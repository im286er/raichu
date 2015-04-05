<?php
namespace vakata\database\orm;

use vakata\dabatase\DatabaseException;

class TableRow implements TableRowInterface
{
	protected $tbl  = null;
	protected $data = [];
	protected $xtra = [];
	protected $inst = [];
	protected $chng = [];
	protected $cche = [];

	public function __construct(Table $tbl, array $data = []) {
		$this->tbl  = $tbl;
		foreach($this->tbl->getColumns() as $column) {
			if(isset($data[$column])) {
				$this->data[$column] = $data[$column];
			}
		}
		foreach($data as $k => $v) {
			if(!isset($this->data[$k])) {
				$this->data[str_replace('___', '.', $k)] = $v;
			}
		}
		foreach($this->tbl->getRelations() as $rl) {
			$this->inst[$rl['field']] = [
				'class'       => $rl['table'],
				'many'        => $rl['many'],
				'foreign_key' => $rl['foreign_key'],
				'local_key'   => $rl['local_key'],
				'pivot'       => $rl['pivot']
			];
		}
	}

	public function getTableName() {
		return $this->tbl->getTableName();
	}
	public function getIndexed() {
		return $this->tbl->getIndexed();
	}
	public function getColumns() {
		return $this->tbl->getColumns();
	}
	public function getPrimaryKey() {
		return $this->tbl->getPrimaryKey();
	}
	public function getRelations() {
		return $this->tbl->getRelations();
	}
	public function getRelationKeys() {
		return $this->tbl->getRelationKeys();
	}

	public function getID() {
		$pk = $this->tbl->getPrimaryKey();
		return $this->{$pk};
	}

	public function __get($key) {
		if(isset($this->chng[$key])) {
			return $this->chng[$key];
		}
		if(isset($this->data[$key])) {
			return $this->data[$key];
		}
		if(isset($this->inst[$key])) {
			$temp = $this->{$key}();
			if(isset($temp)) {
				return $this->inst[$key]['many'] ? $temp : (isset($temp[0]) ? $temp[0] : null);
			}
		}
		return null;
	}
	public function __call($key, $args) {
		if(!isset($this->inst[$key])) {
			return null;
		}
		$ckey = $key;
		if(count($args)) {
			$ckey .= '_' . md5(serialize($args));
		}
		if(isset($this->cche[$ckey])) {
			return $this->cche[$ckey];
		}

		$inst = $this->inst[$key];
		$lkey = $inst['local_key'];
		$pkey = $this->tbl->getPrimaryKey();
		$tbl  = clone $inst['class'];

		if($inst['pivot']) {
			$ids = $this->{$pkey} ? 
				$this->tbl->getDatabase()->all('SELECT ' . $inst['foreign_key'] . ' FROM ' . $inst['pivot'] . ' WHERE ' . $inst['local_key'] . ' = ?', [$this->{$pkey}]) : 
				[];
			return $this->cche[$ckey] = count($ids) ?
				$tbl->filter($inst['class']->getPrimaryKey() . ' IN ('.implode(',',array_fill(0, count($ids), '?')).')', $ids)->read(isset($args[0]) ? $args[0] : []) :
				$tbl->filter('1 = 0')->read();
		}
		return $this->cche[$ckey] = $this->{$lkey} ? 
			$tbl->filter($inst['foreign_key'] . ' = ?', [$this->{$lkey}])->read(isset($args[0]) ? $args[0] : []) : 
			$tbl->filter('1 = 0')->read();
	}

	public function toArray($full = true, $xtra = true) {
		$temp = array_merge($this->data, $this->chng);
		if($xtra) {
			$temp = array_merge($temp, $this->xtra);
		}
		if($full) {
			foreach($this->inst as $k => $v) {
				if($this->{$k}) {
					$temp[$k] = $this->{$k}->toArray(true);
				}
			}
		}
		return $temp;
	}
	public function __debugInfo() {
		return $this->toArray();
	}
	public function jsonSerialize() {
		return $this->toArray();
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
		if(isset($this->inst[$key])) {
			$temp = $this->{$k}();
			if($temp) {
				foreach($temp as $k => $v) {
					unset($temp[$k]);
				}
				if($value !== null) {
					$temp[] = $value;
				}
			}
		}
	}

	public function save() {
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
					foreach($this->{$k}()->save() as $id) {
						$this->chng[$v['local_key']] = $id;
					}
				}
			}

			// own data
			if(count($this->chng)) {
				if($fk !== null) {
					$col = [];
					$par = [];
					foreach($this->chng as $k => $v) {
						if(in_array($k, $this->tbl->getColumns())) {
							$col[] = $k. ' = ?';
							$par[] = $v;
						}
					}
					if(count($col)) {
						$par[] = $fk;
						$this->tbl->getDatabase()->query('UPDATE ' . $this->tbl->getTableName() . ' SET ' . implode(', ', $col) . ' WHERE id = ?', $par);
					}
				}
				else {
					$temp = [];
					foreach($this->chng as $k => $v) {
						if(in_array($k, $this->tbl->getColumns())) {
							$temp[$k] = $v;
						}
					}
					if(!count($temp)) {
						throw new ORMException('Nothing to insert');
					}
					$fk = $this->tbl->getDatabase()->query('INSERT INTO ' . $this->tbl->getTableName() . ' ('.implode(', ', array_keys($temp)).') VALUES ('.implode(', ', array_fill(0, count($temp), '?')).')', array_values($temp))->insertId();
					$nw = true;
				}
			}
			// has relations
			if($fk) {
				foreach($this->inst as $k => $v) {
					if($v['pivot']) {
						$temp = $this->{$k}()->save();
						$this->tbl->getDatabase()->query('DELETE FROM '.$v['pivot'].' WHERE ' . $v['local_key'] . ' = ?', [$fk]);
						foreach($temp as $id) {
							$this->tbl->getDatabase()->query('INSERT INTO '.$v['pivot'].' ('.$v['local_key'].', '.$v['foreign_key'].') VALUES(?,?)', [$fk, $id]);
						}
					}
					else {
						if($v['local_key'] === $id) {
							// set the foreign key on all rows in the collection to $fk
							$temp = [];
							$temp[$v['foreign_key']] = $fk;
							$this->{$k}()->save($temp);
						}
					}
				}
			}
			if(!$wasInTransaction) {
				$this->tbl->getDatabase()->commit();
			}
			return $fk;
		} catch (DatabaseException $e) {
			if(!$wasInTransaction) {
				$this->tbl->getDatabase()->rollback();
			}
			throw $e;
		}
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
						foreach($this->{$k}() as $kk => $item) {
							//unset($this->{$k}()[$kk]);
							$item->delete();
						}
					}
				}
			}
			if($fk) {
				$this->tbl->getDatabase()->query('DELETE FROM ' . $this->tbl->getTableName() . ' WHERE '.$this->tbl->getPrimaryKey().' = ?', [ $fk ]);
			}
			if(!$wasInTransaction) {
				$this->tbl->getDatabase()->commit();
			}
			return $fk;
		} catch (DatabaseException $e) {
			if(!$wasInTransaction) {
				$this->tbl->getDatabase()->rollback();
			}
			throw $e;
		}
	}
}

