<?php
namespace vakata\route;

class Route
{
	protected $routes = [];
	protected $all = null;
	protected $err = null;
	protected $prefix = '';

	protected function compile($url) {
		$url = array_filter(explode('/',trim($url, '/')), function ($v) { return $v !== ''; });
		if(!count($url)) {
			return '(^/+$)ui';
		}
		$url = '(^/' . implode('', array_map([$this, 'compileSegment'], $url)) . '$)';
		if(@preg_match($url, '') === false) {
			throw new RouteException('Could not compile route regex');
		}
		return $url;
	}
	protected function compileSegment($url) {
		$all = preg_match('(^\{[^\}]+\}$)', $url);
		if(!preg_match('(([^{]*)\{([^}]+)\}([^{]*))i', $url)) {
			return '(' . preg_quote($url) . ')/';
		}
		$url = preg_replace_callback(
			'(([^{]*)\{([^}]+)\}([^{]*))i', 
			function ($matches) use ($all) {
				$optional = $matches[2][0] === '?';
				if($optional) {
					$matches[2] = substr($matches[2], 1);
				}
				$matches[2] = explode(':', $matches[2], 2);
				if(count($matches[2]) === 1) {
					$matches[2] = in_array($matches[2][0], ['a','i','h','*','**']) || !preg_match('(^[a-z]+$)', $matches[2][0]) ? 
						[$matches[2][0], ''] : 
						['*', $matches[2][0]];
				}
				list($regex, $group) = $matches[2];
				switch($regex) {
					case 'i':
						$regex = '[0-9]+';
						break;
					case 'a':
						$regex = '[A-Za-z]+';
						break;
					case 'h':
						$regex = '[A-Za-z0-9]+';
						break;
					case '*':
						$regex = '[^/]+';
						break;
					case '**':
						$regex = '[^/]+';
						$regex = '.*';
						break;
					default:
						$regex = $regex;
						break;
				}
				$regex = '(' . ( strlen($group) ? '?P<'.preg_quote($group).'>' : '' ) . $regex . ')';
				if(!$all) {
					$regex = $optional ? $regex . '?' : $regex;
				}
				else {
					$regex = $optional ? '(' . $regex . '/)?' : $regex . '/';
				}
				return preg_quote($matches[1]) . $regex . preg_quote($matches[3]);
			},
			$url
		);
		return $url;
	}
	protected function invoke(callable $handler, array $matches = null, \vakata\http\RequestInterface $req = null, \vakata\http\ResponseInterface $res = null, \Exception $e = null) {
		return call_user_func($handler, $matches, $req, $res, $e);
	}

	public function with($prefix = '') {
		$prefix = trim($prefix, '/');
		$this->prefix = $prefix . (strlen($prefix) ? '/' : '');
		return $this;
	}
	public function add($method, $url = null, $handler = null) {
		$args    = func_get_args();
		$handler = array_pop($args);
		$url     = array_pop($args);
		$method  = array_pop($args);

		if(!$method && (is_array($url) || in_array($url, ['GET','HEAD','POST','PATCH','DELETE','PUT','OPTIONS']))) {
			$method = $url;
			$url = null;
		}

		if(!$url) {
			$url = '{**}';
		}
		$url     = $this->prefix . $url;

		if(!$method) {
			$method = ['GET','POST'];
		}
		if(!is_array($method)) {
			$method = [ $method ];
		}
		if(!is_callable($handler)) {
			throw new RouteException('No valid handler found');
		}

		$url = $this->compile($url);
		foreach($method as $m) {
			if(!isset($this->routes[$m])) {
				$this->routes[$m] = [];
			}
			$this->routes[$m][$url] = $handler;
		}
		return $this;
	}
	public function get($url, callable $handler) {
		return $this->add('GET', $url, $handler);
	}
	public function post($url, callable $handler) {
		return $this->add('POST', $url, $handler);
	}
	public function head($url, callable $handler) {
		return $this->add('HEAD', $url, $handler);
	}
	public function put($url, callable $handler) {
		return $this->add('PUT', $url, $handler);
	}
	public function patch($url, callable $handler) {
		return $this->add('PUT', $url, $handler);
	}
	public function delete($url, callable $handler) {
		return $this->add('DELETE', $url, $handler);
	}
	public function options($url, callable $handler) {
		return $this->add('OPTIONS', $url, $handler);
	}
	public function all(callable $handler) {
		$this->all = $handler;
		return $this;
	}
	public function error(callable $handler) {
		$this->err = $handler;
		return $this;
	}

	public function run(\vakata\http\RequestInterface $req, \vakata\http\ResponseInterface $res) {
		$url = '/'.trim(str_replace($req->getUrlBase(), '', $req->getUrl(false)), '/').'/';
		$arg = explode('/',trim($url, '/'));
		try {
			if(isset($this->all)) {
				return $this->invoke($this->all, $arg, $req, $res);
			}
			if(isset($this->routes[$req->getMethod()])) {
				foreach($this->routes[$req->getMethod()] as $regex => $route) {
					if(preg_match($regex, $url, $matches)) {
						foreach($matches as $k => $v) {
							if(!is_int($k)) {
								$arg[$k] = trim($v,'/');
							}
						}
						return $this->invoke($route, $arg, $req, $res);
					}
				}
			}
			throw new RouteException('No matching route found', 404);
		}
		catch (\Exception $e) {
			if(isset($this->err)) {
				return $this->invoke($this->err, $arg, $req, $res, $e);
			}
			throw $e;
		}
	}
}