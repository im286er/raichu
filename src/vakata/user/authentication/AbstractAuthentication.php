<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

abstract class AbstractAuthentication implements AuthenticationInterface
{
	public function provider() {
		return @strtolower(end(explode('\\', get_class($this))));
	}
	public function clear() {
	}
	public function restore($data = null) {
		throw new NoRestoreException('Не се поддържа възстановяване на достъп');
	}
	abstract public function authenticate($data = null);
}