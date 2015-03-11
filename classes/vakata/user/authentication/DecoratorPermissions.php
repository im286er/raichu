<?php
namespace vakata\user\authentication;

class DecoratorPermissions implements AuthenticationInterface
{
	protected $perm = null;
	protected $auth = null;

	public function __construct(AuthenticationInterface $auth, $perm = 'permissions') {
		$this->auth = $auth;
		$this->perm = $perm;
	}
	public function provider() {
		return $this->auth->provider();
	}
	public function authenticate($data = null) {
		$temp = $this->auth->authenticate($data);
		if($temp) {
			if(isset($temp[$this->perm])) {
				$perm = @json_decode($temp[$this->perm]);
				if(!isset($temp['permissions']) || !is_array($temp['permissions'])) {
					$temp['permissions'] = [];
				}
				if(is_array($perm)) {
					$temp['permissions'] = array_merge($perm, $temp['permissions']);
				}
			}
			$temp['permissions'] = array_unique(array_filter(array_map('strtolower',$temp['permissions'])));
		}
		return $temp;
	}
	public function clear() {
		return $this->auth->clear();
	}
	public function restore($data = null) {
		return $this->auth->restore($data);
	}
}