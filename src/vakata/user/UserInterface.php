<?php
namespace vakata\user;

use vakata\user\authentication\AuthenticationInterface;

interface UserInterface
{
	public function valid();
	public function get($key);
	public function login($data = null);
	public function logout();
	public function restore($data = null);
}