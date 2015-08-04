<?php
namespace vakata\database\orm;

use vakata\database\DatabaseInterface;

class Table implements TableInterface
{
	protected $db         = null;
	protected $table      = null;
	protected $definition = null;
	protected $relations  = [];

	protected $ext        = [];
	protected $new        = [];
	protected $del        = [];

	protected $result     = null;
	protected $filter     = ['1=1'];
	protected $params     = [];
	protected $joined     = [];

	public function __construct(DatabaseInterface $db, $table, array $definition = null) {
		$this->db         = $db;
		$this->table      = $table;
		$this->definition = isset($definition) ? $definition : $this->analyze();
	}
	public function __clone() {
		$this->reset();
	}
	protected function analyze() {
		$res = [ 'columns' => [], 'primary_key' => [], 'definitions' => [], 'indexed' => [] ];
		switch($this->db->driver()) {
			case 'mysql':
			case 'mysqli':
				foreach($this->db->all('SHOW FULL COLUMNS FROM '.$this->table) as $data) {
					$res['columns'][] = $data['Field'];
					$res['definitions'][$data['Field']] = $data;
					if($data['Key'] == 'PRI') {
						$res['primary_key'][] = $data['Field'];
					}
				}
				foreach($this->db->all('SHOW INDEX FROM '.$this->table.' WHERE Index_type = \'FULLTEXT\'') as $data) {
					$res['indexed'][] = $data['Column_name'];
				}
				$res['indexed'] = array_unique($res['indexed']);
				break;
			case 'postgre':
			case 'oracle':
				$res['definitions'] = $this->db->all('SELECT * FROM information_schema.columns WHERE table_name = ? ', [ $this->table ], 'column_name');
				$res['columns'] = array_keys($res['definitions']);
				$tmp = $this->db->one('SELECT constraint_name FROM information_schema.table_constraints WHERE table_name = ? AND constraint_type = ?', [ $this->table, 'PRIMARY KEY' ]);
				if($tmp) {
					$res['primary_key'] = $this->db->all('SELECT column_name FROM information_schema.key_column_usage WHERE table_name = ? AND constraint_name = ?', [ $this->table, $tmp ]);
				}
				break;
			default:
				throw new ORMException('Driver is not supported: ' . $this->db->driver(), 500);
		}
		return $res;
	}

	public function getDatabase() {
		return $this->db;
	}
	public function getTableName() {
		return $this->table;
	}
	public function getDefinition() {
		return $this->definition;
	}
	public function getIndexed() {
		return $this->definition['indexed'];
	}
	public function getSearchable() {
		$temp = [];
		if(isset($this->definition['like']) && is_array($this->definition['like'])) {
			foreach($this->definition['like'] as $c) {
				if(in_array($c, $this->definition['columns'])) {
					$temp[] = $c;
				}
			}
		}
		return $temp;
	}
	public function getColumns() {
		return $this->definition['columns'];
	}
	public function getPrimaryKey() {
		return $this->definition['primary_key'];
	}
	public function &getRelations() {
		return $this->relations;
	}
	public function getRelationKeys() {
		return array_keys($this->relations);
	}


