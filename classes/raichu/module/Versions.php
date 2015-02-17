<?php
namespace raichu\module;

use vakata\database\DatabaseInterface;
use vakata\database\orm\TableInterface;

class Versions
{
	protected $db = null;
	protected $tb = null;
	public function __construct(DatabaseInterface $db, $tb) {
		$this->db = $db;
		$this->tb = $tb;
	}
	public function __invoke(TableInterface $table, $id, $data = null) {
		$v = (int)$this->db->one('SELECT MAX(version) FROM '.$this->tb.' WHERE table_nm = ? AND table_pk = ?', [$table, $id]);
		$o = $this->db->one('SELECT * FROM '.$table->getTableName().' WHERE '.$table->getPrimaryKey().' = ?', [$id]);
		return $this->db->query(
				'INSERT INTO '.$this->tb.' (table_nm, table_pk, version, data, object) VALUES(?,?,?,?,?)', 
				[$table, $id, ++$v, json_encode($data), json_encode($o)]
			)->affected() > 0;
	}
}