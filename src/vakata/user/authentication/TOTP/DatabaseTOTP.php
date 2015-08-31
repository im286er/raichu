<?php
namespace vakata\user\authentication\TOTP;

use vakata\database\DatabaseInterface;
use vakata\user\authentication\AuthenticationInterface;
use vakata\random\Random;

class DatabaseTOTP extends TOTP implements AuthenticationInterface
{
	protected $db;

	public function __construct(DatabaseInterface $db, AuthenticationInterface $service, array $options = []) {
		$this->db = $db;
		$options = array_merge(['table'	=> 'users_totp'], $options);
		parent::__construct($service, $options);
	}

	public function authenticate($data = null) {
		$temp = $this->service->authenticate($data);
		if ($temp) {
			if (!$_SESSION[$this->options['session']]) {
				$user = $this->db->one("SELECT * FROM {$this->options['table']} WHERE id = ?", [$temp['id']]);
				$temp['totp'] = $user['secret'];
				if ($user && (int)$user['enabled']) {
					$device = null;
					if (isset($_COOKIE['totp_device'])) {
						$device = $this->db->one(
							"SELECT device_id FROM " . $this->options['table'] . "_devices WHERE user_id = ? AND device_id = ?",
							[ $temp['id'], $_COOKIE['totp_device'] ]
						);
					}
					if (!$device) {
						if (!isset($data['totp'])) {
							throw new TOTPException('Моля въведете код');
						}
						$this->verifyCode($temp['totp'], $data['totp']);
						if (isset($data['totp_remember']) && (bool)$data['totp_remember']) {
							$device = \vakata\random\Random::string(32);
							$this->db->query(
								"INSERT INTO " . $this->options['table'] . "_devices (user_id, device_id, friendly_name, created, used) VALUES (?, ?, ?, ?, ?)",
								[
									$temp['id'],
									$device,
									isset($data['totp_device']) && strlen($data['totp_device']) ?
										$data['totp_device'] :
										(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
									date('Y-m-d H:i:s'),
									date('Y-m-d H:i:s')
								]
							);
						}
					}
					if ($device) {
						setcookie('totp_device', $device, time() + 3600 * 24 * 30, '/', null, false, true);
						$this->db->query(
							"UPDATE " . $this->options['table'] . "_devices SET used = ? WHERE user_id = ? AND device_id = ?",
							[
								date('Y-m-d H:i:s'),
								$temp['id'],
								$device
							]
						);
					}
				}
				$_SESSION[$this->options['session']] = true;
			}
		}
		return $temp;
	}
}
