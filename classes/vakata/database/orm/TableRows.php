<?php
namespace vakata\database\orm;

use vakata\database\ResultInterface;
use vakata\database\DatabaseException;

class TableRows implements TableRowsInterface, \JsonSerializable
{
	protected $db  = null;
	protected $col = null;
	protected $tbl = null;
	protected $rel = [];
	protected $ext = [];
	protected $del = 0;
	protected $cnt = 0;

	public function __construct(ResultInterface $col, TableInterface $tbl, $cnt = 0) {
		$this->col = $col;
		$this->tbl = $tbl;
		$this->cnt = $cnt;

		$this->ext = count($this->col) ? array_fill(0, count($this->col), null) : [];
	}

	protected function extend($key, array $data = null) {
		if(isset($this->ext[$key])) {
			return $this->ext[$key];
		}
		if($data === null) {
			return null;
		}
		return $this->ext[$key] = new TableRow($this->tbl, $data);
	}

	public function cnt() {
		return $this->cnt;
	}

	public function getTable() {
		return $this->tbl;
	}
	public function toArray($full = true) {
		$temp = [];
		foreach($this as $k => $v) {
			$temp[] = $v->toArray($full);
		}
		return $temp;
	}
	public function save() {
		$wasInTransaction = $this->tbl->getDatabase()->isTransaction();
		if(!$wasInTransaction) {
			$this->tbl->getDatabase()->begin();
		}
		try {
			foreach($this->ext as $k => $v) {
				if($v === false) {
					$v->delete();
				}
				if($v !== null) {
					$v->save();
				}
			}
		} catch (DatabaseException $e) {
			if($wasInTransaction) {
				throw $e;
			}
			$this->tbl->getDatabase()->rollback();
		}
	}

	/* ARRAY FUNCTIONS */
	public function offsetGet($offset) {
		if(!$this->offsetExists($offset) || !array_key_exists($offset, $this->ext) || $this->ext[$offset] === false) {
			return null;
		}
		return isset($this->ext[$offset]) ? $this->ext[$offset] : $this->extend($offset, $this->col->offsetGet($offset));
	}
	public function offsetSet($offset, $value) {
		if(!is_array($value)) {
			throw new ORMException('Only arrays can be used with offsetSet');
		}
		if($offset !== null && !$this->offsetExists($offset)) {
			throw new ORMException('Invalid offset used with offsetSet', 404);
		}
		if($offset === null) {
			$offset = count($this->ext);
			$this->extend($offset, (array)$value);
		}
		else {
			$temp = $this->offsetGet($offset);
			$temp->fromArray($value);
		}
	}
	public function offsetExists($offset) {
		return $offset < count($this->ext) && array_key_exists($offset, $this->ext) && $this->ext[$offset] !== false;
	}
	public function offsetUnset($offset) {
		if($this->offsetExists($offset)) {
			$this->ext[$offset] = false;
			$this->del++;
		}
	}
	public function count() {
		return count($this->ext) - $this->del;
	}
	public function current() {
		if($temp = current($this->ext)) {
			return $temp;
		}
		return $this->extend(key($this->ext), $this->col->current());
	}
	public function key() {
		return key($this->ext);
	}
	public function next() {
		$this->col->next();
		next($this->ext);
	}
	public function rewind() {
		$this->col->rewind();
		reset($this->ext);
	}
	public function valid() {
		while(current($this->ext) === false && key($this->ext) !== null) {
			$this->next();
		}
		return key($this->ext) !== null;
	}

	/* helpers */
	public function __debugInfo() {
		return $this->toArray();
	}
	public function jsonSerialize() {
		return $this->toArray();
	}
}