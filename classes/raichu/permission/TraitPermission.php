<?php
namespace raichu\permission;

use \raichu\Raichu as raichu;

trait TraitPermission
{
	protected final function requireUser() {
		if(!raichu::user()->valid()) {
			raichu::user()->login(raichu::request()->getAuthorization());
			if(!raichu::user()->valid()) {
				throw new PermissionException('Невалиден потребител', 401);
			}
		}
	}
	protected final function requireAdmin() {
		$this->requireUser();
		if(!raichu::user()->isAdmin()) {
			throw new PermissionException('Недостатъчно ниво на достъп', 403);
		}
	}
	protected final function requirePermission($permission) {
		$this->requireUser();
		if(!$this->hasPermission($permission)) {
			throw new PermissionException('Действието е забранено за потребителя', 403);
		}
	}
	protected final function isUser() {
		return raichu::user()->valid();
	}
	protected final function isAdmin() {
		return raichu::user()->isAdmin();
	}
	protected final function hasPermission($permission) {
		return raichu::user()->hasPermission($permission);
	}
	protected final function requireLocal() {
		if(!isset($_SERVER['REMOTE_ADDR']) || !isset($_SERVER['SERVER_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', $_SERVER['SERVER_ADDR']])) {
			throw new PermissionException('Невалидно отдалечено извикване', 403);
		}
	}
	protected final function requireParams($params, $required) {
		if(!is_array($required)) { $required = [$required]; }
		foreach($required as $key) {
			if(!isset($params[$key])) {
				throw new ParamsException('Липсва параметър - ' . htmlspecialchars($key), 400);
			}
		}
	}
	protected final function requirePost() {
		if(!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','PATCH','DELETE'])) {
			throw new PostRequiredException('Некоректно обръщение', 405);
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