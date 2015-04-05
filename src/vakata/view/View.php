<?php
namespace vakata\view;

class View
{
	protected static $dirs = [];
	protected static $vars = [];

	protected $file = null;
	protected $data = [];

	public function __construct($file, array $data = []) {
		$this->file = static::normalize($file);
		$this->data = $data;
	}

	public function add($name, $value = null) {
		if(is_array($name) && $value === null) {
			$this->data = array_merge($this->data, $name);
		}
		else {
			$this->data[$name] = $value;
		}
		return $this;
	}
	public function render($master = null, array $masterData = []) {
		extract(static::$vars);
		extract($this->data);
		try {
			ob_start();
			include $this->file;
			$data = ob_get_clean();
			if($master) {
				$data = (new self($master, $masterData))->add('data', $data)->render();
			}
			return $data;
		}
		catch(\Exception $e) {
			ob_get_clean();
			throw $e;
		}
	}

	public static function get($file, array $data = []) {
		return (new self($file, $data))->render();
	}
	public static function share($var, $value = null) {
		if(is_array($var) && $value === null) {
			static::$vars = array_merge(static::$vars, $var);
		}
		else {
			static::$vars[$var] = $value;
		}
	}
	public static function dir($dir) {
		if(!realpath($dir)) {
			throw new ViewException('Invalid dir');
		}
		static::$dirs[] = realpath($dir);
		static::$dirs = array_unique(array_filter(static::$dirs));
	}
	public static function exists($file) {
		try {
			static::normalize($file);
			return true;
		}
		catch(ViewException $e) {
			return false;
		}
	}
	
	protected static function normalize($view) {
		if(!preg_match('(\.php$)i',$view)) {
			$view .= '.php';
		}
		foreach(static::$dirs as $dir) {
			if(is_file($dir . DIRECTORY_SEPARATOR . $view) && is_readable($dir . DIRECTORY_SEPARATOR . $view)) {
				return $dir . DIRECTORY_SEPARATOR . $view;
			}
		}
		throw new ViewException('View not found', 404);
	}
}