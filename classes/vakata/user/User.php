<?php
namespace vakata\user;

use vakata\user\authentication\AuthenticationInterface;

class User implements UserInterface
{
	protected $data = [];
	protected $auth = null;

	public function __construct(AuthenticationInterface $auth) {
		$this->auth = $auth;
	}

	public function valid() {
		return isset($this->data['id']) && $this->data['id'] !== null;
	}
	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}
	public function __get($key) {
		return $this->get($key);
	}

	public function __call($key, $args) {
		if(strpos($key, 'is') === 0) {
			return $this->get(strtolower(substr($key, 2))) == (isset($args[0]) ? $args[0] : true);
		}
		if(strpos($key, 'has') === 0 && isset($args[0])) {
			return is_array($this->get(strtolower(substr($key, 3)))) && in_array($args[0], $this->get(strtolower(substr($key, 3))));
		}
	}

	public function login($data = null) {
		$this->data = $this->auth->authenticate($data);
		return $this->valid() ? $this->data : null;
	}
	public function logout() {
		$this->auth->clear();
		return $this->data = [];
	}
	public function restore($data = null) {
		return $this->auth->restore($data);
	}
}