<?php
namespace vakata\user\authentication\OAuth;
use vakata\user\authentication\AbstractAuthentication;
use vakata\user\UserException;

abstract class AbstractOAuth extends AbstractAuthentication
{
	protected $public_key    = null;
	protected $private_key   = null;
	protected $callback_url  = null;
	protected $authorize_url = null;
	protected $permissions   = null;
	protected $token_url     = null;
	protected $info_url      = null;
	protected $grant_type    = 'authorization_code';

	public function __construct($public_key, $private_key, $callback_url, $permissions = '') {
		$this->public_key    = $public_key;
		$this->private_key   = $private_key;
		$this->permissions   = $permissions;
		$this->callback_url  = $callback_url;
	}
	public function authenticate($data = null) {
		if(strpos(strtolower(trim($_SERVER['REQUEST_URI'],'/')), $this->provider()) === false) {
			return null;
		}
		if(strpos(trim($_SERVER['REQUEST_URI'],'/'), preg_replace('(^https?://[^/]+/)i','',$this->callback_url)) === false) {
			while(ob_get_level()) { ob_end_clean(); }
			header('Location: ' . 
				$this->authorize_url . 
					'client_id='    . urlencode($this->public_key) . '&' . 
					'scope='        . urlencode($this->permissions) . '&' . 
					'redirect_uri=' . urlencode($this->callback_url) . '&' . 
					'state='        . $this->state()
			);
		}
		else {
			if(isset($_GET['error_reason']) || isset($_GET['error']) || !isset($_GET['code'])) {
				throw new UserException('Грешка при взимането на токен');
			}
			if(!isset($_GET['state']) || $_GET['state'] !== $this->state()) {
				throw new UserException('Грешка при валидация на състоянието');
			}
			$access_token = @file_get_contents($this->token_url, false, stream_context_create([
				'http' => [
					'method'  => 'POST',
					'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
					'content' => http_build_query([
						'client_id'      => $this->public_key,
						'redirect_uri'   => $this->callback_url,
						'client_secret'  => $this->private_key,
						'code'           => $_GET['code'],
						'grant_type'     => $this->grant_type
					])
				]
			]));
			if(!$access_token) {
				throw new UserException('Грешка при взимането на токен');
			}
			if(@json_decode($access_token, true)) {
				$access_token = @json_decode($access_token, true);
			}
			else {
				parse_str($access_token, $access_token);
			}
			if(!$access_token || !is_array($access_token) || !isset($access_token['access_token'])) {
				throw new UserException('Грешка при взимането на токен');
			}
			$access_token = $access_token['access_token'];
			$user_details = @file_get_contents($this->info_url . 'access_token=' . rawurlencode($access_token));
			if(!$user_details || !($user_details = @json_decode($user_details, true)) || isset($user_details['error'])) {
				throw new UserException('Грешка при извличането на потребител');
			}
			return $user_details;
		}
	}
	protected function state() {
		return sha1(session_id() . '/' . $this->public_key . '/' . $this->callback_url . '/' . ( isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' ) . '/' . ( isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '' ));
	}
}