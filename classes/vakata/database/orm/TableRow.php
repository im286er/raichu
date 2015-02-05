<?php
namespace vakata\database\orm;

use vakata\dabatase\DatabaseException;

class TableRow implements TableRowInterface, \JsonSerializable
{
	protected $tbl  = null;
	protected $data = [];
	protected $inst = [];
	protected $chng = [];
	protected $cche = [];

	public function __construct(TableInterface $tbl, array $data = []) {
		$this->tbl  = $tbl;
		foreach($this->tbl->getColumns() as $column) {
			if(isset($data[$column])) {
				$this->data[$column] = $data[$column];
			}
		}
		foreach($this->tbl->getRelations() as $rl) {
			$this->inst[$rl['field']] = [
				'class'       => clone $rl['table'],
				'many'        => $rl['many'],
				'foreign_key' => $rl['foreign_key'],
				'local_key'   => $rl['local_key'],
				'pivot'       => $rl['pivot']
			];
		}
	}

	public function getID() {
		$pk = $this->tbl->getPrimaryKey();
		return $this->{$pk};
	}
	public function getTable() {
		return $this->tbl;
	}

	public function offsetGet($offset) {
		if(!$this->offsetExists($offset)) {
			//throw new ORMException('Invalid offset used with offsetGet', 404);
			return null;
		}
		return isset($this->inst[$offset]) ? $this->__call($offset, []) : $this->__get($offset);
	}
	public function offsetSet($offset, $value) {
		$this->__set($offset, $value);
	}
	public function offsetExists($offset) {
		return in_array($offset, array_filter(array_unique(array_merge(array_keys($this->data), array_keys($this->chng), array_keys($this->inst)))));
	}
	public function offsetUnset($offset) {
		if(isset($this->chng[$offset])) {
			unset($this->chng[$offset]);
		}
	}

