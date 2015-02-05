<?php
namespace vakata\user\authentication;

interface AuthenticationInterface
{
	public function provider();
	public function authenticate($data = null);
	public function clear();
	public function restore($data = null);
}