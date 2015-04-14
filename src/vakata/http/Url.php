<?php
namespace vakata\http;

class Url implements UrlInterface
{
	protected $appr = '';
	protected $webr = '';
	protected $reqt = '';
	protected $serv = '';
	protected $segs = [];
	protected $extn = '';
	protected $domn = '';

	public function __construct() {
		$temp = [];

		$this->appr	= defined('APPROOT') ? APPROOT : dirname($_SERVER['SCRIPT_NAME']); // getcwd()
		$this->webr	= preg_replace('@/+@','/','/'.str_replace('\\',"/",str_replace(str_replace(array('\\','/'), DIRECTORY_SEPARATOR, trim($_SERVER['DOCUMENT_ROOT'],'/\\')), '', $this->appr)).'/');
		//$this->reqt	= htmlentities(trim(preg_replace(array('(^'.preg_quote($this->webr).')ui','(\?'.preg_quote($_SERVER['QUERY_STRING']).'$)ui'),'',$_SERVER['REQUEST_URI']),'/'));
		//$this->reqt	= htmlentities(trim(preg_replace(array('(^'.preg_quote($this->webr).')ui'),'',explode('?', $_SERVER['REQUEST_URI'], 2)[0]),'/'));
		$this->reqt	= htmlentities(trim(preg_replace(array('(^'.preg_quote($this->webr).')ui'),'',parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)),'/'));
		$this->serv	= 'http' . ( !empty($_SERVER['HTTPS']) ? 's' : '' ) . '://' . htmlentities($_SERVER['SERVER_NAME']);
		$this->segs	= array_filter(explode('/', $this->reqt), function ($var) { return $var !== ''; });
		$this->extn	= strpos($this->reqt,'.') ? substr($this->reqt, strrpos($this->reqt, '.') + 1) : '';
		$this->domn	= trim(preg_replace('@^www\.@', '', htmlentities($_SERVER['SERVER_NAME'])),'/');
		$this->extn = preg_match('(\.([a-z0-9]{2,4})$)i', $_SERVER['REQUEST_URI'], $temp) ? $temp[1] : '';
	}
	
	public function current($withQuery = true) {
		return $this->serv.$this->webr.$this->reqt.( $withQuery && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '' );
	}
	public function segments() {
		return $this->segs;
	}
	public function segment($i, $stripExtension = false) {
		$i = (int)$i;
		if($i < 0) { $i = count($this->segs) + $i; }
		$seg = isset($this->segs[$i]) ? urldecode($this->segs[$i]) : null;
		return $seg === null || !$stripExtension || !strlen($this->extn) ? $seg : preg_replace('@\.'.preg_quote($this->extn).'$@ui', '', $seg);
	}
	public function extension($default = null) {
		return $this->extn === '' ? $default : $this->extn;
	}
	public function root() {
		return $this->webr;
	}
	public function base() {
		return $this->serv.$this->webr;
	}
	public function request($ext = true) {
		return $ext || !strlen($this->extn) ? $this->reqt : preg_replace('@\.'.preg_quote($this->extn).'$@ui','',$this->reqt);
	}
	public function server() {
		return $this->serv;
	}
	public function domain() {
		return $this->domn;
	}
	public function get($req = '', array $params = null) {
		if(strpos($req, '//') == false) {
			if(!isset($req[0]) || $req[0] !== '/') {
				$req = $this->webr . $req;
			}
			$req = array_map('urlencode',explode('/',trim($req,'/')));
			foreach($req as $k => $v) {
				if($v == '..' && $k) { unset($req[$k - 1]); }
				else if($v == '.' || $v == '..') { unset($req[$k]); }
			}
			$req = $this->serv . '/' . implode('/', $req);
		}
		if($params) {
			$params = http_build_query($params);
			$req = $req . '?' . $params;
		}
		return $req;
	}
}