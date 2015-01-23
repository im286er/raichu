<?php
namespace vakata\user\authentication;

class Dummy extends AbstractAuthentication
{
	public function authenticate($data = null) {
		return [
			'id'   => 'guest',
			'name' => 'guest'
		];
	}
}