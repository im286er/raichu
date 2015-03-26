<?php
namespace raichu;

use vakata\database\orm\Table;
use vakata\database\DatabaseInterface;
use raichu\permission\PermissionsInterface;
use raichu\permission\TraitPermission;

class CRUDModule extends Table implements PermissionsInterface
{
	use TraitPermission;

	protected $name     = null;
	protected $config   = null;
	protected $versions = '';

	public function __construct(DatabaseInterface $db, array $config = [], $versions = 'versions') {
		$config = array_replace_recursive([
				'module' => [
					'name'				=> basename(str_replace('\\', '/', get_class($this))),
					'table'				=> basename(str_replace('\\', '/', get_class($this))),
					'pk'				=> null,
					'requireUser'		=> false,
					'requireAdmin'		=> false,
					'requirePermission'	=> false,
					'versions'			=> false
				],
				'fields' => [
					/*
					field => [
						read => true
						write => true,
						index => true,
						special => false, // author, editor, created, updated
						check_user_read => false,
						check_user_write => false
					]
					*/
				],
				'operations' => [
					'create' => [
						'disabled'			=> false,
						'requireUser'		=> false,
						'requireAdmin'		=> false,
						'requirePermission'	=> false
					],
					'read' => [
						'disabled'			=> false,
						'requireUser'		=> false,
						'requireAdmin'		=> false,
						'requirePermission'	=> false
					],
					'update' => [
						'disabled'			=> false,
						'requireUser'		=> false,
						'requireAdmin'		=> false,
						'requirePermission'	=> false
					],
					'delete' => [
						'disabled'			=> false,
						'requireUser'		=> false,
						'requireAdmin'		=> false,
						'requirePermission'	=> false
					],
				],
				'relations' => [
					/*
					fieldname => [
						rtype => '',   // hasOne, hasMany, belongsTo
						field => '',
						table => '',   // tablename
						class => null, // configTable
						param => ''    // tablename.json location
					]
					*/
				]
			], $config);
		$this->name   = $config['module']['name'];
		$this->config = $config;
		$this->versions = $versions;
		
		if($this->config['module']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['module']['requireAdmin']) {
			$this->requireAdmin();
		}
		if($this->config['module']['requirePermission']) {
			$this->requirePermission($this->name);
		}

		$r = [];
		$i = [];
		foreach($this->config['fields'] as $field => $data) {
			$this->config['fields'][$field] = $data = array_merge([
				'type'             => 'text',
				'read'             => true,
				'write'            => true,
				'index'            => false,
				'special'          => false,
				'pattern'          => false,
				'check_user_read'  => false,
				'check_user_write' => false
			], $data);
			if($data['read'] && (!$data['check_user_read'] || $this->hasPermission($this->name.'.read.'.$field))) {
				$r[] = $field;
			}
			if($data['index']) {
				$i[] = $field;
			}
		}
		if(count($r) && $this->config['module']['pk'] !== null) {
			$r[] = $this->config['module']['pk'];
		}
		if(!count($r)) {
			$r = null;
		}
		if(!count($i)) {
			$i = null;
		}
		parent::__construct($db, $this->config['module']['table'], $this->config['module']['pk'], $r, $i);
		foreach($this->config['relations'] as $field => $options) {
			$this->{$options['rtype']}(strpos($options['table'], '\\') ? raichu::instance($options['table']) : $options['table'], $options['field'], $field);
		}
		foreach($this->config['operations'] as $op => $conf) {
			$this->config['operations'][$op] = array_merge([
				'disabled'          => false,
				'requireUser'       => false,
				'requireAdmin'      => false,
				'requirePermission' => false
			], $conf);
		}
	}

	protected function version($id, $data = null) {
		if(!$this->config['module']['versions']) {
			return false;
		}
		$v = (int)$this->db->one('SELECT MAX(version) FROM '.$this->versions.' WHERE table_nm = ? AND table_pk = ?', [$this->config['module']['table'], $id]);
		$o = $this->db->one('SELECT * FROM '.$this->config['module']['table'].' WHERE '.$this->config['module']['pk'].' = ?', [$id]);
		return $this->db->query(
				'INSERT INTO '.$this->versions.' (table_nm, table_pk, version, data, object) VALUES(?,?,?,?,?)', 
				[$this->config['module']['table'], $id, ++$v, json_encode($data), json_encode($o)]
			)->affected() > 0;
	}

	public function read($filter = null, $params = null, $order = null, $limit = null, $offset = null, $is_single = false, $as_array = true) {
		if($this->config['operations']['read']['disabled']) {
			throw new \Exception('Method not allowed', 405);
		}
		if($this->config['operations']['read']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['operations']['read']['requireAdmin']) {
			$this->requireAdmin();
		}
		if($this->config['operations']['read']['requirePermission']) {
			$this->requirePermission($this->name . '.' . __FUNCTION__);
		}

		// filter per user by passing data from the parent class

		return parent::read($filter, $params, $order, $limit, $offset, $is_single, $as_array);
	}

