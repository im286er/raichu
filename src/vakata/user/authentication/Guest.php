<?php
namespace vakata\user\authentication;

class Guest extends AbstractAuthentication
{
	public function authenticate($data = null) {
		return [
			'id'    => 'guest',
			'name'  => 'Guest',
			'guest' => true
		];
	}
}
