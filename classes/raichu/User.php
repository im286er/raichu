<?php
namespace raichu;

use vakata\user\authentication\AuthenticationInterface;
use vakata\user\UserInterface;

class User implements UserInterface
{
	protected $user = null;
	protected $meta = [];

	public function __construct(UserInterface $user) {
		$this->user = $user;
	}

	public function valid() {
		return $this->user->valid();
	}
	public function get($key) {
		$temp = $this->user->get($key);
		if($temp === null && is_array($this->meta) && isset($this->meta[$key])) {
			$temp = $this->meta[$key];
		}
		return $temp;
	}
	public function __get($key) {
		return $this->get($key);
	}

	public function login($data = null) {
		$res = $this->user->login($data);
		if($res && $this->user->meta !== null) {
			$this->meta = @json_decode($this->user->meta, true);
		}
		return $res;
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