	// relations
	protected function getRelatedTable($to_table) {
		if(!($to_table instanceof Table)) {
			if(!is_array($to_table)) {
				$to_table = [ 'table' => $to_table ];
			}
			if(!isset($to_table['definition'])) {
				$to_table['definition'] = null;
			}
			$to_table = new Table($this->getDatabase(), $to_table['table'], $to_table['definition']);
		}
		return $to_table;
	}
	public function addAdvancedRelation($to_table, $name, array $keymap, $many = true, $pivot = null, array $pivot_keymap = []) {
		if(!count($keymap)) {
			throw new ORMException('No linking fields specified');
		}
		$to_table = $this->getRelatedTable($to_table);
		$this->relations[$name] = [
			'name'         => $name,
			'table'        => $to_table,
			'keymap'       => $keymap,
			'many'         => (bool)$many,
			'pivot'        => $pivot,
			'pivot_keymap' => $pivot_keymap
		];
		return $this;
	}
	public function hasOne($to_table, $name = null, $to_table_column = null) {
		$to_table = $this->getRelatedTable($to_table);
		$columns = $to_table->getColumns();

		$keymap = [];
		if(!isset($to_table_column)) {
			$to_table_column = [];
		}
		if(!is_array($to_table_column)) {
			$to_table_column = [ $to_table_column ];
		}
		foreach($this->getPrimaryKey() as $k => $pk_field) {
			$key = null;
			if(isset($to_table_column[$pk_field])) {
				$key = $to_table_column[$pk_field];
			}
			else if(isset($to_table_column[$k])) {
				$key = $to_table_column[$k];
			}
			else {
				$key = $this->getTableName() . '_' . $pk_field;
			}
			if(!in_array($key, $columns)) {
				throw new ORMException('Missing foreign key mapping');
			}
			$keymap[$pk_field] = $key;
		}

		if(!isset($name)) {
			$name = $to_table->getTableName() . '_' . implode('_', array_keys($keymap));
		}
		$this->relations[$name] = [
			'name'         => $name,
			'table'        => $to_table,
			'keymap'       => $keymap,
			'many'         => false,
			'pivot'        => null,
			'pivot_keymap' => []
		];
		return $this;
	}
	public function hasMany($to_table, $name = null, $to_table_column = null) {
		$to_table = $this->getRelatedTable($to_table);
		$columns = $to_table->getColumns();

		$keymap = [];
		if(!isset($to_table_column)) {
			$to_table_column = [];
		}
		if(!is_array($to_table_column)) {
			$to_table_column = [ $to_table_column ];
		}
		foreach($this->getPrimaryKey() as $k => $pk_field) {
			$key = null;
			if(isset($to_table_column[$pk_field])) {
				$key = $to_table_column[$pk_field];
			}
			else if(isset($to_table_column[$k])) {
				$key = $to_table_column[$k];
			}
			else {
				$key = $this->getTableName() . '_' . $pk_field;
			}
			if(!in_array($key, $columns)) {
				throw new ORMException('Missing foreign key mapping');
			}
			$keymap[$pk_field] = $key;
		}

		if(!isset($name)) {
			$name = $to_table->getTableName() . '_' . implode('_', array_keys($keymap));
		}
		$this->relations[$name] = [
			'name'         => $name,
			'table'        => $to_table,
			'keymap'       => $keymap,
			'many'         => true,
			'pivot'        => null,
			'pivot_keymap' => []
		];
		return $this;
	}
	public function belongsTo($to_table, $name = null, $local_column = null) {
		$to_table = $this->getRelatedTable($to_table);
		$columns = $this->getColumns();

		$keymap = [];
		if(!isset($local_column)) {
			$local_column = [];
		}
		if(!is_array($local_column)) {
			$local_column = [ $local_column ];
		}
		foreach($to_table->getPrimaryKey() as $k => $pk_field) {
			$key = null;
			if(isset($local_column[$pk_field])) {
				$key = $local_column[$pk_field];
			}
			else if(isset($local_column[$k])) {
				$key = $local_column[$k];
			}
			else {
				$key = $to_table->getTableName() . '_' . $pk_field;
			}
			if(!in_array($key, $columns)) {
				throw new ORMException('Missing foreign key mapping');
			}
			$keymap[$key] = $pk_field;
		}

		if(!isset($name)) {
			$name = $to_table->getTableName() . '_' . implode('_', array_keys($keymap));
		}
		$this->relations[$name] = [
			'name'         => $name,
			'table'        => $to_table,
			'keymap'       => $keymap,
			'many'         => false,
			'pivot'        => null,
			'pivot_keymap' => []
		];
		return $this;
	}
	public function manyToMany($to_table, $pivot, $name = null, $to_table_column = null, $local_column = null) {
		$to_table = $this->getRelatedTable($to_table);
		$pt_table = $this->getRelatedTable($pivot);

		$local_columns = $this->getColumns();
		$pivot_columns = $pt_table->getColumns();
		$related_columns = $to_table->getColumns();

		$keymap = [];
		if(!isset($to_table_column)) {
			$to_table_column = [];
		}
		if(!is_array($to_table_column)) {
			$to_table_column = [ $to_table_column ];
		}
		foreach($this->getPrimaryKey() as $k => $pk_field) {
			$key = null;
			if(isset($to_table_column[$pk_field])) {
				$key = $to_table_column[$pk_field];
			}
			else if(isset($to_table_column[$k])) {
				$key = $to_table_column[$k];
			}
			else {
				$key = $this->getTableName() . '_' . $pk_field;
			}
			if(!in_array($key, $pivot_columns)) {
				throw new ORMException('Missing foreign key mapping');
			}
			$keymap[$pk_field] = $key;
		}

		$pivot_keymap = [];
		if(!isset($local_column)) {
			$local_column = [];
		}
		if(!is_array($local_column)) {
			$local_column = [ $local_column ];
		}
		foreach($to_table->getPrimaryKey() as $k => $pk_field) {
			$key = null;
			if(isset($local_column[$pk_field])) {
				$key = $local_column[$pk_field];
			}
			else if(isset($local_column[$k])) {
				$key = $local_column[$k];
			}
			else {
				$key = $to_table->getTableName() . '_' . $pk_field;
			}
			if(!in_array($key, $pivot_columns)) {
				throw new ORMException('Missing foreign key mapping');
			}
			$pivot_keymap[$key] = $pk_field;
		}

		if(!isset($name)) {
			$name = $to_table->getTableName() . '_' . implode('_', array_keys($keymap));
		}
		$this->relations[$name] = [
			'name'         => $name,
			'table'        => $to_table,
			'keymap'       => $keymap,
			'many'         => true,
			'pivot'        => $pivot,
			'pivot_keymap' => $pivot_keymap
		];
		return $this;
	}

