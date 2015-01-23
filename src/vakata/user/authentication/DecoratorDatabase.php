<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class DecoratorDatabase implements AuthenticationInterface
{
	protected $db       = null;
	protected $tb       = '';
	protected $id       = null;
	protected $auth     = null;
	protected $register = false;

	public function __construct(AuthenticationInterface $auth, \vakata\database\DatabaseInterface $db, $tb = 'users', $register = false) {
		$this->auth = $auth;
		$this->db = $db;
		$this->tb = $tb;
		$this->register = $register;
	}
	public function provider() {
		return $this->auth->provider();
	}
	public function clear() {
		if($this->id) {
			$this->db->query('UPDATE ' . $this->tb . ' SET last_session = ?, last_seen = ? WHERE id = ?', [ '', date('Y-m-d H:i:s'), $this->id ]);
			$this->id = null;
		}
		return $this->auth->clear();
	}
	public function restore($data = null) {
		$hash = $this->auth->restore();
		$user = $this->getByProviderId($hash['provider'], $hash['id']);
		return array_merge($user, $hash);
	}
	public function authenticate($data = null) {
		$temp = $this->auth->authenticate($data);
		if($temp) {
			$data = $this->getByProviderId($this->auth->provider(), $temp['id']);
			if(!$data && $this->register) {
				// name, mail - normalize from data and insert
				$user_id = $this->db->query('INSERT INTO '.$this->tb.' (name, mail, last_seen, last_login, created, match_session) VALUES (?,?,?,?,?,?)', [ isset($temp['name']) ? $temp['name'] : $temp['id'], isset($temp['mail']) ? $temp['mail'] : '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), 1 ])->insertId();
				$this->db->query(
					'INSERT INTO ' . $this->tb . '_authentication (provider, provider_id, user_id) VALUES(?,?,?)', 
					[ $this->auth->provider(), $temp['id'], $user_id ]
				);
				$data = $this->db->one(
					'SELECT * FROM ' . $this->tb . ' WHERE id = ?', 
					[ $user_id ]
				);
			}
			if(!$data) {
				throw new UserException('Невалиден потребител');
			}
			if((int)$data['disabled']) {
				throw new UserException('Блокиран потребител');
			}
			if(isset($temp['login']) && isset($temp['seen']) && $temp['login'] < $temp['seen'] && isset($data['match_session']) && isset($data['last_session']) && (bool)$data['match_session'] && $data['last_session'] !== session_id()) {
				throw new UserException('Има друг потребител с вашето име в системата.');
			}
			// name, mail - normalize from data and update if present
			$this->db->query(
				'UPDATE ' . $this->tb . ' SET last_session = ?, last_seen = ?, last_login = ? WHERE id = ?', 
				[ session_id(), date('Y-m-d H:i:s'), date('Y-m-d H:i:s', isset($temp['login']) ? $temp['login'] : time()), $data['id'] ]
			);
			$this->id = isset($data['id']) ? $data['id'] : null;
			return array_merge($temp, $data);
		}
		return null;
	}

	protected function getByProviderId($provider, $id) {
		return $this->db->one(
			'SELECT u.* FROM ' . $this->tb . '_authentication p, ' . $this->tb . ' u WHERE p.user_id = u.id AND p.provider = ? AND p.provider_id = ?', 
			[ $provider, $id ]
		);
	}
}