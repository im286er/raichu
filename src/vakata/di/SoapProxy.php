<?php
namespace vakata\di;

class SoapProxy
{
	protected $dic;
	protected $instance;

	function __construct(DI $dic, $class) {
		$this->duc = $dic;
		$this->instance = $this->di->instance($class);
	}
	public function __call($method, $args) {
		return $this->dic->invoke($this->instance, $method, $args);
	}
}
