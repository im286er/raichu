<?php
namespace vakata\user\authentication\JWT;
use vakata\user\authentication\AbstractAuthentication;
use vakata\user\UserException;

abstract class AbstractJwt extends AbstractAuthentication
{
	protected $key = null;

	public function __construct($key) {
		$this->key = $key;
	}
	public function authenticate($data = null) {
		if (!isset($data['token'])) {
			return null;
		}

		$token = explode('.', $data['token']);
		if (count($token) != 3) {
			throw new UserException('Invalid token');
		}
		$token = array_map(function ($input) {
			return base64_decode(strtr($input, '-_', '+/'));
		}, $token);
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
		$temp = explode('.', $data['token']);
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

		$token[1] = json_decode($token[1], true);
		if (isset($token[1]['nbf']) && $token[1]['nbf'] > time()) {
			throw new UserException('Invalid token');
		}
		if (isset($token[1]['iat']) && $token[1]['iat'] > time()) {
			throw new UserException('Invalid token');
		}
		if (isset($token[1]['exp']) && time() >= $token[1]['exp']) {
			throw new UserException('Invalid token');
		}
		return $this->data = $token[1];
	}
}