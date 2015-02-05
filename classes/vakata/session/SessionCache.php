<?php
namespace vakata\session;

class SessionCache implements \SessionHandlerInterface
{
	private $cache  = null;
	private $table  = null;
	private $expire = null;

	public function __construct(\vakata\cache\CacheInterface $cache, $table = 'sessions') {
		$this->cache = $cache;
		$this->table = $table;
		$this->expire = ini_get('session.gc_maxlifetime');
	}
	public function close() {
		return true;
	}
	public function destroy($session_id) {
		return $this->cache->del($session_id, $this->table);
	}
	public function gc($maxlifetime) {
		return true;
	}
	public function open($save_path, $name) {
		return true;
	}
	public function read($session_id) {
		$data = $this->cache->get($session_id, $this->table);
		return $data ? $data : '';
	}
	public function write($session_id, $session_data) {
		return $this->cache->set($session_id, $session_data, $this->table, $this->expire);
	}
}