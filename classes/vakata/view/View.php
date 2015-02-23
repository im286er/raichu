<?php
namespace vakata\view;

class View
{
	protected $file = null;
	protected $data = null;

	public function __construct($file, $data = null) {
		$this->file = $file;
		$this->data = $data;
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