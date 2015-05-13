<?php
namespace vakata\localization;

class Lang
{
	protected $data = [];
	protected $lang = [];
	protected $dflt = null;

	public function add($code, $data) {
		if(!isset($this->data[$code])) {
			$this->data[$code] = [];
		}
		if(is_string($data)) {
			$this->data[$code][] = $data;
			if(isset($this->lang[$code])) {
				if(is_file($data)) {
					$temp = @json_decode(file_get_contents($data), true);
					if($temp) {
						$this->lang[$code] = array_merge($temp, $this->lang[$code]);
					}
				}
			}
		}
		if(is_array($data)) {
			if(!isset($this->lang[$code])) {
				$this->load($code);
			}
			$this->lang[$code] = array_merge($data, $this->lang[$code]);
		}
		return $this;
	}
	public function available() {
		return array_keys($this->data);
	}

	public function setDefault($code) {
		if(isset($this->data[$code])) {
			$this->dflt = $code;
		}
		return $this;
	}
	public function getDefault($code) {
		return $this->dflt;
	}

	public function get($key, $count = 0, array $replace = [], $code = null) {
		if(!isset($code)) {
			$code = isset($this->dflt) && isset($this->data[$this->dflt]) ? $this->dflt : array_keys($this->data);
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
	public function __invoke($key, $count = 0, array $replace = [], $code = null) {
		return $this->get($key, $count, $replace, $code);
	}
	public function dump($code = null) {
		if($code === null || !isset($this->data[$code])) {
			$code = isset($this->dflt) && isset($this->data[$this->dflt]) ? $this->dflt : key($this->data);
		}
		if(!isset($this->lang[$code])) {
			$this->load($code);
		}
		$result = [];
		$this->traverse($this->lang[$code], $result, $code);
		return $result;
	}

	protected function traverse($value, &$result, $key = '') {
		foreach($value as $k => $v) {
			if(is_string($v)) {
				$result[$key . '[' . $k . ']'] = $v;
			}
			if(is_array($v)) {
				$this->traverse($v, $result, $key . '[' . $k . ']');
			}
		}
	}
	protected function load($code) {
		if(!isset($this->data[$code])) {
			return null;
		}
		if(!isset($this->lang[$code])) {
			$this->lang[$code] = [];
		}
		foreach($this->data[$code] as $file) {
			if(is_file($file)) {
				$temp = @json_decode(file_get_contents($file), true);
				if($temp) {
					$this->lang[$code] = array_merge($temp, $this->lang[$code]);
				}
			}
		}
	}
	protected function parse($code, $key, $count = 0, array $replace = []) {
		if(!isset($this->data[$code])) {
			return null;
		}
		if(!isset($this->lang[$code])) {
			$this->load($code);
		}
		if(!isset($this->lang[$code])) {
			return null;
		}
		$tmp = explode('.', $key);
		$val = $this->lang[$code];
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