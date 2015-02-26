<?php
namespace raichu;

use \vakata\view\View;

class Application
{
	use TraitPermission;

	protected $slug = '';
	protected $name = null;
	protected $clss = null;
	protected $view = null;
	
	public function __construct($name, $clss, $slug = '', $view = null) {
		$this->name = $name;
		$this->clss = $clss;
		$this->slug = strlen(trim($slug, '/')) ? trim($slug, '/') . '/' : '';
		if($view) {
			$this->view = realpath($view);
			if(!$this->view) {
				throw new \Exception('Invalid view dir');
			}
		}
	}

	public function name() {
		return $this->name;
	}
	public function slug() {
		return trim($this->slug, '/');
	}

	public function url($req = '', array $params = null) {
		if(strpos($req, '//') == false) {
			if($req[0] !== '/') {
				$req = raichu::request()->getUrlRoot() . $this->slug . $req;
			}
			$req = array_map('urlencode',explode('/',trim($req,'/')));
			foreach($req as $k => $v) {
				if($v == '..' && $k) { unset($req[$k - 1]); }
				if($v == '.' || $v == '..') { unset($req[$k]); }
			}
			$req = raichu::request()->getUrlServer() . '/' . implode('/', $req);
		}
		if($params) {
			$params = http_build_query($params);
			$req = $req . '?' . $params;
		}
		return $req;
	}

	protected function normalizeView($view) {
		if($this->view) {
			$view = $this->view . DIRECTORY_SEPARATOR . $view;
		}
		if(!preg_match('(\.php$)i',$view)) {
			$view .= '.php';
		}
		return $view;
	}

	public function viewExists($view) {
		$view = $this->normalizeView($view);
		return is_file($view) && is_readable($view);
	}
	public function render($view, $data = null, $decorate = false) {
		if(!$this->viewExists($view)) {
			throw new \Exception('View not readable');
		}
		$view = $this->normalizeView($view);
		if($decorate) {
			if(!$data) { $data = []; }
			$data['req']  = raichu::request();
			$data['res']  = raichu::response();
			$data['app']  = $this;
			$data['user'] = raichu::user();
		}
		return View::get($view, $data);
	}

	public function instance($instance) {
		$class = null;
		if(is_string($this->clss)) {
			$class = $this->clss . '\\' . $instance;
		}
		if(!$class && is_array($this->clss) && isset($this->clss[$instance])) {
			$class = $this->clss[$instance];
		}
		if(!$class && is_array($this->clss)) {
			foreach($this->clss as $candidate) {
				if(array_reverse(explode('\\', $candidate))[0] === $instance) {
					$class = $candidate;
					break;
				}
			}
		}
		if(!$class) {
			throw new \Exception('Invalid class', 404);
		}
		return raichu::instance($class);
	}
	public function invoke($class, $method = 'index', $data = null) {
		$temp = $this->instance($class);
		if(!is_callable([$temp, $method])) {
			throw new \Exception('Method not found', 404);
		}
		$content = $temp->{$method}($data);
		return $content;
	}

	public function process($class, $method = 'index', $frmt = 'html', $data = null) {
		$data = $this->invoke($class, $method, $data);
		$class = basename(str_replace('\\', '/', $class));
		$view = $class.'.'.$method.'.'.$frmt.'.php';
		if($this->viewExists($view)) {
			return $this->render($view, $data, true);
		}
		$view = $class.'.'.$method.'.php';
		if($this->viewExists($view)) {
			return $this->render($view, $data, true);
		}
		$view = $method.'.'.$frmt.'.php';
		if($this->viewExists($view)) {
			return $this->render($view, $data, true);
		}
		$view = $method.'.php';
		if($this->viewExists($view)) {
			return $this->render($view, $data, true);
		}
		throw new \Exception('No view found');
	}

	public function toXML($data) {
		$data = array('root' => $data);
		function array_to_xml($data, $xml) {
			foreach($data as $k => $v) {
				switch(gettype($v)) {
					case 'NULL':
						$v = 'NULL';
						break;
					case 'boolean':
						$v = $v ? 'TRUE' : 'FALSE';
						break;
					case 'object':
						$v = get_object_vars($v);
						break;
					case 'array':
						break;
					default:
						$v = (string)$v;
						break;
				}
				if(is_array($v)) {
					array_to_xml($v, $xml->addChild(is_numeric($k) ? 'item' : $k));
				}
				else {
					if(is_numeric($k) || preg_match('(^[a-z][a-z0-9_]+$)i', $k)) {
						$xml->addChild(is_numeric($k) ? 'item' : $k, $v);
					}
				}
			}
			return $xml;
		};
		return '<?xml version="1.0" encoding="UTF-8" ?>' . array_to_xml($data, new \SimpleXMLElement('<root/>'))->root->asXML();
	}
	public function toJSON($data) {
		return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}