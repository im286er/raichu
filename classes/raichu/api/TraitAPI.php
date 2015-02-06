<?php
namespace raichu\api;

use raichu\Raichu as raichu;

trait TraitAPI
{
	protected final function requireUser() {
		raichu::user()->login(raichu::request()->getAuthorization());
		if(!raichu::user()->valid()) {
			throw new APIException('Invalid user', 401);
		}
	}
	protected final function requireAdmin() {
		$this->requireUser();
		if(!raichu::user()->admin) {
			throw new APIException('Invalid user', 403);
		}
	}
	protected final function requirePermission($permission, $class = null) {
		if(!$this->hasPermission($class ? $class : basename(str_replace('\\', '/', get_class($this))), $permission)) {
			throw new APIException('Invalid action', 403);
		}
	}
	protected final function hasPermission($class, $method = null) {
		$permissions = @json_decode(raichu::user()->permissions, true);
		return	$permissions && 
				is_array($permissions) && 
				(
					(!isset($method) && (isset($permissions[$class]) || in_array($class, $permissions))) ||
					( isset($method) &&  isset($permissions[$class]) && (in_array($method, $permissions[$class]) || isset($permissions[$class][$method])))
				);
	}
	protected final function requireLocal() {
		if(!isset($_SERVER['REMOTE_ADDR']) || !isset($_SERVER['SERVER_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', $_SERVER['SERVER_ADDR']])) {
			throw new APIException('Invalid caller', 403);
		}
	}
	protected final function requireParams($params, $required) {
		if(!is_array($required)) { $required = [$required]; }
		foreach($required as $key) {
			if(!isset($params[$key])) {
				throw new APIException('Invalid arguments - missing ' . htmlspecialchars($key), 400);
			}
		}
	}
	protected final function cacheable($key, callable $value = null, $namespace = 'api', $time = 14400) {
		raichu::cache_getSet($key, $value, $namespace, $time);
	}
	/*
	public function sample_method($data) {
		$this->require_user(__FUNCTION__);
		return $this->cacheable(sha1(get_class($this).'/'.__FUNCTION__.'/'.serialize($data)), function () use ($data) {
			return $this->_get_one($data);
		}, (int)$data['obs']);
	}
	*/
}

/*
	protected final function hasRead($class, $field) {
		$permissions = @json_decode(raichu::user("permissions"), true);
		return	$permissions && 
				is_array($permissions) && 
				isset($permissions[$class]) &&
				isset($permissions[$class]['fields_r']) &&
				isset($permissions[$class]['fields_r'][$field]);
	}
	protected final function hasWrite($class, $field) {
		$permissions = @json_decode(raichu::user("permissions"), true);
		return	$permissions && 
				is_array($permissions) && 
				isset($permissions[$class]) &&
				isset($permissions[$class]['fields_w']) &&
				isset($permissions[$class]['fields_w'][$field]);
	}
}
*/