	public function search($term) {
		if(count($this->getIndexed()) && is_string($term) && strlen($term) >= 4) {
			$this->filter('MATCH ('.implode(', ', $this->getIndexed()).') AGAINST (?)', [$term]);
		}
		$like = $this->getSearchable();
		if(count($like) && is_string($term) && strlen($term)) {
			$term = '%' . str_replace(['%','_'], ['\\%', '\\_'], $term) . '%';
			$sql = '';
			$par = [];
			foreach($like as $fd) {
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
		$this->new = [];
		$this->del = [];
		return $this;
	}

	public function count() {
		$sql = 'SELECT COUNT(DISTINCT t.' . implode(', t.', $this->getPrimaryKey()) . ') FROM ' . $this->getTableName() . ' AS t ';
		foreach($this->joined as $k => $v) {
			if($this->relations[$k]['pivot']) {
				$sql .= 'LEFT JOIN ' . $this->relations[$k]['pivot'] . ' AS '.$k.'_pivot ON ';
				$tmp = [];
				foreach($this->relations[$k]['keymap'] as $kk => $vv) {
					$tmp[] = 't.' . $kk . ' = ' . $k . '_pivot.' . $vv . ' ';
				}
				$sql .= implode(' AND ', $tmp);

				$sql .= 'LEFT JOIN ' . $this->relations[$k]['table']->getTableName() . ' AS '.$k.' ON ';
				$tmp = [];
				foreach($this->relations[$k]['pivot_keymap'] as $kk => $vv) {
					$tmp[] = $k . '.' . $vv . ' = ' . $k . '_pivot.' . $kk . ' ';
				}
				$sql .= implode(' AND ', $tmp);
			}
			else {
				$sql .= $v . ' JOIN ' . $this->relations[$k]['table']->getTableName() . ' AS '.$k.' ON ';
				$tmp = [];
				foreach($this->relations[$k]['keymap'] as $kk => $vv) {
					$tmp[] = 't.' . $kk . ' = ' . $k . '.' . $vv . ' ';
				}
				$sql .= implode(' AND ', $tmp);
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
					if(in_array($v, $this->getColumns()) || $v === '*') {
						$temp[] = 't.' . $v;
					}
				}
				else {
					if(preg_match('(^[a-z_0-9]+\.[a-z_0-9*]+$)i', $v)) {
						$v = explode('.', $v, 2);
						if(isset($this->relations[$v[0]]) && ($v[1] === '*' || in_array($v[1], $this->relations[$v[0]]['table']->getColumns()))) {
							$this->joined[$v[0]] = 'LEFT';
							$temp[] = $v[1] === '*' ? implode('.', $v) : implode('.', $v) . ' AS ' . implode('___', $v);
						}
					}
				}
			}
			$fields = $temp;
		}
		if(!$fields || !count($fields)) {
			$fields = [];
			foreach($this->getColumns() as $c) {
				$fields[] = 't.'.$c;
			}
		}
		$sql = 'SELECT ' . implode(', ', $fields). ' FROM ' . $this->table . ' AS t ';
		
		foreach($this->joined as $k => $v) {
			if($this->relations[$k]['pivot']) {
				$sql .= 'LEFT JOIN ' . $this->relations[$k]['pivot'] . ' AS '.$k.'_pivot ON ';
				$tmp = [];
				foreach($this->relations[$k]['keymap'] as $kk => $vv) {
					$tmp[] = 't.' . $kk . ' = ' . $k . '_pivot.' . $vv . ' ';
				}
				$sql .= implode(' AND ', $tmp);

				$sql .= 'LEFT JOIN ' . $this->relations[$k]['table']->getTableName() . ' AS '.$k.' ON ';
				$tmp = [];
				foreach($this->relations[$k]['pivot_keymap'] as $kk => $vv) {
					$tmp[] = $k . '.' . $vv . ' = ' . $k . '_pivot.' . $kk . ' ';
				}
				$sql .= implode(' AND ', $tmp);
			}
			else {
				$sql .= $v . ' JOIN ' . $this->relations[$k]['table']->getTableName() . ' AS '.$k.' ON ';
				$tmp = [];
				foreach($this->relations[$k]['keymap'] as $kk => $vv) {
					$tmp[] = 't.' . $kk . ' = ' . $k . '.' . $vv . ' ';
				}
				$sql .= implode(' AND ', $tmp);
			}
		}

		$sql .= 'WHERE ' . implode(' AND ', $this->filter) . ' ';
		if(count($this->joined)) {
			$sql .= 'GROUP BY t.' . implode(', t.', $this->getPrimaryKey()) . ' ';
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

	public function read($settings = null) {
		// TODO - maybe single() method? :
		//if(isset($settings) && is_numeric($settings)) {
		//	return $this->filter($this->pk . ' = ' . (int)$settings)->select();
		//}
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
			if(in_array($settings['o'], $this->getColumns())) {
				$order = $settings['o'] . ' ' . (isset($settings['d']) && (int)$settings['d'] ? 'DESC' : 'ASC');
			}
			if(strpos($settings['o'], '.')) {
				$temp = explode('.', $settings['o'], 2);
				if(isset($this->relations[$temp[0]]) && in_array($temp[1], $this->relations[$temp[0]]['table']->getColumns())) {
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
		foreach($this->getColumns() as $column) {
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
			if(isset($this->relations[$temp[0]]) && in_array($temp[1], $this->relations[$temp[0]]['table']->getColumns())) {
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
	public function toArray($full = true) {
		$temp = [];
		foreach($this as $k => $v) {
			$temp[$k] = $v->toArray($full);
		}
		return $temp;
	}
	public function __debugInfo() {
		return $this->toArray();
	}
	public function jsonSerialize() {
		return $this->toArray();
	}

	// MODIFIERS
	public function create(array $data) {
		$temp = new TableRow(clone $this, [], true);
		$temp->fromArray($data);
		return $this->new[] = $temp;
	}
	public function save(array $data = [], $delete = true) {
		$wasInTransaction = $this->db->isTransaction();
		if(!$wasInTransaction) {
			$this->db->begin();
		}
		try {
			$ret = [];
			$ids = null;
			foreach($this->new as $temp) {
				foreach($data as $k => $v) {
					$temp->{$k} = $v;
				}
				$ids = $temp->save();
				$ret[md5(serialize($ids))] = array_merge($temp->toArray(false), $ids);
			}
			foreach($this as $temp) {
				foreach($data as $k => $v) {
					$temp->{$k} = $v;
				}
				$ids = $temp->save();
				$ret[md5(serialize($ids))] = array_merge($temp->toArray(false), $ids);
			}
			foreach($this->del as $temp) {
				$ids = $delete ? $temp->delete() : $temp->getID();
				unset($ret[md5(serialize($ids))]);
			}
			if(!$wasInTransaction) {
				$this->db->commit();
			}
			return $ret;
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