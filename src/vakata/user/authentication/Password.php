<?php
namespace vakata\user\authentication {

	use vakata\user\UserException;

	class Password extends AbstractAuthentication
	{
		use \vakata\user\TraitLogData;

		protected $db = null;
		protected $tb = null;
		protected $settings = null;

		public function __construct(\vakata\database\DatabaseInterface $db, $tb = 'users_password', array $settings = []) {
			$this->db = $db;
			$this->tb = $tb;
			$this->settings = array_merge([
				'forgot_password'	=> 1800,
				'force_changepass'	=> 0, // никога 2592000 // 30 дни
				'error_timeout'		=> 30,
				'error_timeout_cnt'	=> 3,
				'max_errors'		=> 10,
				'ip_errors'			=> 5
			], $settings);
		}

		public function authenticate($data = null) {
			if (is_array($data) && isset($data['forgotpassword']) && strlen($data['forgotpassword']) && (int)$this->settings['forgot_password']) {
				$tmp = $this->db->one("SELECT password_id, created FROM ".$this->tb."_restore WHERE hash = ? AND used = 0 ORDER BY created DESC LIMIT 1", array($data['forgotpassword']));
				if (!$tmp) {
					throw new UserException('Невалиден токен');
				}
				if (time() - (int)strtotime($tmp['created']) > (int)$this->settings['forgot_password']) {
					throw new UserException('Изтекъл токен.');
				}
				$id = $this->validateChange($tmp['password_id'], $data);
				$this->db->query('UPDATE '.$this->tb.'_restore SET used = 1 WHERE hash = ? AND used = 0', array($data['forgotpassword']));
				$this->db->query('INSERT INTO '.$this->tb.'_log (password_id, action, created, ip, ua) VALUES(?,?,?,?,?)', array($id, 'login', date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()));
				$temp = $this->db->one("SELECT * FROM " . $this->tb . " WHERE id = ?", [$id]);
				return $this->filterReturn($temp);
			}
			// login with user and pass
			if (!isset($data['username']) || !isset($data['password'])) {
				return null;
			}
			$username = $data['username'];
			$password = $data['password'];

			if ((int)$this->settings['ip_errors'] && (int)$this->db->one('SELECT COUNT(*) FROM ' . $this->tb . '_log WHERE ip = ? AND action = \'error\' AND created > NOW() - INTERVAL 1 HOUR', array($this->ipAddress())) > (int)$this->settings['ip_errors']) {
				throw new UserException('IP адресът е блокиран за един час след ' . (int)$this->settings['ip_errors'] . ' грешни опита.');
			}
			$tmp = $this->db->one('SELECT id, username, password, created FROM ' . $this->tb . ' WHERE username = ? ORDER BY created DESC LIMIT 1', array($username));
			if (!$tmp) {
				throw new UserException('Грешно потребителско име.');
			}
			$err = $this->db->all('SELECT action, created FROM ' . $this->tb . '_log WHERE password_id = ? AND created > NOW() - INTERVAL 1 HOUR ORDER BY created DESC LIMIT 20', array($tmp['id']));
			$err_cnt = 0;
			$err_dtm = 0;
			foreach ($err as $e) {
				if ($e['action'] === 'login') {
					break;
				}
				if ($e['action'] === 'error') {
					$err_cnt ++;
					if (!$err_dtm) {
						$err_dtm = strtotime($e['created']);
					}
				}
			}
			if (
				(int)$this->settings['error_timeout'] &&
				$err_cnt && $err_cnt >= (int)$this->settings['error_timeout_cnt'] &&
				time() - $err_dtm < (int)$this->settings['error_timeout']
			) {
				throw new UserException('Изчакайте ' . (int)$this->settings['error_timeout'] . ' секунди преди нов опит.');
			}
			if ($err_cnt && (int)$this->settings['max_errors'] && $err_cnt >= (int)$this->settings['max_errors'] && time() - $err_dtm < 3600) {
				throw new UserException('Потребителят е блокиран за един час след ' . (int)$this->settings['max_errors'] . ' грешни опита.');
			}
			if ($tmp['password'] === '') {
				throw new UserException('Потребителят не може да влиза с парола.');
			}
			if ($password === $tmp['password']) {
				$this->db->query('INSERT INTO '.$this->tb.'_log (password_id, action, created, ip, ua) VALUES(?,?,?,?,?)', array($tmp['id'], 'login', date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()));
				$temp = $this->db->one('SELECT * FROM ' . $this->tb . ' WHERE id = ?', [ $this->validateChange($tmp['id'], $data) ]);
				return $this->filterReturn($temp);
			}
			if (!password_verify($password, $tmp['password'])) {
				$this->db->query('INSERT INTO '.$this->tb.'_log (password_id, action, created, ip, ua) VALUES(?,?,?,?,?)', array($tmp['id'], 'error', date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()));
				throw new UserException('Грешна парола.');
			}
			$this->db->query('INSERT INTO '.$this->tb.'_log (password_id, action, created, ip, ua) VALUES(?,?,?,?,?)', array($tmp['id'], 'login', date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()));
			if (
				((int)$this->settings['force_changepass'] && isset($tmp['created']) && time() - strtotime($tmp['created']) > (int)$this->settings['force_changepass']) ||
				(isset($data['password1']) && isset($data['password2']) && isset($data['changepassword']) && (int)$data['changepassword'])
			) {
				$temp = $this->db->one('SELECT * FROM ' . $this->tb . ' WHERE id = ?', [ $this->validateChange($tmp['id'], $data) ]);
				return $this->filterReturn($temp);
			}
			if (password_needs_rehash($tmp['password'], PASSWORD_DEFAULT)) {
				$this->rehashPassword($tmp['id'], $password, true);
			}
			return $this->filterReturn($tmp);
		}
		public function restore($data = null) {
			if ((int)$this->settings['forgot_password']) {
				$e = $this->db->one("SELECT id, username FROM ".$this->tb." WHERE password <> '' AND username = ?", array($data['username']));
				if (!$e) {
					throw new UserException('Невалидно потребителско име');
				}
				$m = $e['username'];
				$hsh = md5($e['id'] . $m . time() . rand(0,9));
				if ($this->db->query(
					"INSERT INTO ".$this->tb."_restore (hash, password_id, created, ip, ua) VALUES (?,?,?,?,?)",
					[$hsh, $e['id'], date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()]
				)->affected()) {
					return array('id' => $m, 'token' => $hsh, 'provider' => $this->provider()); //, 'is_mail' => filter_var($m, FILTER_VALIDATE_EMAIL));
				}
				throw new UserException('Моля, опитайте отново');
			}
			throw new NoRestoreException('Невъзможно възстановяването на парола');
		}

