<?php
namespace vakata\user\authentication;

class Certificate extends AbstractAuthentication
{
	protected $fields = [];

	public function __construct(array $fields = []) {
		$this->fields = $fields;
	}
	public function authenticate($data = null) {
		if (isset($_SERVER['HTTPS']) && isset($_SERVER['SSL_CLIENT_M_SERIAL']) && isset($_SERVER['SSL_CLIENT_VERIFY']) && $_SERVER['SSL_CLIENT_VERIFY'] === 'SUCCESS') {
			$data = [
				'id'          => $_SERVER['SSL_CLIENT_M_SERIAL'],
				'certificate' => $_SERVER['SSL_CLIENT_M_SERIAL'],
				'name'        => isset($_SERVER['SSL_CLIENT_S_DN_CN']) ? $_SERVER['SSL_CLIENT_S_DN_CN'] : '',
				'mail'        => isset($_SERVER['SSL_CLIENT_S_DN_Email']) ? $_SERVER['SSL_CLIENT_S_DN_Email'] : ''
			];
			foreach($this->fields as $k => $v) {
				if(is_numeric($k)) {
					$k = $v;
				}
				$data[$v] = isset($_SERVER['SSL_CLIENT_' . $k]) ? $_SERVER['SSL_CLIENT_' . $k] : null;
			}
			return $data;
		}
	}
}