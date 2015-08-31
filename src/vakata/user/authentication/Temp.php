<?php
namespace vakata\user\authentication;

use vakata\random\Random as random;

class Temp extends AbstractAuthentication
{
	public function authenticate($data = null) {
		return [
			'id' => time() . '.' . random::string(32),
			'temporary' => true
		];
	}
}
