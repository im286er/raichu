<?php
namespace demoapp;

use vakata\database\orm\Table;
use vakata\database\DatabaseInterface;

class Users extends Table
{
	use \raichu\api\TraitAPI;

	public function __construct(DatabaseInterface $db) {
		/*
		$this->requireUser(); 						// => require user or admin
		$this->requirePermission(__FUNCTION__);		// => check permissions
		*/

		// => filter readable fields (passing to the constructor below)
		// => call parent with all params (so that DB is not checked for structure on each creation)
		parent::__construct($db, 'users', 'id', [ 'name', 'mail', 'last_seen', 'last_login', 'created', 'disabled', 'match_session' ], ['name', 'mail']);

		// => define relations (for the first param instead of strings Table instances can be used)
		$this->hasOne('users_authentication', 'user_id', 'authentication');
		$this->hasMany('users_authentication', 'user_id', 'authentication2');
	}

	public function read($filter = null, $params = null, $order = null, $limit = null, $offset = null, $is_single = false) {
		/*
		$this->requireUser(); 						// => require user or admin
		$this->requirePermission(__FUNCTION__);		// => check permissions

		// => filter per user
		$filter  = $filter ? ' ('.$filter.') AND ' : '';
		$filter .= ' (field = ?) ';
		if(!$params) { $params = []; }
		$params[] = 1;
		*/

		return parent::read($filter, $params, $order, $limit, $offset, $is_single);
	}

	public function create(array $data) {
		/*
		$this->requireUser();									// => require user or admin
		$this->requirePermission(__FUNCTION__);					// => check permissions
		unset($data['key']); 									// => limit current user from specifying certain fields
		$data['updated'] = date('Y-m-d H:i:s');					// => add special fields
		$this->getDatabase()->query('INSERT INTO versions');	// => versioning
		*/
		
		return parent::create($data);
	}
	public function update(array $data) {
		/*
		$this->requireUser();									// => require user or admin
		$this->requirePermission(__FUNCTION__);					// => check permissions
		unset($data['key']);									// => limit current user from specifying certain fields
		$data['updated'] = date('Y-m-d H:i:s');					// => add special fields
		$this->getDatabase()->query('INSERT INTO versions');	// => versioning
		*/
		
		return parent::create($data);
	}
	public function delete(array $data) {
		/*
		$this->requireUser();									// => require user or admin
		$this->requirePermission(__FUNCTION__);					// => check permissions
		$this->getDatabase()->query('INSERT INTO versions');	// => versioning
		*/
		
		return parent::delete($data);
	}

	// RPC callable function
	public function demo() {
		throw new \Exception('asdf');
	}
}