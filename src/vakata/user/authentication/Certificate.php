<?php
namespace vakata\user\authentication;

class Certificate extends AbstractAuthentication
{
	protected $filter = [];

	public function __construct(callable $filter = null) {
		$this->filter = $filter;
	}
	public function authenticate($data = null) {
		if (
			isset($_SERVER['HTTPS']) &&
			isset($_SERVER['SSL_CLIENT_M_SERIAL']) &&
			isset($_SERVER['SSL_CLIENT_VERIFY']) &&
			$_SERVER['SSL_CLIENT_VERIFY'] === 'SUCCESS'
		) {
			$data = [
				'id'          => $_SERVER['SSL_CLIENT_M_SERIAL'],
				'certificate' => $_SERVER['SSL_CLIENT_M_SERIAL'],
				'name'        => isset($_SERVER['SSL_CLIENT_S_DN_CN']) ? $_SERVER['SSL_CLIENT_S_DN_CN'] : '',
				'mail'        => isset($_SERVER['SSL_CLIENT_S_DN_Email']) ? $_SERVER['SSL_CLIENT_S_DN_Email'] : ''
			];
			return (isset($this->filter) && is_callable($this->filter)) ?
				$this->filter($data) :
				$data;
		}
	}
}
