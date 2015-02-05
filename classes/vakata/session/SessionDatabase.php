<?php
namespace vakata\session;

class SessionDatabase implements \SessionHandlerInterface
{
	private $db = null;
	private $tb = null;

	public function __construct(\vakata\database\DatabaseInterface $db, $tb = 'sessions') {
		$this->db = $db;
		$this->tb = $tb;
	}

	public function close() {
		return true;
	}
	public function destroy($session_id) {
		$this->db->query('DELETE FROM '.$this->tb.' WHERE id = ?', [$session_id])->affected();
		return true;
	}
	public function gc($maxlifetime) {
		$this->db->query('DELETE FROM '.$this->tb.' WHERE updated < ?', [date('Y-m-d H:i:s', (time() - (int)$maxlifetime))]);
		return true;
	}
	public function open($save_path, $name) {
		return true;
	}
	public function read($session_id) {
		$data = $this->db->one('SELECT data FROM '.$this->tb.' WHERE id = ?', [$session_id]);
		return $data ? $data : '';
	}
	public function write($session_id, $session_data) {
		return $this->db->query('INSERT INTO '.$this->tb.' (id, data, created, updated) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE data = ?, updated = ?', array($session_id, $session_data, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $session_data, date('Y-m-d H:i:s')))->affected();
	}
}