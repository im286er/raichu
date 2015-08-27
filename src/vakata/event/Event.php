<?php
namespace vakata\event;

class Event implements EventInterface
{
	protected $_events = [];

	public function listen($event, callable $callback) {
		foreach (explode(' ', $event) as $e) {
			if (!isset($this->_events[(string)$e])) {
				$this->_events[(string)$e] = array();
			}
			$this->_events[(string)$e][] = $callback;
		}
	}
	public function trigger($event, $params = null) {
		if (isset($this->_events[$event]) && is_array($this->_events[$event])) {
			foreach ($this->_events[$event] as $callback) {
				call_user_func($callback, $params);
			}
		}
	}
}