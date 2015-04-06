<?php
namespace vakata\localization;

class Lang
{
	protected $data = [];
	protected $dflt = null;
	public function add($code, $data, $dflt = false) {
		$this->data[$code] = $data;
		if($dflt) {
			$this->setDefault($code);
		}
		return $this;
	}
	public function setDefault($code) {
		if(isset($this->data[$code])) {
			$this->dflt = $code;
		}
		return $this;
	}
	public function get($key, $count = 0, array $replace = [], $code = null) {
		if(!isset($code)) {
			$code = isset($this->dflt) && isset($this->data[$this->dflt]) ? $this->data[$this->dflt] : array_keys($this->data);
		}
		if(is_string($code)) {
			$code = [$code];
		}
		if(!is_array($code)) {
			return null;
		}
		foreach($code as $c) {
			$tmp = $this->parse($c, $key, $count, $replace);
			if($tmp !== null) {
				return $tmp;
			}
		}
		return $key;
	}
	protected function parse($code, $key, $count = 0, array $replace = []) {
		if(is_string($this->data[$code])) {
			$this->data[$code] = @json_decode(file_get_contents($val), true);
		}
		if(!isset($this->data[$code])) {
			return null;
		}
		$tmp = explode('.', $key);
		$val = $this->data[$code];
		foreach($tmp as $k) {
			if(!isset($val[$k])) {
				return null;
			}
			$val = $val[$k];
		}
		$val = explode('|', $val);
		$val = isset($val[$count]) ? $val[$count] : end($val);
		if(count($replace)) {
			$val = preg_replace_callback('(:[a-z0-9]+)', function ($matches) use ($replace) { return isset($replace[substr($matches[0], 1)]) ? $replace[substr($matches[0], 1)] : $matches[0]; }, $val);
		}
		return $val;
	}
}