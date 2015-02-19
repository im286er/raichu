<?php
namespace raichu\api;

class SOAP
{
	public function __construct(\vakata\route\Route $router, $classes, $url = '') {
		$url = trim($url, '/');
		$router
			->with($url)
			->add([ 'GET', 'POST' ], '{**:resource}', function ($matches, $req, $res) use ($classes) {
				try {
					$data = $this->instance($classes, $matches['resource']);
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
	public function instance($classes, $commands) {
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

			$class = null;
			if(is_string($classes)) {
				$class = $classes . '\\' . $instance;
			}
			if(!$class && is_array($classes) && isset($classes[$instance])) {
				$class = $classes[$instance];
			}
			if(!$class && is_array($classes)) {
				foreach($classes as $candidate) {
					if(array_reverse(explode('\\', $candidate))[0] === $instance) {
						$class = $candidate;
						break;
					}
				}
			}
			if(!$class) {
				throw new APIException('Invalid resource');
			}

			$instance = \raichu\Raichu::instance($class);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
			return $instance;
		} catch(\Exception $e) {
			throw new APIException($e->getMessage(), 404);
		}
	}
}