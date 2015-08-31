<?php
namespace vakata\user\authentication;

class DecoratorToken implements AuthenticationInterface
{
	protected $auth = null;
	protected $prov = null;
	protected $settings = null;

	public function __construct(AuthenticationInterface $auth, array $settings) {
		$this->settings = array_merge([
			'key'      => 'tokenkey',
			'cookie'   => false,
			'validity' => 3600 * 24 * 30
		], $settings);
		$this->key = $this->settings['key'];
		$this->auth = $auth;
	}
	public function provider() {
		return isset($this->prov) ? $this->prov : $this->auth->provider();
	}
	public function authenticate($data = null) {
		if (!isset($data['token']) && $this->settings['cookie'] && isset($_COOKIE) && isset($_COOKIE[$this->settings['cookie']])) {
			$data['token'] = $_COOKIE[$this->settings['cookie']];
		}
		if (isset($data['token'])) {
			$temp = $this->verifyToken($data['token']);
			if ($temp) {
				$temp['token'] = $this->generateToken($temp, 0, $this->settings['validity']);
				if ($this->settings['cookie']) {
					setcookie($this->settings['cookie'], $temp['token'], time() + $this->settings['validity'], '/', null, false, true);
				}
				return $temp;
			}
		}
		$temp = $this->auth->authenticate($data);
		if ($temp) {
			$temp['provider'] = $this->auth->provider();
			$temp['token'] = $this->generateToken($temp, 0, $this->settings['validity']);
			if ($this->settings['cookie']) {
				setcookie($this->settings['cookie'], $temp['token'], time() + $this->settings['validity'], '/', null, false, true);
			}
		}
		return $temp;
	}
	public function clear() {
		if ($this->settings['cookie']) {
			setcookie($this->settings['cookie'], "", time() - $this->settings['validity'], '/', null, false, true);
		}
		return $this->auth->clear();
	}
	public function restore($data = null) {
		return $this->auth->restore($data);
	}
	protected function verifyToken($challenge) {
		$token = explode('.', $challenge);
		if (count($token) != 3) {
			throw new UserException('Invalid token');
		}
		$token = array_map(function ($input) {
			return base64_decode(strtr($input, '-_', '+/'));
		}, $token);

		$token[1] = json_decode($token[1], true);
		if (isset($token[1]['provider'])) {
			$this->prov = $token[1]['provider'];
		}

		$token[0] = json_decode($token[0], true);
		if (!$token[0] || !$token[0]['alg']) {
			throw new UserException('Invalid token');
		}

		$key = $this->key;
		if (is_array($this->key)) {
			if (!isset($token[0]['kid']) || !isset($this->key[$token[0]['kid']])) {
				throw new UserException('Invalid token');
			}
			$key = $this->key[$token[0]['kid']];
		}
		$temp = explode('.', $challenge);
		switch ($token[0]['alg']) {
			case 'RS256':
				if (!openssl_verify($temp[0] . '.' . $temp[1], $token[2], $key, str_replace(['RS','HS'], 'SHA', $token[0]['alg']))) {
					//throw new UserException('Invalid token');
					return null;
				}
				break;
			case 'HS256':
			case 'HS512':
			case 'HS384':
			default:
				if ($token[2] !== hash_hmac(str_replace(['RS','HS'], 'SHA', $token[0]['alg']), $temp[0] . '.' . $temp[1], $key, true)) {
					//throw new UserException('Invalid token');
					return null;
				}
		}

		if (isset($token[1]['nbf']) && $token[1]['nbf'] > time()) {
			throw new UserException('Invalid token');
		}
		if (isset($token[1]['iat']) && $token[1]['iat'] > time()) {
			throw new UserException('Invalid token');
		}
		if (isset($token[1]['exp']) && time() >= $token[1]['exp']) {
			throw new UserException('Invalid token');
		}
		return $token[1];
	}
	protected function generateToken($data, $beg = 0, $end = 0) {
		$kid = null;
		$key = $this->key;
		if (is_array($key)) {
			$kid = array_rand($key);
			$key = $key[$kid];
		}
		$body = $data;
		$body['provider'] = $this->provider();
		$body['iat'] = time();
		if ((int)$beg) {
			$body['nbf'] = time() + $beg;
		}
		if ((int)$end) {
			$body['exp'] = time() + $end;
		}
		$head = [ 'typ' => 'JWT', 'alg' => 'HS256' ];
		if (isset($kid)) {
			$head['kid'] = $kid;
		}

		$head = str_replace('=', '', strtr(base64_encode(json_encode($head)), '+/', '-_'));
		$body = str_replace('=', '', strtr(base64_encode(json_encode($body)), '+/', '-_'));

		$sign = hash_hmac('SHA256', $head . '.' . $body, $key, true);
		$sign = str_replace('=', '', strtr(base64_encode($sign), '+/', '-_'));
		return $head . '.' . $body . '.' . $sign;
	}
}
