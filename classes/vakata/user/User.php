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