		protected function filterReturn($data) {
			$data['id'] = $data['username'];
			$data['name'] = $data['username'];
			if (filter_var($data['username'], FILTER_VALIDATE_EMAIL)) {
				$data['mail'] = $data['username'];
			}
			unset($data['password']);
			return $data;
		}
		protected function rehashPassword($id, $password) {
			$this->db->query("UPDATE ".$this->tb." SET password = ? WHERE id = ?", array(password_hash($password, PASSWORD_DEFAULT), $id));
		}
		protected function changePassword($id, $password) {
			//$username = $this->db->one("SELECT username FROM ".$this->tb." WHERE id = ?", [$id]);
			//return $this->db->query("INSERT INTO ".$this->tb." (username, password, created) VALUES(?,?,?)", [$username, password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')])->insertId();
			$this->db->query('INSERT INTO '.$this->tb.'_log (password_id, action, created, ip, ua) VALUES(?,?,?,?,?)', array($id, 'change', date('Y-m-d H:i:s'), $this->ipAddress(), $this->userAgent()));
			$this->db->query("UPDATE ".$this->tb." SET password = ?, created = ? WHERE id = ?", array(password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $id));
			return $id;
		}
		protected function validateChange($id, array $data) {
			if (!isset($data['password1']) || !isset($data['password2']) || !strlen($data['password2'])) {
				throw new PasswordChangeException('Моля сменете паролата си.');
			}
			if ($data['password1'] !== $data['password2']) {
				throw new PasswordChangeException('Паролите не съвпадат');
			}
			return $this->changePassword($id, $data['password1']);
		}
	}
}

