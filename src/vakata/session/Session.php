<?php
namespace vakata\session;

use \SessionHandlerInterface;

class Session
{
	public function __construct(SessionHandlerInterface $handler = null, $name = null, $location = null) {
		if ($name) {
			@ini_set('session.name', $name);
		}
		if (!$handler && $location) {
			session_save_path($session['location']);
		}
		if ($handler) {
			@ini_set('session.save_handler', 'user');
			session_set_save_handler($handler);
			register_shutdown_function('session_write_close');
		}
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	}
	public function get($key, $default = null) {
		$key = array_filter(explode('.', $key));
		$tmp = $_SESSION;
		foreach ($key as $k) {
			if (!isset($tmp[$k])) {
				return $default;
			}
			$tmp = $tmp[$k];
		}
		return $tmp;
	}
	public function set($key, $value) {
		$key = array_filter(explode('.', $key));
		$tmp = &$_SESSION;
		foreach ($key as $k) {
			if (!isset($tmp[$k])) {
				$tmp[$k] = [];
			}
			$tmp = &$tmp[$k];
		}
		return $tmp = is_array($tmp) && is_array($value) && count($tmp) ? array_merge($tmp, $value) : $value;
	}
	public function del($key) {
		$key = explode('.', $key);
		$tmp = &$_SESSION;
		foreach ($key as $k) {
			if (!isset($tmp[$k])) {
				return false;
			}
			$tmp = &$tmp[$k];
		}
		unset($tmp);
		return true;
	}
}
