<?php
namespace raichu\api;

use raichu\Raichu as raichu;

abstract class CRUDModule extends AbstractAPI
{
	public function __construct(ModuleDefinition $config) {
	}

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