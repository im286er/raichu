<?php
namespace raichu\api;

class SOAP
{
	public function __construct(\vakata\route\Route $router, $namespace, $url = '') {
		$url = trim($url, '/');
		$router
			->with($url)
			->add([ 'GET', 'POST' ], '{**:resource}', function ($matches, $req, $res) use ($namespace) {
				try {
					$data = $this->instance($namespace, $matches['resource']);
					$soap = new SoapServer(null, array(
						'uri' => $req->getUrl(false)
					));
					$soap->setObject($data);
					$soap->handle();
				}
				catch(\Exception $ex) {
					$soap->fault($ex->getCode(), $ex->getMessage());
				}
			})
			->with('');
	}
	public function instance($namespace, $commands) {
		if(!is_array($commands)) {
			$commands = explode('/', trim($commands, '/'));
		}
		if(!count($commands)) {
			throw new APIException('Invalid resource');
		}
		if(strrpos($commands[count($commands)-1], '.') !== false) {
			$commands[count($commands)-1] = substr($commands[count($commands)-1], 0, strrpos($commands[count($commands)-1], '.'));
		}

		try {
			$instance = array_shift($commands);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
			$instance = \raichu\Raichu::instance($namespace . '\\' . $instance);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
			return $instance;
		} catch(\Exception $e) {
			throw new APIException($e->getMessage(), 404);
		}
	}
}