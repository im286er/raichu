<?php
namespace raichu\api;

use raichu\Raichu as raichu;

trait TraitAPI
{
	protected final function requireUser($permission = null, $class = null) {
		raichu::user_login();
		if(!raichu::user_is_valid() && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			raichu::user_login(['username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']]);
		}
		if(!raichu::user_is_valid() && raichu::input_header('Authorization')) {
			$temp = explode(' ',raichu::input_header('Authorization'));
			if(strtolower($temp[0]) === 'basic') {
				$temp[1] = base64_decode($temp[1]);
				$temp[1] = explode(':', $temp[1], 2);
				raichu::user_login(['username' => $temp[1][0], 'password' => $temp[1][1]]);
			}
			if(strtolower($temp[0]) === 'token' || strtolower($temp[0]) === 'bearer' || strtolower($temp[0]) === 'oauth') {
				raichu::user_login(['token' => $temp[1]]);
			}
		}
		if(!raichu::user_is_valid()) {
			throw new APIException('Invalid user', 401);
		}
		if($permission && !$this->has_permission($class ? $class : basename(str_replace('\\', '/', get_class($this))), $permission)) {
			throw new APIException('Invalid action', 403);
		}
	}
	protected final function requireAdmin($permission = null, $class = null) {
		$this->require_user($permission, $class);
		if(!raichu::user("admin")) {
			throw new APIException('Invalid user', 403);
		}
	}
	protected final function hasPermission($class, $method = null) {
		$permissions = @json_decode(raichu::user("permissions"), true);
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