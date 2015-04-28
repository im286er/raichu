<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class Manager implements AuthenticationInterface
{
	protected $services = [];
	protected $prov = '';

	public function __construct(array $services = null) {
		if($services) {
			foreach($services as $service) {
				if($service instanceof AuthenticationInterface) {
					$this->services[] = $service;
				}
			}
		}
	}

	public function provider() {
		return $this->prov;
	}
	public function authenticate($data = null) {
		foreach($this->services as $service) {
			$temp = $service->authenticate($data);
			if($temp) {
				$this->prov = $service->provider();
				return $temp;
			}
		}
		return null;
	}
	public function clear() {
		foreach($this->services as $service) {
			$service->clear();
		}
	}
	public function restore($data = null) {
		foreach($this->services as $service) {
			try {
				return $service->restore($data);
			}
			catch(NoRestoreException $ignore) { }
		}
		throw new NoRestoreException('Нe се поддържа възстановяване на достъпа');
	}
}