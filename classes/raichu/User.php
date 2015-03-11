<?php
namespace raichu;

use vakata\user\authentication\AuthenticationInterface;
use vakata\user\UserInterface;

class User implements UserInterface
{
	protected $user = null;

	public function __construct(UserInterface $user) {
		$this->user = $user;
	}

	public function valid() {
		return $this->user->valid();
	}
	public function get($key) {
		return $this->user->get($key);
	}
	public function __get($key) {
		return $this->get($key);
	}

	public function login($data = null) {
		return $this->user->login($data);
	}
	public function logout() {
		return $this->user->logout();
	}
	public function restore($data = null) {
		return $this->user->restore($data);
	}

	public function isAdmin() {
		return $this->valid() && $this->admin !== null;
	}
	public function hasPermission($permission) {
		return is_array($this->permissions) && in_array(strtolower($permission), $this->permissions);
	}
	public function inGroup($group) {
		return is_array($this->groups) && isset($this->groups[$group]);
	}
	public function hasPrimaryGroup($group) {
		return $this->group === $group;
	}
}