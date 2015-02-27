<?php
namespace vakata\view;

class View
{
	protected $file = null;
	protected $data = [];

	public function __construct($file, array $data = []) {
		$this->file = $file;
		$this->data = $data;
	}
	public function add($name, $value) {
		$this->data[$name] = $value;
	}
	public function render() {
		extract($this->data);
		ob_start();
		include $this->file;
		return ob_get_clean();
	}
	public static function get($file, $data = null) {
		return (new self($file, $data))->render();
	}
}