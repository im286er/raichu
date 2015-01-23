<?php
namespace vakata\log;

class LogDatabase extends Log
{
	protected $db = null;
	protected $tb = null;
	public function __construct(\vakata\database\DatabaseInterface $db, $tb) {
		$this->db = $db;
		$this->tb = $tb;
	}
	protected function log($severity, $message, array $context = []) {
		return $this->db->query('INSERT INTO ' . $this->tb . ' (tm, severity, message, context) VALUES (?,?,?,?)', [
			date('Y-m-d H:i:s'),
			$severity,
			$message,
			(count($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '')
		]);
	}
}