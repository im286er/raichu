<?php
namespace vakata\cache;

interface CacheInterface
{
	public function clear($partition = false);
	public function prepare($key, $partition = false);
	public function set($key, $value, $partition = false, $expires = 14400);
	public function get($key, $partition = false, $meta_only = false);
	public function delete($key, $partition = false);
	public function getSet($key, callable $value = null, $partition = false, $time = 14400);
}