	public function create(array $data) {
		$this->requirePost();

		if($this->config['operations']['create']['disabled']) {
			throw new \Exception('Method not allowed', 405);
		}
		if($this->config['operations']['create']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['operations']['create']['requireAdmin']) {
			$this->requireAdmin();
		}
		if($this->config['operations']['create']['requirePermission']) {
			$this->requirePermission($this->name . '.' . __FUNCTION__);
		}

		$w = [];
		foreach($this->config['fields'] as $field => $definition) {
			if($definition['write'] && (!$definition['check_user_write'] || $this->hasPermission($this->name.'.write.'.$field))) {
				$w[] = $field;
			}
			if($definition['special']) {
				switch($definition['special']) {
					case 'created':
					case 'updated':
						$w[] = $field;
						$data[$field] = date('Y-m-d H:i:s');
						break;
					case 'author':
					case 'editor':
						$w[] = $field;
						$data[$field] = \vakata\raichu\Raichu::user()->id;
				}
			}
			if(is_array($data[$field]) || is_object($data[$field])) {
				$data[$field] = json_encode($data[$field]);
			}
			switch($definition['type']) {
				case 'date':
					$data[$field] = strtotime($data[$field]);
					$data[$field] = $data[$field] === false ? '0000-00-00' : date('Y-m-d', $data[$field]);
					break;
				case 'datetime':
					$data[$field] = strtotime($data[$field]);
					$data[$field] = $data[$field] === false ? '0000-00-00 00:00:00' : date('Y-m-d H:i:s', $data[$field]);
					break;
				default:
					break;
			}
			if($definition['pattern'] && !preg_match('(^'.$definition['pattern'].'$)', $data[$field])) {
				throw new \Exception('Некоректни данни', 406);
			}
		}
		foreach($data as $k => $v) {
			if(!in_array($k, $w)) {
				unset($data[$k]);
			}
		}

		$id = parent::create($data);
		$this->version($id, $data);
		return $id;
	}
	public function update(array $data) {
		$this->requirePost();
		if($this->config['operations']['update']['disabled']) {
			throw new \Exception('Method not allowed', 405);
		}
		if($this->config['operations']['update']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['operations']['update']['requireAdmin']) {
			$this->requireAdmin();
		}
		if($this->config['operations']['update']['requirePermission']) {
			$this->requirePermission($this->name . '.' . __FUNCTION__);
		}

		$w = [];
		$w[] = $this->config['module']['pk'];
		foreach($this->config['fields'] as $field => $definition) {
			if($definition['write'] && (!$definition['check_user_write'] || $this->hasPermission($this->name.'.write.'.$field))) {
				$w[] = $field;
			}
			if($definition['special']) {
				switch($definition['special']) {
					case 'updated':
						$w[] = $field;
						$data[$field] = date('Y-m-d H:i:s');
						break;
					case 'editor':
						$w[] = $field;
						$data[$field] = \vakata\raichu\Raichu::user()->id;
				}
			}
			if(is_array($data[$field]) || is_object($data[$field])) {
				$data[$field] = json_encode($data[$field]);
			}
			switch($definition['type']) {
				case 'date':
					$data[$field] = strtotime($data[$field]);
					$data[$field] = $data[$field] === false ? '0000-00-00' : date('Y-m-d', $data[$field]);
					break;
				case 'datetime':
					$data[$field] = strtotime($data[$field]);
					$data[$field] = $data[$field] === false ? '0000-00-00 00:00:00' : date('Y-m-d H:i:s', $data[$field]);
					break;
				default:
					break;
			}
			if($definition['pattern'] && !preg_match('(^'.$definition['pattern'].'$)', $data[$field])) {
				throw new \Exception('Некоректни данни', 406);
			}
		}
		foreach($data as $k => $v) {
			if(!in_array($k, $w)) {
				unset($data[$k]);
			}
		}

		$id = parent::update($data);
		$this->version($id, $data);
		return $id;
	}
	public function delete(array $data) {
		$this->requirePost();
		if($this->config['operations']['delete']['disabled']) {
			throw new \Exception('Method not allowed', 405);
		}
		if($this->config['operations']['delete']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['operations']['delete']['requireAdmin']) {
			$this->requireAdmin();
		}
		if($this->config['operations']['delete']['requirePermission']) {
			$this->requirePermission($this->name . '.' . __FUNCTION__);
		}

		$id = parent::delete($data);
		$this->version($id, $data);
		return $id;
	}
	public function permissions() {
		$temp = [];
		if($this->config['module']['requirePermission']) {
			$temp[] = $this->name;
		}
		foreach($this->config['operations'] as $o => $op) {
			if(isset($op['requirePermission']) && $op['requirePermission']) {
				$temp[] = $this->name.'.'.$o;
			}
		}
		foreach($this->config['fields'] as $f => $fld) {
			if(isset($fld['check_user_read']) && $fld['check_user_read']) {
				$temp[] = $this->name.'.read.'.$fld;
			}
			if(isset($fld['check_user_write']) && $fld['check_user_write']) {
				$temp[] = $this->name.'.write.'.$fld;
			}
		}
		return $temp;
	}
}