<?php
namespace vakata\event;

interface EventInterface
{
	public function listen($event, callable $callback);
	public function trigger($event, $params = null);
}