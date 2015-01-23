<?php
namespace vakata\database\orm;

use vakata\database\DatabaseInterface;

class Table implements TableInterface, \JsonSerializable
{
	protected $db = null;
	protected $tb = null;
	protected $pk = null;
	protected $fd = [];
	protected $rl = [];

	protected $whe = '1 = 1';
	protected $par = null;
	protected $col = null;

	public function __construct(DatabaseInterface $db, $tb, $pk = null, array $fd = null) {
		$this->db = $db;
		$this->tb = $tb;

		if(!$pk || !$fd) {
			$tmp = $this->getDefinition();
			if(!$pk) {
				$pk = $tmp['primary_key'];
			}
			if(!$fd) {
				$fd = $tmp['columns'];
			}
			//$pk = $tmp['pk'] ? $tmp['pk'] : $pk;
			//$fd = $tmp['fields'];
		}

		$this->pk = $pk ? $pk : 'id';
		$this->fd = count($fd) ? $fd : [$this->pk];
		if(is_array($this->fd)) {
			$this->fd[] = $this->pk;
			$this->fd = array_unique($this->fd);
		}
	}

	public function getDefinition() {
		$res = [ 'columns' => [], 'primary_key' => null, 'definitions' => [] ];
		switch($this->db->driver()) {
			case 'mysql':
			case 'mysqli':
				foreach($this->db->all('SHOW FULL COLUMNS FROM '.$this->tb) as $data) {
					$res['columns'][] = $data['Field'];
					$res['definitions'][$data['Field']] = $data;
					if($data['Key'] == 'PRI') {
						$res['primary_key'] = $data['Field'];
					}
				}
				break;
			case 'postgre':
			case 'oracle':
				$res['definitions'] = $this->db->all('SELECT * FROM information_schema.columns WHERE table_name = ? ', [ $this->tb ], 'column_name');
				$res['columns'] = array_keys($res['definitions']);
				$tmp = $this->db->one('SELECT constraint_name FROM information_schema.table_constraints WHERE table_name = ? AND constraint_type = ?', [ $this->tb, 'PRIMARY KEY' ]);
				if($tmp) {
					$res['primary_key'] = $this->db->one('SELECT column_name FROM information_schema.key_column_usage WHERE table_name = ? AND constraint_name = ?', [ $this->tb, $tmp ]);
				}
				break;
			default:
				throw new APIException('Driver is not supported: ' . $this->db->driver(), 500);
		}
		return $res;
	}
	public function hasOne($tb, $key = null, $field = null) {
		return $this->addRelation($tb, $this->pk, $key ? $key : $this->tb . '_id', false, null, $field);
	}
	public function hasMany($tb, $key = null, $field = null) {
		return $this->addRelation($tb, $this->pk, $key ? $key : $this->tb . '_id', true, null, $field);
	}
	public function belongsTo($tb, $key = null, $field = null) {
		return $this->addRelation($tb, $key ? $key : $tb . '_id', null, false, null, $field);
	}
	public function manyToMany($tb, $pivot = null, $field = null) {
		return $this->addRelation($tb, $this->tb . '_' . $this->pk, null, true, $pivot ? $pivot : $this->tb . '_' . $pivot, $field);
	}
	public function addRelation($tb, $local_key, $foreign_key = null, $many = true, $pivot = null, $field = null) {
		$temp = $tb instanceof Table ? $tb : new Table($this->db, $tb);
		if(!$foreign_key) {
			$foreign_key = ($pivot ? $temp->getTable() . '_' : '') . $temp->getPrimaryKey();
		}
		$this->rl[] = [
			'table'       => $temp,
			'local_key'   => $local_key,
			'foreign_key' => $foreign_key,
			'many'        => $many,
			'pivot'       => $pivot,
			'field'       => $field ? $field : $tb . '_' . $local_key
		];
		return $this;
	}

	public function getTable() {
		return $this->tb;
	}
	public function getColumns() {
		return $this->fd;
	}
	public function getPrimaryKey() {
		return $this->pk;
	}
	public function getRelations() {
		return $this->rl;
	}
	public function getDatabase() {
		return $this->db;
	}

	public function filter($sql, array $par = []) {
		if(is_int($sql) || is_numeric($sql)) {
			$par = [ (int)$sql ];
			$sql = ' id = ? ';
		}
		$this->whe = $sql;
		$this->par = $par;
		return $this;
	}
	public function cnt() {
		return $this->whe !== null ? $this->db->one('SELECT COUNT(*) AS cnt FROM ' . $this->tb . ' WHERE ' . $this->whe, $this->par) : 0;
	}

	public function get($order = null, $limit = null, $offset = null) {
		return new TableRows(
			$this->db->get('SELECT ' . implode(', ', $this->fd) . ' FROM ' . $this->tb . ' WHERE ' . $this->whe . ( $order ? ' ORDER BY ' . $order : '') . ( (int)$limit ? ' LIMIT ' . (int)$limit : '') . ( (int)$offset ? ' OFFSET ' . (int)$offset : ''), $this->par),
			$this
		);
	}
	public function all($order = null, $limit = null, $offset = null) {
		return $this->get($order, $limit, $offset)->toArray();
	}
	public function one($id = null, $order = null, $offset = null) {
		if($id) {
			$this->filter((int)$id);
		}
		$temp = $this->get($order, 1, $offset);
		return isset($temp[0]) ? $temp[0] : null;
	}

	public function insert(array $data) {
		$par = [];
		$fld = [];
		foreach($data as $k => $v) {
			if(in_array($k, $this->fd)) {
				$par[] = $v;
				$fld[] = $k;
			}
		}
		return $this->db->query('INSERT INTO ' . $this->tb . ' (' . implode(', ', $k) . ') VALUES (' . implode(',', array_fill(0, count($par), '?')) . ')', $par)->insertId();
	}
	public function update(array $data) {
		$par = [];
		$fld = [];
		foreach($data as $k => $v) {
			if(in_array($k, $this->fd)) {
				$par[] = $v;
				$fld[] = $k . ' = ? ';
			}
		}
		return $this->db->query('UPDATE ' . $this->tb . ' SET ' . implode(', ', $fld) . ' WHERE ' . $this->whe, array_merge($par, $this->par))->affected();
	}
	public function delete() {
		return $this->db->query('DELETE FROM ' . $this->tb . ' WHERE ' . $this->whe, $this->par)->affected();
	}

	public function jsonSerialize() {
		return $this->all();
	}
}