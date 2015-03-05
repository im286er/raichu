<?php
namespace raichu;

interface PermissionsInterface
{
	/**
	 * get an array of all permissions specified by the module
	 * @method permissions
	 * @return array      an array of strings, where each string is a unique permission required by the module
	 */
	public function permissions();
}