<?php
namespace vakata\route;

class Route
{
	protected $routes = [];
	protected $all = null;
	protected $err = null;
	protected $ran = false;
	protected $prefix = '';
	protected $preprocessors = [];

	protected function compile($url, $full = true) {
		$url = array_filter(explode('/',trim($url, '/')), function ($v) { return $v !== ''; });
		if(!count($url)) {
			return '(^/+$)ui';
		}
		$url = '(^/' . implode('', array_map([$this, 'compileSegment'], $url)) . ($full ? '$' : '') .')';
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
	protected function invoke(callable $handler, array $matches = null, \vakata\http\RequestInterface $req = null, \vakata\http\ResponseInterface $res = null, \vakata\http\UrlInterface $url = null, \Exception $e = null) {
		return call_user_func($handler, $matches, $req, $res, $url, $e);
	}

	public function with($prefix = '', callable $handler = null) {
		$prefix = trim($prefix, '/');
		$this->prefix = $prefix . (strlen($prefix) ? '/' : '');
		if(isset($handler)) {
			$prefix = $this->compile($prefix, false);
			if(!isset($this->preprocessors[$prefix])) {
				$this->preprocessors[$prefix] = [];
			}
			$this->preprocessors[$prefix][] = $handler;
		}
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

		if(!$url && $this->prefix) {
			$url = $this->prefix;
		}
		else {
			if(!$url) {
				$url = '{**}';
			}
			$url = $this->prefix . $url;
		}

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
	public function isRun() {
		return $this->ran;
	}
	public function isEmpty() {
		return $this->all === null && $this->err === null && count($this->routes) === 0;
	}

	public function run(\vakata\http\UrlInterface $url, \vakata\http\RequestInterface $req, \vakata\http\ResponseInterface $res) {
		if($this->isRun() || $this->isEmpty()) {
			return;
		}
		$this->ran = true;
		$request = str_replace('//', '/', '/'.trim($url->request(false), '/').'/');
		$matches = [];
		try {
			foreach($this->preprocessors as $regex => $proc) {
				if(preg_match($regex, $request, $matches)) {
					$arg = explode('/',trim($request, '/'));
					foreach($matches as $k => $v) {
						if(!is_int($k)) {
							$arg[$k] = trim($v,'/');
						}
					}
					foreach($proc as $h) {
						if($this->invoke($h, $arg, $req, $res, $url) === false) {
							return false;
						}
					}
				}
			}
			$arg = explode('/',trim($request, '/'));
			if(isset($this->all)) {
				return $this->invoke($this->all, $arg, $req, $res, $url);
			}
			if(isset($this->routes[$req->getMethod()])) {
				foreach($this->routes[$req->getMethod()] as $regex => $route) {
					if(preg_match($regex, $request, $matches)) {
						foreach($matches as $k => $v) {
							if(!is_int($k)) {
								$arg[$k] = trim($v,'/');
							}
						}
						return $this->invoke($route, $arg, $req, $res, $url);
					}
				}
			}
			throw new RouteException('No matching route found', 404);
		}
		catch (\Exception $e) {
			$res->removeHeaders();
			$res->setBody(null);
			// while(ob_get_level()) { ob_end_clean(); }

			if(isset($this->err)) {
				return $this->invoke($this->err, $arg, $req, $res, $url, $e);
			}

			@error_log('PHP Exception:' . ((int)$e->getCode() ? ' ' . $e->getCode() . ' -' : '') . ' ' . $e->getMessage() . ' in '.$e->getFile().' on line '.$e->getLine());

			$res->setStatusCode($e->getCode() >= 200 && $e->getCode() <= 503 ? $e->getCode() : 500);
			switch($req->getResponseFormat()) {
				case 'json':
					$res->setContentType('json');
					$res->setBody(json_encode($e->getMessage()));
					break;
				case 'xml':
					$res->setContentType('xml');
					echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n\n";
					echo '<error><![CDATA['.str_replace(']]>', '', $e->getMessage()).']]></error>';
					break;
				case 'html':
					$res->setContentType('html');

					echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Грешка</title></head><body style="background:#ebebeb;">';
					echo '<h1 style="font-size:1.4em; text-align:center; margin:2em 0 0 0; color:#8b0000; text-shadow:1px 1px 0 white;">В момента не можем да обслужим заявката Ви.</h1>' . "\n\n";

					if(defined('DEBUG') && DEBUG) {
						echo '<h2 style="color:#222; font-size:1.2em; margin:1em 0 1em 0; text-align:center; text-shadow:1px 1px 0 white;">' . preg_replace('/, called in.*/','',$e->getMessage()) . '</h2>';
						echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding-bottom:1em; overflow:hidden;">';
						echo '<strong style="display:block; background:#ebebeb; text-align:center; border-bottom:1px solid silver; line-height:2em; margin-bottom:1em;">'.(@$e->getFile()).' : '.(@$e->getLine()).'</strong>';
						$file = @file($e->getFile());
						$line = (int)@$e->getLine() - 1;
						if($file && $line) {
							for($i = max($line - 5, 0); $i < max($line - 5, 0) + 11; $i++) {
								if(!isset($file[$i])) { break; }
								echo '<div style="padding:0 1em; line-height:2em; '. ($line === $i ? 'background:lightyellow; position:relative; color:#8b0000; font-weight:bold; box-shadow:0 0 2px rgba(0,0,0,0.7)' : 'background:'.($i%2 ? '#ebebeb' : 'white').';') . '">';
								echo '<strong style="float:left; width:40px;">' . ($i + 1). '. </strong> ' . htmlspecialchars(trim($file[$i],"\r\n")) . "\n";
								echo '</div>';
							}
						}
						echo '</pre>';
						echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding-bottom:1em; overflow:hidden;">';
						echo '<strong style="display:block; background:#ebebeb; text-align:center; border-bottom:1px solid silver; line-height:2em; margin-bottom:1em;">Trace</strong>';
						foreach($e->getTrace() as $k => $trace) {
							if($k === 0) {
								$trace['file'] = @$e->getFile();
								$trace['line'] = @$e->getLine();
							}
							echo '<p style="margin:0; padding:0 1em; line-height:2em; '.($k==0?'background:lightyellow; border-top:1px solid gray; border-bottom:1px solid gray;':'').' '.($k%2 == 1 ? 'background:#ebebeb' : '').'">';
							echo '<span style="display:inline-block; min-width:550px;"><code style="color:green">'.(isset($trace['file'])?$trace['file']:'').'</code>';
							echo '<code style="color:gray"> '.(isset($trace['file'])?':':'').' </code>';
							echo '<code style="color:#8b0000">'.(isset($trace['line'])?$trace['line']:'').'</code></span> ';
							if(isset($trace['class'])) {
								echo '<code style="color:navy">'.$trace['class'].$trace['type'].$trace['function'].'()</code>';
							}
							else {
								echo '<code style="color:navy">'.$trace['function'].'()</code>';
							}
							echo '</p>';
						}
						echo '</pre>';
					}
					break;
				case 'text':
				default:
					$res->setContentType('txt');
					echo $e->getMessage();
					break;
			}
		}
	}
}