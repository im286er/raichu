<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class Ldap extends AbstractAuthentication
{
	protected $domain = null;

	public function __construct($domain) {
		$this->domain = $domain;
	}
	public function authenticate($data = null) {
		if(!isset($data['username']) || !isset($data['password']) || !isset($this->domain)) {
			return null;
		}
		$username = @current(explode('@', $data['username'])) . '@' . $this->domain;
		$ldap = @ldap_connect($this->domain);
		if(!$ldap) {
			throw new UserException('Грешка при връзката с домейн.');
		}
		@ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		@ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		if(!@ldap_bind($ldap, $username, $data['password'])) {
			throw new UserException('Грешно потребителско име.');
		}

		$data = @ldap_search($ldap, 'DC=' . implode(',DC=', explode('.', $this->domain)), '(&(objectclass=person)(userprincipalname='.$username.'))');
		$data = @ldap_first_entry($ldap, $data);
		if(!$data) {
			throw new UserException('Грешно потребителско име.');
		}
		$temp = [];
		$data = @ldap_get_attributes($ldap, $data);
		foreach($data as $k => $v) {
			if($v && isset($v['count']) && $v['count'] === 1) {
				$temp[$k] = $v[0];
			}
		}
		$temp['id'] = $username;
		$temp['name'] = isset($temp['displayName']) ? $temp['displayName'] : '';

		@ldap_unbind($ldap);
		return $temp;
	}
}