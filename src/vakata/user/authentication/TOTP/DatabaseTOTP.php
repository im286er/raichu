<?php
namespace vakata\user\authentication\TOTP;

use vakata\database\DatabaseInterface;
use vakata\user\authentication\AuthenticationInterface;

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
				if ($user && (int)$user['enabled']) {
					$temp['totp'] = $user['secret'];
					if (!isset($data['totp'])) {
						throw new TOTPException('Моля въведете код');
					}
					$this->verifyCode($temp['totp'], $data['totp']);
				}
				$_SESSION[$this->options['session']] = true;
			}
		}
		return $temp;
	}
}