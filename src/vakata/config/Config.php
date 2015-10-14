<?php
namespace vakata\config;

class Config
{
	protected $config = [];

	public function __construct(array $config = []) {
		$this->config = $config;
	}

	public function get($key, $default = null) {
		$key = array_filter(explode('.', $key));
		$tmp = $this->config;
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
		$tmp = &$this->config;
		foreach ($key as $k) {
			if (!isset($tmp[$k])) {
				$tmp[$k] = [];
			}
			$tmp = &$tmp[$k];
		}
		return $tmp = is_array($tmp) && is_array($value) && count($tmp) ? array_merge($tmp, $value) : $value;
	}
}