namespace {
	if (!defined('PASSWORD_BCRYPT')) {
		define('PASSWORD_BCRYPT', 1);
		define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);
		define('PASSWORD_BCRYPT_DEFAULT_COST', 10);
	}
	if (!function_exists('password_hash')) {
		function password_hash($password, $algo, array $options = array()) {
			if (!function_exists('crypt')) {
				trigger_error("Crypt must be loaded for password_hash to function", E_USER_WARNING);
				return null;
			}
			if (is_null($password) || is_int($password)) {
				$password = (string) $password;
			}
			if (!is_string($password)) {
				trigger_error("password_hash(): Password must be a string", E_USER_WARNING);
				return null;
			}
			if (!is_int($algo)) {
				trigger_error("password_hash() expects parameter 2 to be long, " . gettype($algo) . " given", E_USER_WARNING);
				return null;
			}
			$resultLength = 0;
			switch ($algo) {
				case PASSWORD_BCRYPT:
					$cost = PASSWORD_BCRYPT_DEFAULT_COST;
					if (isset($options['cost'])) {
						$cost = $options['cost'];
						if ($cost < 4 || $cost > 31) {
							trigger_error(sprintf("password_hash(): Invalid bcrypt cost parameter specified: %d", $cost), E_USER_WARNING);
							return null;
						}
					}
					// The length of salt to generate
					$raw_salt_len = 16;
					// The length required in the final serialization
					$required_salt_len = 22;
					$hash_format = sprintf("$2y$%02d$", $cost);
					// The expected length of the final crypt() output
					$resultLength = 60;
					break;
				default:
					trigger_error(sprintf("password_hash(): Unknown password hashing algorithm: %s", $algo), E_USER_WARNING);
					return null;
			}
			$salt_requires_encoding = false;
			if (isset($options['salt'])) {
				switch (gettype($options['salt'])) {
					case 'NULL':
					case 'boolean':
					case 'integer':
					case 'double':
					case 'string':
						$salt = (string) $options['salt'];
						break;
					case 'object':
						if (method_exists($options['salt'], '__tostring')) {
							$salt = (string) $options['salt'];
							break;
						}
					case 'array':
					case 'resource':
					default:
						trigger_error('password_hash(): Non-string salt parameter supplied', E_USER_WARNING);
						return null;
				}
				if ((function_exists('mb_strlen') ? mb_strlen($salt, '8bit') : strlen($salt)) < $required_salt_len) {
					trigger_error("password_hash(): Provided salt is too short.", E_USER_WARNING);
					return null;
				} elseif (0 == preg_match('#^[a-zA-Z0-9./]+$#D', $salt)) {
					$salt_requires_encoding = true;
				}
			} else {
				$buffer = '';
				$buffer_valid = false;
				if (function_exists('mcrypt_create_iv') && !defined('PHALANGER')) {
					$buffer = mcrypt_create_iv($raw_salt_len, MCRYPT_DEV_URANDOM);
					if ($buffer) {
						$buffer_valid = true;
					}
				}
				if (!$buffer_valid && function_exists('openssl_random_pseudo_bytes')) {
					$buffer = openssl_random_pseudo_bytes($raw_salt_len);
					if ($buffer) {
						$buffer_valid = true;
					}
				}
				if (!$buffer_valid && @is_readable('/dev/urandom')) {
					$f = fopen('/dev/urandom', 'r');
					$read = function_exists('mb_strlen') ? mb_strlen($buffer, '8bit') : strlen($buffer);
					while ($read < $raw_salt_len) {
						$buffer .= fread($f, $raw_salt_len - $read);
						$read = function_exists('mb_strlen') ? mb_strlen($buffer, '8bit') : strlen($buffer);
					}
					fclose($f);
					if ($read >= $raw_salt_len) {
						$buffer_valid = true;
					}
				}
				if (!$buffer_valid || (function_exists('mb_strlen') ? mb_strlen($buffer, '8bit') : strlen($buffer)) < $raw_salt_len) {
					$bl = function_exists('mb_strlen') ? mb_strlen($buffer, '8bit') : strlen($buffer);
					for ($i = 0; $i < $raw_salt_len; $i++) {
						if ($i < $bl) {
							$buffer[$i] = $buffer[$i] ^ chr(mt_rand(0, 255));
						} else {
							$buffer .= chr(mt_rand(0, 255));
						}
					}
				}
				$salt = $buffer;
				$salt_requires_encoding = true;
			}
			if ($salt_requires_encoding) {
				// encode string with the Base64 variant used by crypt
				$base64_digits =
					'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
				$bcrypt64_digits =
					'./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

				$base64_string = base64_encode($salt);
				$salt = strtr(rtrim($base64_string, '='), $base64_digits, $bcrypt64_digits);
			}
			$salt = function_exists('mb_substr') ? mb_substr($salt, 0, $required_salt_len, '8bit') : substr($salt, 0, $required_salt_len);
			$hash = $hash_format . $salt;
			$ret = crypt($password, $hash);
			if (!is_string($ret) || (function_exists('mb_strlen') ? mb_strlen($ret, '8bit') : strlen($ret)) != $resultLength) {
				return false;
			}
			return $ret;
		}
		function password_get_info($hash) {
			$return = array(
				'algo' => 0,
				'algoName' => 'unknown',
				'options' => array(),
			);
			if ((function_exists('mb_substr') ? mb_substr($hash, 0, 4, '8bit') : substr($hash, 0, 4)) == '$2y$' && (function_exists('mb_strlen') ? mb_strlen($hash, '8bit') : strlen($hash)) == 60) {
				$return['algo'] = PASSWORD_BCRYPT;
				$return['algoName'] = 'bcrypt';
				list($cost) = sscanf($hash, "$2y$%d$");
				$return['options']['cost'] = $cost;
			}
			return $return;
		}
		function password_needs_rehash($hash, $algo, array $options = array()) {
			$info = password_get_info($hash);
			if ($info['algo'] != $algo) {
				return true;
			}
			switch ($algo) {
				case PASSWORD_BCRYPT:
					$cost = isset($options['cost']) ? $options['cost'] : PASSWORD_BCRYPT_DEFAULT_COST;
					if ($cost != $info['options']['cost']) {
						return true;
					}
					break;
			}
			return false;
		}
		function password_verify($password, $hash) {
			if (!function_exists('crypt')) {
				trigger_error("Crypt must be loaded for password_verify to function", E_USER_WARNING);
				return false;
			}
			$ret = crypt($password, $hash);
			if (!is_string($ret) || (function_exists('mb_strlen') ? mb_strlen($ret, '8bit') : strlen($ret)) != (function_exists('mb_strlen') ? mb_strlen($hash, '8bit') : strlen($hash)) || (function_exists('mb_strlen') ? mb_strlen($ret, '8bit') : strlen($ret)) <= 13) {
				return false;
			}
			$status = 0;
			$length = function_exists('mb_strlen') ? mb_strlen($ret, '8bit') : strlen($ret);
			for ($i = 0; $i < $length; $i++) {
				$status |= (ord($ret[$i]) ^ ord($hash[$i]));
			}
			return $status === 0;
		}
	}
}