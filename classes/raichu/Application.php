<?php
namespace raichu;

use \vakata\view\View;

class Application
{
	use TraitPermission {
		isUser as public;
		isAdmin as public;
		hasPermission as public;
	}

	protected $slug = '';
	protected $clss = null;
	protected $view = null;
	protected $conf = null;
	
	public function __construct($clss, $slug = '', $view = null, array $conf = []) {
		$this->clss = $clss;
		$this->conf = $conf;
		$this->slug = strlen(trim($slug, '/')) ? trim($slug, '/') . '/' : '';
		if($view) {
			$this->view = realpath($view);
			if(!$this->view) {
				throw new \Exception('Invalid view dir');
			}
		}
	}

	public function slug() {
		return trim($this->slug, '/');
	}

	public function url($req = '', array $params = null) {
		if(strpos($req, '//') == false) {
			if(!isset($req[0]) || $req[0] !== '/') {
				$req = raichu::request()->getUrlRoot() . $this->slug . $req;
			}
			$req = array_map('urlencode',explode('/',trim($req,'/')));
			foreach($req as $k => $v) {
				if($v == '..' && $k) { unset($req[$k - 1]); }
				else if($v == '.' || $v == '..') { unset($req[$k]); }
			}
			$req = raichu::request()->getUrlServer() . '/' . implode('/', $req);
		}
		if($params) {
			$params = http_build_query($params);
			$req = $req . '?' . $params;
		}
		return $req;
	}

	public function config($key) {
		$key = explode('.', $key);
		$tmp = $this->conf;
		foreach($key as $k) {
			if(!isset($tmp[$k])) {
				return null;
			}
			$tmp = $tmp[$k];
		}
		return $tmp;
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
	public function render($view, array $data = []) {
		if(!$this->viewExists($view)) {
			throw new \Exception('View not readable');
		}
		$view = $this->normalizeView($view);
		$inst = new View($view, $data);
		$inst->add('app', $this);
		return $inst->render();
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
		$method = explode('.', $method)[0];
		if(!is_callable([$temp, $method])) {
			throw new \Exception('Method not found', 404);
		}
		$content = $temp->{$method}($data);
		return $content;
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