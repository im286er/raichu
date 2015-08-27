<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class DecoratorGroups implements AuthenticationInterface
{
	protected $db   = null;
	protected $tb   = '';
	protected $auth = null;

	public function __construct(AuthenticationInterface $auth, \vakata\database\DatabaseInterface $db, $tb = 'users_groups') {
		$this->auth = $auth;
		$this->db   = $db;
		$this->tb   = $tb;
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
			$grps = $this->db->all(
				'SELECT g.id, g.name, g.permissions, l.primary_group FROM ' . $this->tb . '_link l, ' . $this->tb . ' g WHERE l.user_id = ? AND g.id = l.group_id ORDER BY l.primary_group ASC',
				[$temp['id']]);

			$temp['primaryGroup'] = null;
			$temp['group'] = [];
			if (!isset($temp['permissions']) || !is_array($temp['permissions'])) {
				$temp['permissions'] = [];
			}
			$perm = [];
			foreach ($grps as $k => $v) {
				$v['permissions'] = @json_decode($v['permissions']);
				if (is_array($v['permissions'])) {
					$perm = array_merge($perm, $v['permissions']);
				}
				if ((int)$v['primary_group']) {
					$temp['primaryGroup'] = (int)$v['id'];
				}
				$temp['group'][(int)$v['id']] = $v['name'];
			}
			$temp['permission'] = $temp['permissions'] = array_unique(array_filter(array_map('strtolower',array_merge($perm, $temp['permissions']))));
		}
		return $temp;
	}
}