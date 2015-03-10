<?php
namespace vakata\view;

class View
{
	protected static $vdir = '';
	protected $file = null;
	protected $data = [];

	public function __construct($file, array $data = []) {
		$this->file = $this->normalize($file);
		$this->data = $data;
	}
	protected function normalize($view) {
		if(static::$vdir) {
			$view = static::$vdir . DIRECTORY_SEPARATOR . $view;
		}
		if(!preg_match('(\.php$)i',$view)) {
			$view .= '.php';
		}
		if(!is_file($view) || !is_readable($view)) {
			throw new ViewException('View not found', 404);
		}
		return $view;
	}

	public function add($name, $value) {
		$this->data[$name] = $value;
		return $this;
	}
	public function render() {
		extract($this->data);
		ob_start();
		include $this->file;
		return ob_get_clean();
	}
	public static function dir($dir) {
		static::$vdir = realpath($dir);
	}
	public static function get($file, $data = null) {
		return (new self($file, $data))->render();
	}
}