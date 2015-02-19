<?php
namespace raichu\module;

use vakata\database\orm\Table;
use vakata\database\DatabaseInterface;

abstract class AbstractModule extends Table
{
	use TraitPermission;

	protected $name    = null;
	protected $config  = null;
	protected $version = null;

	public function __construct(DatabaseInterface $db, array $config = [], Versions $v = null) {
		$config = array_merge_recursive([
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
		
		if($this->config['module']['requireUser']) {
			$this->requireUser();
		}
		if($this->config['module']['requireAdmin']) {
			$this->requireAdmin();
		}

		$r = [];
		$i = [];
		foreach($this->config['fields'] as $field => $data) {
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

		$this->version = $config['module']['versions'] && $v ? $v : function ($o, $i, $d = null) {};

		parent::__construct($db, $this->config['module']['table'], $this->config['module']['pk'], $r, $i);

		foreach($this->config['relations'] as $field => $options) {
			$this->{$options['rtype']}(strpos($options['table'], '\\') ? raichu::instance($options['table']) : $options['table'], $options['field'], $field);
		}
	}

	public function read($filter = null, $params = null, $order = null, $limit = null, $offset = null, $is_single = false) {
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

		return parent::read($filter, $params, $order, $limit, $offset, $is_single);
	}

	public function create(array $data) {
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
				switch($data['special']) {
					case 'created':
					case 'updated':
						$w[] = $field;
						$data[$field] = date('Y-m-d H:i:s');
						break;
					case 'author':
					case 'editor':
						$w[] = $field;
						$data[$field] = \raichu\Raichu::user()->id;
				}
			}
		}
		foreach($data as $k => $v) {
			if(!in_array($k, $w)) {
				unset($data[$k]);
			}
		}

		$id = parent::create($data);
		$this->version($this, $id, $data);
		return $id;
	}
	public function update(array $data) {
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
		foreach($this->config['fields'] as $field => $definition) {
			if($definition['write'] && (!$definition['check_user_write'] || $this->hasPermission($this->name.'.write.'.$field))) {
				$w[] = $field;
			}
			if($definition['special']) {
				switch($data['special']) {
					case 'updated':
						$w[] = $field;
						$data[$field] = date('Y-m-d H:i:s');
						break;
					case 'editor':
						$w[] = $field;
						$data[$field] = \raichu\Raichu::user()->id;
				}
			}
		}
		foreach($data as $k => $v) {
			if(!in_array($k, $w)) {
				unset($data[$k]);
			}
		}

		$id = parent::update($data);
		$this->version($this, $id, $data);
		return $id;
	}
	public function delete(array $data) {
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
		$this->version($this, $id, $data);
		return $id;
	}
}