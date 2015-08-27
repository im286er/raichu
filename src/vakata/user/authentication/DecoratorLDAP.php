<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class DecoratorLDAP implements AuthenticationInterface
{
	protected $auth = null;
	protected $domain = null;
	protected $username = null;
	protected $password = null;

	public function __construct(AuthenticationInterface $auth, $domain, $username = null, $password = null) {
		$this->auth = $auth;
		$this->domain = $domain;
		$this->username = $username;
		$this->password = $password;
	}
	public function provider() {
		return $this->auth->provider();
	}
	public function clear() {
		return $this->auth->clear();
	}
	public function restore($data = null) {
		return $this->auth->restore($data);
	}
	public function authenticate($data = null) {
		$temp = $this->auth->authenticate($data);
		if ($temp) {
			if (!isset($temp['mail'])) {
				throw new UserException('Потребителят няма валиден e-mail адрес.');
			}
			$ldap = @ldap_connect($this->domain);
			if (!$ldap) {
				throw new UserException('Грешка при връзката с домейн.');
			}
			@ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			@ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
			if (!@ldap_bind($ldap, $this->username, $this->password)) {
				throw new UserException('Грешка при аутентикацията пред домейн.');
			}

			$data = @ldap_search($ldap, 'DC=' . implode(',DC=', explode('.', $this->domain)), '(&(objectclass=person)(mail='.$temp['mail'].'))');
			if (!$data) {
				throw new UserException('Грешка при търсене в домейн.');
			}
			$data = @ldap_first_entry($ldap, $data);
			if (!$data) {
				throw new UserException('Невалиден потребител.');
			}
			$data = @ldap_get_attributes($ldap, $data);
			foreach ($data as $k => $v) {
				if ($v && isset($v['count']) && $v['count'] === 1) {
					$temp[$k] = $v[0];
				}
			}
			$temp['name'] = $temp['displayName'];
			@ldap_unbind($ldap);
			return $temp;
		}
		return null;
	}
}