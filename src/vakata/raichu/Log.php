<?php
namespace vakata\raichu;

class Log
{
	use \vakata\user\TraitLogData;

	protected $db;
	protected $tb;

	public function __construct(\vakata\database\DatabaseInterface $db, $tb = 'log') {
		$this->db = $db;
		$this->tb = $tb;
	}

	public function insert($module, $method, array $data = []) {
		try {
			$user = \vakata\raichu\Raichu::user()->id;
		}
		catch(\Exception $e) {
			$user = 0;
		}
		$this->db->query("INSERT INTO {$this->tb} (module, method, data, created, user, ip, session, agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
			$module,
			$method,
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			date('Y-m-d H:i:s'),
			$user,
			$this->ipAddress(),
			$this->sessionId(),
			$this->userAgent()
		]);
	}
	public function __invoke($module, $method, array $data = []) {
		return $this->insert($module, $method, $data);
	}
}