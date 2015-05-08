<?php
namespace vakata\database\orm;

use vakata\database\DatabaseInterface;

class Table implements TableInterface
{
	protected $db     = null;
	protected $tb     = null;
	protected $pk     = null;
	protected $fd     = [];
	protected $tx     = [];
	protected $lk     = [];
	protected $rl     = [];
	protected $ext    = [];
	protected $new    = [];
	protected $del    = [];
	protected $result = null;
	protected $filter = ['1=1'];
	protected $params = [];
	protected $joined = [];

	public function __construct(DatabaseInterface $db, $tb, array $definition = null) {
		$this->db = $db;
		$this->tb = $tb;

		if (!$definition) {
			$definition = $this->getDefinition();
		}
		$this->pk = $definition['primary_key'];
		$this->fd = $definition['columns'];
		$this->tx = isset($definition['indexed']) ? $definition['indexed'] : [];
		if(isset($definition['like']) && is_array($definition['like'])) {
			foreach($definition['like'] as $c) {
				if(in_array($c, $this->fd)) {
					$this->lk[] = $c;
				}
			}
		}

		$this->reset();
	}

	// getters
	public function getDefinition() {
		$res = [ 'columns' => [], 'primary_key' => null, 'definitions' => [], 'indexed' => [] ];
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
				foreach($this->db->all('SHOW INDEX FROM '.$this->tb.' WHERE Index_type = \'FULLTEXT\'') as $data) {
					$res['indexed'][] = $data['Column_name'];
				}
				$res['indexed'] = array_unique($res['indexed']);
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
				throw new ORMException('Driver is not supported: ' . $this->db->driver(), 500);
		}
		return $res;
	}
	public function getTableName() {
		return $this->tb;
	}
	public function getIndexed() {
		return $this->tx;
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
	public function getRelationKeys() {
		return array_keys($this->rl);
	}
	public function getDatabase() {
		return $this->db;
	}

	// relations
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
	protected function addRelation($tb, $local_key, $foreign_key = null, $many = true, $pivot = null, $field = null) {
		$df = null;
		if(is_array($tb)) {
			$df = isset($tb['definition']) ? $tb['definition'] : null;
			$tb = $tb['table'];
		}
		$temp = $tb instanceof Table ? $tb : new Table($this->db, $tb, $df);
		if(!$foreign_key) {
			$foreign_key = ($pivot ? $temp->getTableName() . '_' : '') . $temp->getPrimaryKey();
		}
		$this->rl[$field ? $field : $tb . '_' . $local_key] = [
			'table'       => $temp,
			'local_key'   => $local_key,
			'foreign_key' => $foreign_key,
			'many'        => $many,
			'pivot'       => $pivot,
			'field'       => $field ? $field : $tb . '_' . $local_key
		];
		return $this;
	}

	// selection
	public function search($term) {
		if(count($this->tx) && is_string($term) && strlen($term) >= 4) {
			$this->filter('MATCH ('.implode(', ', $this->tx).') AGAINST (?)', [$term]);
		}
		if(count($this->lk) && is_string($term) && strlen($term)) {
			$term = '%' . str_replace(['%','_'], ['\\%', '\\_'], $term) . '%';
			$sql = '';
			$par = [];
			foreach($this->lk as $fd) {
				$sql[] = $fd . ' LIKE ?';
				$par[] = $term;
			}
			$this->filter('('.implode(' OR ', $sql).')', $par);
		}
		return $this;
	}
	public function filter($sql, array $params = []) {
		$this->filter[] = '(' . $sql . ')';
		$this->params = array_merge($this->params, array_values($params));
		$this->result = null;
		$this->ext = [];
		return $this;
	}
	public function reset() {
		$this->filter = ['1=1'];
		$this->params = [];
		$this->result = null;
		$this->joined = [];
		$this->ext = [];
		return $this;
	}

	public function count() {
		$sql = 'SELECT COUNT(DISTINCT t.' . $this->pk . ') FROM ' . $this->tb . ' AS t ';
		foreach($this->joined as $k => $v) {
			if($this->rl[$k]['pivot']) {
				$sql .= 'LEFT JOIN ' . $this->rl[$k]['pivot'] . ' AS '.$k.'_pivot ON t.' . $this->pk . ' = ' . $k . '_pivot.' . $this->rl[$k]['local_key'] . ' ';
				$sql .= 'LEFT JOIN ' . $this->rl[$k]['table']->getTableName() . ' AS '.$k.' ON ' . $k . '.' . $this->rl[$k]['table']->getPrimaryKey() . ' = ' . $k . '_pivot.' . $this->rl[$k]['foreign_key'] . ' ';
			}
			else {
				$sql .= $v . ' JOIN ' . $this->rl[$k]['table']->getTableName() . ' AS '.$k.' ON t.' . $this->rl[$k]['local_key'] . ' = ' . $k . '.' . $this->rl[$k]['foreign_key'] . ' ';
			}
		}
		$sql .= 'WHERE ' . implode(' AND ', $this->filter) . ' ';
		return $this->db->one($sql, $this->params);
	}
	public function select($order = null, $limit = 0, $offset = 0, array $fields = null) {
		if($fields && count($fields)) {
			$temp = [];
			foreach($fields as $k => $v) {
				if(!strpos($v, '.')) {
					if(in_array($v, $this->fd) || $v === '*') {
						$temp[] = 't.' . $v;
					}
				}
				else {
					if(preg_match('(^[a-z_0-9]+\.[a-z_0-9*]+$)i', $v)) {
						$v = explode('.', $v, 2);
						if(isset($this->rl[$v[0]]) && ($v[1] === '*' || in_array($v[1], $this->rl[$v[0]]['table']->getColumns()))) {
							$this->joined[$v[0]] = 'LEFT';
							$temp[] = $v[1] === '*' ? implode('.', $v) : implode('.', $v) . ' AS ' . implode('___', $v);
							//$temp[] = 't.' . $this->rl[$v[0]]['local_key'];
						}
					}
				}
			}
			$fields = $temp;
		}
		if(!$fields || !count($fields)) {
			$fields = [];
			foreach($this->fd as $c) {
				$fields[] = 't.'.$c;
			}
		}
		$sql = 'SELECT ' . implode(', ', $fields). ' FROM ' . $this->tb . ' AS t ';
		foreach($this->joined as $k => $v) {
			if($this->rl[$k]['pivot']) {
				$sql .= 'LEFT JOIN ' . $this->rl[$k]['pivot'] . ' AS '.$k.'_pivot ON t.' . $this->pk . ' = ' . $k . '_pivot.' . $this->rl[$k]['local_key'] . ' ';
				$sql .= 'LEFT JOIN ' . $this->rl[$k]['table']->getTableName() . ' AS '.$k.' ON ' . $k . '.' . $this->rl[$k]['table']->getPrimaryKey() . ' = ' . $k . '_pivot.' . $this->rl[$k]['foreign_key'] . ' ';
			}
			else {
				$sql .= $v . ' JOIN ' . $this->rl[$k]['table']->getTableName() . ' AS '.$k.' ON t.' . $this->rl[$k]['local_key'] . ' = ' . $k . '.' . $this->rl[$k]['foreign_key'] . ' ';
			}
		}
		$sql .= 'WHERE ' . implode(' AND ', $this->filter) . ' ';
		if(count($this->joined)) {
			$sql .= 'GROUP BY t.' . $this->pk . ' ';
		}
		if($order) {
			$sql .= 'ORDER BY ' . $order . ' ';
		}
		if((int)$limit) {
			$sql .= 'LIMIT ' . (int)$limit . ' ';
		}
		if((int)$limit && (int)$offset) {
			$sql .= 'OFFSET ' . (int)$offset;
		}
		$this->result = $this->db->get($sql, $this->params, null, false, 'assoc', false);
		$this->ext = [];
		return $this;
	}
	// mass changes
	// public function update(array $data, $order = null, $limit = 0) {
	// 	$fields = [];
	// 	$params = [];
	// 	foreach($data as $k => $v) {
	// 		// TODO: make sure fields are valid
	// 		$fields[] = $k . ' = ? ';
	// 		$params[] = $v;
	// 	}
	// 	if(!count($fields)) {
	// 		throw new ORMException('Nothing to update');
	// 	}
	// 	return $this->db->query('' .
	// 		'UPDATE ' . $this->tb . ' SET ' . implode(', ', $fields) . ' ' .
	// 		'WHERE ' . implode(' AND ', $this->filter) . ' ' .
	// 		($order ? 'ORDER BY ' . $order : '') . ' ' .
	// 		((int)$limit ? 'LIMIT ' . (int)$limit : ''),
	// 	array_merge($params, $this->params))->affected();
	// }
	// public function delete($order = null, $limit = 0) {
	// 	return $this->db->query('' .
	// 		'DELETE FROM ' . $this->tb . ' ' .
	// 		'WHERE ' . implode(' AND ', $this->filter) . ' ' .
	// 		($order ? 'ORDER BY ' . $order : '') . ' ' .
	// 		((int)$limit ? 'LIMIT ' . (int)$limit : ''),
	// 	$this->params)->affected();
	// }

	public function read($settings = null) {
		//$this->all();
		if(isset($settings) && is_numeric($settings)) {
			return $this->filter($this->pk . ' = ' . (int)$settings)->select();
		}
		$settings = array_merge([
			'l' => null,
			'p' => 0,
			'o' => null,
			'd' => 0,
			'q' => '',
			'f' => '*'
		], isset($settings) && is_array($settings) ? $settings : []);
		$fields = is_array($settings['f']) ? $settings['f'] : [ $settings['f'] ];
		$order = null;
		$limit = 0;
		$offset = 0;
		if(isset($settings['o'])) {
			if(in_array($settings['o'], $this->fd)) {
				$order = $settings['o'] . ' ' . (isset($settings['d']) && (int)$settings['d'] ? 'DESC' : 'ASC');
			}
			if(strpos($settings['o'], '.')) {
				$temp = explode('.', $settings['o'], 2);
				if(isset($this->rl[$temp[0]]) && in_array($temp[1], $this->rl[$temp[0]]['table']->getColumns())) {
					$this->joined[$temp[0]] = 'LEFT';
					$order = $settings['o'] . ' ' . (isset($settings['d']) && (int)$settings['d'] ? 'DESC' : 'ASC');
				}
			}
		}
		if(isset($settings['l']) && (int)$settings['l']) {
			$limit = (int)$settings['l'];
			if(isset($settings['p'])) {
				$offset = (int)$settings['p'] * $limit;
			}
		}
		if(isset($settings['q'])) {
			$this->search($settings['q']);
		}
		// filter on local columns
		foreach($this->fd as $column) {
			if(isset($settings[$column])) {
				if(!is_array($settings[$column])) {
					$this->filter($column . ' = ?', [$settings[$column]]);
					continue;
				}
				if(isset($settings[$column]['beg']) && isset($settings[$column]['end'])) {
					$this->filter($column . ' BETWEEN ? AND ?', [ $settings[$column]['beg'], $settings[$column]['end'] ]);
					continue;
				}
				if(count($settings[$column])) {
					$this->filter($column . ' IN ('.implode(',', array_fill(0, count($settings[$column]), '?')).')', $settings['column']);
					continue;
				}
			}
		}
		// filter on remote columns
		foreach($settings as $column => $filter) {
			if(!strpos($column, '.') || !preg_match('(^[a-z_0-9]+\.[a-z_0-9]+$)i', $column)) {
				continue;
			}

			$temp = explode('.', $column, 2);
			if(isset($this->rl[$temp[0]]) && in_array($temp[1], $this->rl[$temp[0]]['table']->getColumns())) {
				$this->joined[$temp[0]] = 'INNER';
				if(!is_array($settings[$column])) {
					$this->filter($column . ' = ?', [$settings[$column]]);
					continue;
				}
				if(isset($settings[$column]['beg']) && isset($settings[$column]['end'])) {
					$this->filter($column . ' BETWEEN ? AND ?', [ $settings[$column]['beg'], $settings[$column]['end'] ]);
					continue;
				}
				if(count($settings[$column])) {
					$this->filter($column . ' IN ('.implode(',', array_fill(0, count($settings[$column]), '?')).')', $settings['column']);
					continue;
				}
			}
		}
		return $this->select($order, $limit, $offset, $fields);
	}
	// creation
	public function create(array $data) {
		$temp = new TableRow(clone $this);
		$temp->fromArray($data);
		return $this->new[] = $temp;
	}

	// row processing
	protected function extend($key, array $data = null) {
		if(isset($this->ext[$key])) {
			return $this->ext[$key];
		}
		if($data === null) {
			return null;
		}
		return $this->ext[$key] = new TableRow($this, $data);
	}

	// array stuff - collection handling
	public function offsetGet($offset) {
		if($this->result === null) {
			$this->select();
		}
		return $this->result->offsetExists($offset) ? $this->extend($offset, $this->result->offsetGet($offset)) : null;
	}
	public function offsetSet($offset, $value) {
		if($offset === null) {
			if(is_array($value)) {
				$this->create($value);
				return ;
			}
			if($value instanceof TableRow) {
				return $this->new[] = $value;
			}
			throw new ORMException('Invalid input to offsetSet');
		}
		if($this->result === null) {
			$this->select();
		}
		if(!$this->offsetExists($offset)) {
			throw new ORMException('Invalid offset used with offsetSet', 404);
		}
		$temp = $this->offsetGet($offset);
		if(is_array($value)) {
			return $temp->fromArray($value);
		}
		if($value instanceof TableRow) {
			$this->del[] = $temp;
			$this->new[] = $value;
		}
		throw new ORMException('Invalid input to offsetSet');
	}
	public function offsetExists($offset) {
		if($this->result === null) {
			$this->select();
		}
		return $this->result->offsetExists($offset);
	}
	public function offsetUnset($offset) {
		if($this->result === null) {
			$this->select();
		}
		if(!$this->offsetExists($offset)) {
			throw new ORMException('Invalid offset used with offsetUnset', 404);
		}
		$temp = $this->offsetGet($offset);
		if(!$temp) {
			throw new ORMException('Invalid offset used with offsetUnset', 404);
		}
		$this->del[] = $temp;
	}
	public function current() {
		if($this->result === null) {
			$this->select();
		}
		return $this->extend($this->result->key(), $this->result->current());
	}
	public function key() {
		if($this->result === null) {
			$this->select();
		}
		return $this->result->key();
	}
	public function next() {
		if($this->result === null) {
			$this->select();
		}
		$this->result->next();
	}
	public function rewind() {
		if($this->result === null) {
			$this->select();
		}
		$this->result->rewind();
	}
	public function valid() {
		if($this->result === null) {
			$this->select();
		}
		return $this->result->valid();
	}

	// helpers
	public function toArray($full = true, $xtra = true) {
		$temp = [];
		foreach($this as $k => $v) {
			$temp[$k] = $v->toArray($full, $xtra);
		}
		return $temp;
	}
	public function __debugInfo() {
		return $this->toArray();
	}
	public function jsonSerialize() {
		return $this->toArray();
	}

	// modifiers
	public function save(array $data = []) {
		$wasInTransaction = $this->db->isTransaction();
		if(!$wasInTransaction) {
			$this->db->begin();
		}
		try {
			$ret = [];
			foreach($this->new as $temp) {
				foreach($data as $k => $v) {
					$temp->{$k} = $v;
				}
				$ret[$temp->save()] = true;
			}
			foreach($this as $temp) {
				foreach($data as $k => $v) {
					$temp->{$k} = $v;
				}
				$ret[$temp->save()] = true;
			}
			foreach($this->del as $temp) {
				unset($ret[$temp->delete()]);
			}
			if(!$wasInTransaction) {
				$this->db->commit();
			}
			return array_keys($ret);
		} catch (DatabaseException $e) {
			if(!$wasInTransaction) {
				$this->db->rollback();
			}
			throw $e;
		}
	}
	public function delete() {
		$wasInTransaction = $this->db->isTransaction();
		if(!$wasInTransaction) {
			$this->db->begin();
		}
		try {
			foreach($this as $temp) {
				$temp->delete();
			}
			if(!$wasInTransaction) {
				$this->db->commit();
			}
		} catch (DatabaseException $e) {
			if(!$wasInTransaction) {
				$this->db->rollback();
			}
			throw $e;
		}
	}
}