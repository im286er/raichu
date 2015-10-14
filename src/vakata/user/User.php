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
		if (strpos($key, 'is') === 0) {
			return $this->get(strtolower(substr($key, 2))) == (isset($args[0]) ? $args[0] : true);
		}
		if (strpos($key, 'has') === 0 && isset($args[0])) {
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

	public static function userAgent() {
		return isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}
	public static function ipAddress() {
		$ip = '0.0.0.0';
		// TODO: check if remote_addr is a cloudflare one and only then read the connecting ip
		// https://www.cloudflare.com/ips-v4
		// https://www.cloudflare.com/ips-v6
		if (false && isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
			$ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}
		elseif (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if (strpos($ip, ',') !== false) {
			$ip = @end(explode(',', $ip));
		}
		$ip = trim($ip);
		if (false === ($ip = filter_var($ip, FILTER_VALIDATE_IP))) {
			$ip = '0.0.0.0';
		}
		return $ip;
	}
}
