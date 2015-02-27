<?php
namespace raichu;

trait TraitPermission
{
	protected final function requireUser() {
		raichu::user()->login(raichu::request()->getAuthorization());
		if(!raichu::user()->valid()) {
			throw new \Exception('Невалиден потребител', 401);
		}
	}
	protected final function requireAdmin() {
		$this->requireUser();
		if(!raichu::user()->admin) {
			throw new \Exception('Недостатъчно ниво на достъп', 403);
		}
	}
	protected final function requirePermission($permission) {
		if($this->requireUser() && !$this->hasPermission($permission)) {
			throw new \Exception('Действието е забранено за потребителя', 403);
		}
	}
	protected final function isUser() {
		return raichu::user()->valid();
	}
	protected final function isAdmin() {
		return raichu::user()->valid() && raichu::user()->admin;
	}
	protected final function hasPermission($permission) {
		$permissions = @json_decode(raichu::user()->permissions, true);

		return	$permissions && 
				is_array($permissions) && 
				in_array(strtolower($permission), array_map('strtolower', $permissions));
	}
	protected final function requireLocal() {
		if(!isset($_SERVER['REMOTE_ADDR']) || !isset($_SERVER['SERVER_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', $_SERVER['SERVER_ADDR']])) {
			throw new \Exception('Невалидно отдалечено извикване', 403);
		}
	}
	protected final function requireParams($params, $required) {
		if(!is_array($required)) { $required = [$required]; }
		foreach($required as $key) {
			if(!isset($params[$key])) {
				throw new \Exception('Липсва параметър - ' . htmlspecialchars($key), 400);
			}
		}
	}
}

/*
protected final function cacheable($key, callable $value = null, $namespace = 'api', $time = 14400) {
	raichu::cache_getSet($key, $value, $namespace, $time);
}
public function sample_method($data) {
	$this->require_user(__FUNCTION__);
	return $this->cacheable(sha1(get_class($this).'/'.__FUNCTION__.'/'.serialize($data)), function () use ($data) {
		return $this->_get_one($data);
	}, (int)$data['obs']);
}
*/
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