	public function __get($key) {
		return isset($this->chng[$key]) ? $this->chng[$key] : (isset($this->data[$key]) ? $this->data[$key] : null);
	}
	public function __call($key, $args) {
		if(!isset($this->inst[$key])) {
			return null;
		}
		$ckey = $key;
		if(count($args)) {
			$ckey .= '_' . md5(serialize($args));
		}
		if(!isset($this->cche[$ckey])) {
			$inst = $this->inst[$key];
			$lkey = $inst['local_key'];
			$pkey = $this->tbl->getPrimaryKey();

			if(isset($args[0]) && (is_int($args[0]) || is_numeric($args[0]))) {
				$args[1] = [ (int)$args[0] ];
				$args[0] = ' '.$inst['class']->getPrimaryKey().' = ? ';
			}
			if(!isset($args[0])) { $args[0] = ' 1 = 1 '; }
			if(!isset($args[1])) { $args[1] = []; }
			if(!isset($args[2])) { $args[2] = null; }
			if(!isset($args[3])) { $args[3] = null; }
			if(!isset($args[4])) { $args[4] = null; }

			if($inst['pivot']) {
				$ids = $this->{$pkey} ? 
					$this->tbl->getDatabase()->all('SELECT ' . $inst['foreign_key'] . ' FROM ' . $inst['pivot'] . ' WHERE ' . $inst['local_key'] . ' = ?', [$this->{$pkey}]) : 
					[];
				if(count($ids)) {
					$this->cche[$ckey] = $inst['class']->read($inst['class']->getPrimaryKey() . ' IN ('.implode(',',array_fill(0, count($ids), '?')).') AND ('.$args[0].') ', array_merge($ids, $args[1]), $args[2], $args[3], $args[4]);
				}
				else {
					$this->cche[$ckey] = $inst['class']->read('1 = 0');
				}
			}
			else {
				if($this->{$lkey}) {
					$this->cche[$ckey] = $inst['class']->read($inst['foreign_key'] . ' = ? AND ('.$args[0].') ', array_merge([$this->{$lkey}], $args[1]), $args[2], $args[3], $args[4]);
				}
				else {
					$this->cche[$ckey] = $inst['class']->read('1 = 0');
				}
			}
		}
		if($this->inst[$key]['many']) {
			return $this->cche[$ckey];
		}
		if(!isset($this->cche[$ckey][0])) {
			$this->cche[$ckey][] = [];
		}
		return $this->cche[$ckey][0];
	}
	public function __debugInfo() {
		return $this->toArray();
	}
	public function jsonSerialize() {
		return $this->toArray();
	}
	public function toArray($full = true) {
		if($this->isNull()) {
			return null;
		}
		$temp = array_merge($this->data, $this->chng);
		if($full) {
			foreach($this->inst as $k => $v) {
				$temp[$k] = $this->{$k}()->toArray(true);
			}
		}
		return $temp;
	}
	public function fromArray(array $data) {
		foreach($data as $k => $v) {
			$this->__set($k, $v);
		}
	}
	public function __set($key, $value) {
		if(in_array($key, $this->tbl->getColumns()) && (isset($this->chng[$key]) || !isset($this->data[$key]) || $this->data[$key] !== $value)) {
			$this->chng[$key] = $value;
		}
	}
	protected function isNull() {
		return count($this->chng) + count($this->data) === 0;
	}
	public function save() {
		if($this->isNull()) {
			return null;
		}

		$wasInTransaction = $this->tbl->getDatabase()->isTransaction();
		if(!$wasInTransaction) {
			$this->tbl->getDatabase()->begin();
		}
		try {
			$id = $this->tbl->getPrimaryKey();
			$fk = $this->{$id};
			$nw = false;

			if(!$fk) {
				$this->chng = array_merge($this->data, $this->chng);
			}

			// belongs relations
			foreach($this->inst as $k => $v) {
				if(!$v['pivot'] && $v['foreign_key'] === $v['class']->getPrimaryKey()) {
					// belongs relation, update own field
					$tmp = $this->{$k}()->save();
					if($tmp !== null) {
						$this->chng[$v['local_key']] = $tmp;
					}
				}
			}

			// own data
			if(count($this->chng)) {
				if($fk) {
					$this->tbl->update($this->chng);
				}
				else {
					$fk = $this->tbl->create($this->chng);
					$nw = true;
				}
			}

			// has relations
			if($fk) {
				foreach($this->inst as $k => $v) {
					if($v['pivot']) {
						$this->tbl->getDatabase()->query('DELETE FROM '.$v['pivot'].' WHERE ' . $v['local_key'] . ' = ?', [$fk]);
						foreach($this->{$k}() as $item) {
							$this->tbl->getDatabase()->query('INSERT INTO '.$v['pivot'].' ('.$v['local_key'].', '.$v['foreign_key'].') VALUES(?,?)', [$fk, $item->save()]);
						}
					}
					else {
						if($v['local_key'] === $id) {
							// set the foreign key on all rows in the collection to $fk
							if($v['many']) {
								if($nw) {
									$this->{$k}()->save();
								}
								else {
									foreach($this->{$k}() as $kk => $item) {
										$item->{$v['foreign_key']} = $fk;
										$item->save();
									}
								}
							}
							else {
								if(!$this->{$k}()->isNull()) {
									$this->{$k}()->{$v['foreign_key']} = $fk;
									$this->{$k}()->save();
								}
							}
						}
					}
				}
			}
		} catch (DatabaseException $e) {
			if($wasInTransaction) {
				throw $e;
			}
			$this->tbl->getDatabase()->rollback();
		}

		return $fk;
	}
	public function delete() {
		$wasInTransaction = $this->tbl->getDatabase()->isTransaction();
		if(!$wasInTransaction) {
			$this->tbl->getDatabase()->begin();
		}
		try {
			$id = $this->tbl->getPrimaryKey();
			$fk = $this->{$id};

			foreach($this->inst as $k => $v) {
				if($v['pivot'] && $fk) {
					$this->tbl->getDatabase()->query('DELETE FROM '.$v['pivot'].' WHERE ' . $v['local_key'] . ' = ?', [$fk]);
				}
				else {
					if($v['local_key'] === $id) {
						// set the foreign key on all rows in the collection to $fk
						foreach($this->{$v['field']}() as $k => $item) {
							unset($this->{$v['field']}()[$k]);
							$item->delete();
						}
					}
				}
			}
			if($fk) {
				$temp = [];
				$temp[$this->tbl->getPrimaryKey()] = $fk;
				$this->tbl->delete($temp);
			}
		} catch (DatabaseException $e) {
			if($wasInTransaction) {
				throw $e;
			}
			$this->tbl->getDatabase()->rollback();
			throw $e;
		}
	}
}

