<?php
namespace vakata\http;

class Request implements RequestInterface
{
	use \vakata\user\TraitLogData;

	protected $http = '1.1';
	protected $body = null;
	protected $head = [];
	protected $meth = 'GET';

	protected $approot		= false;
	protected $webroot		= false;
	protected $request		= false;
	protected $server		= false;
	protected $segments		= false;
	protected $extension	= false;
	protected $domain		= false;

	public function __construct() {
		$this->http = @trim(array_pop(explode('/', isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.1')));
		$this->body = @file_get_contents('php://input');
		$this->meth = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

		$headers = [];
		if(function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
		}
		foreach($_SERVER as $key => $val) {
			if(strncmp($key, 'HTTP_', 5) === 0) {
				$headers[$key] = $val;
			}
		}
		foreach($headers as $key => $value) {
			$this->head[$this->cleanHeaderName($key)] = $value;
		}

		$this->approot		= defined('APPROOT') ? APPROOT : getcwd();
		$this->webroot		= preg_replace('@/+@','/','/'.str_replace('\\',"/",str_replace(str_replace(array('\\','/'), DIRECTORY_SEPARATOR, trim($_SERVER['DOCUMENT_ROOT'],'/\\')), '', $this->approot)).'/');
		$this->request		= htmlentities(trim(preg_replace(array('(^'.preg_quote($this->webroot).')ui','(\?'.preg_quote($_SERVER['QUERY_STRING']).'$)ui'),'',$_SERVER['REQUEST_URI']),'/'));
		$this->server		= 'http' . ( !empty($_SERVER['HTTPS']) ? 's' : '' ) . '://' . htmlentities($_SERVER['SERVER_NAME']);
		$this->segments		= array_filter(explode('/', $this->request), function ($var) { return $var !== ''; });
		$this->extension	= strpos($this->request,'.') ? substr($this->request, strrpos($this->request, '.') + 1) : '';
		$this->domain		= trim(preg_replace('@^www\.@', '', htmlentities($_SERVER['SERVER_NAME'])),'/');
		if(!preg_match('@^[a-z0-9]{2,4}$@i', $this->extension)) { $this->extension = ''; }
	}
	protected function cleanHeaderName($name) {
		if(strncmp($name, 'HTTP_', 5) === 0) {
			$name = substr($name, 5);
		}
		$name = str_replace('_', ' ', strtolower($name));
		$name = str_replace('-', ' ', strtolower($name));
		$name = str_replace(' ', '-', ucwords($name));
		return $name;
	}
	protected function cleanValue($value, $mode = null) {
		if(is_array($value)) {
			$temp = [];
			foreach($value as $k => $v) {
				$temp[$k] = $this->cleanValue($v);
			}
			return $temp;
		}
		// remove magic quotes
		if(get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}
		// normalize newlines
		if(strpos($value, "\r") !== false) {
			$value = str_replace(array("\r\n", "\r", "\r\n\n"), PHP_EOL, $value);
		}
		// remove invalid utf8 chars
		if(preg_match('/[^\x00-\x7F]/S', $value) != 0) {
			$value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
		}
		// remove non-printable chars
		do {
			$count = 0;
			$value = preg_replace(array('/%0[0-8bcef]/', '/%1[0-9a-f]/', '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'), '', $value, -1, $count);
		} while($count);

		switch($mode) {
			case 'int':		$value = (int)$value;													break;
			case 'float':	$value = (float)$value;													break;
			case 'nohtml':	$value = strip_tags((string)$value);									break;
			case 'escape':	$value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE);	break;
			case 'string':	$value = (string)$value;												break;
			case 'raw':
			default:		$value = $value;														break;
		}
		return $value;
	}
	protected function getValue(array $collection, $key, $default, $mode) {
		if($key === null) {
			return $this->cleanValue($collection, $mode);
		}
		return isset($collection[$key]) ? $this->cleanValue($collection[$key], $mode) : $default;
	}

	public function getProtocolVersion() {
		return $this->http;
	}
	public function getBody() {
		return $this->body !== false ? $this->body : null;
	}
	public function getHeaders() {
		return $this->head;
	}
	public function hasHeader($header) {
		return isset($this->head[$this->cleanHeaderName($header)]);
	}
	public function getHeader($header) {
		return isset($this->head[$this->cleanHeaderName($header)]) ? $this->head[$this->cleanHeaderName($header)] : null;
	}
	public function getMethod() {
		return $this->meth;
	}

	public function getUrl($withQuery = true) {
		return $this->server.$this->webroot.$this->request.( $withQuery && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '' );
	}
	public function getUrlSegments() {
		return $this->segments;
	}
	public function getUrlSegment($i, $stripExtension = false) {
		$i = (int)$i;
		if($i < 0) { $i = count($this->segments) + $i; }
		$seg = isset($this->segments[$i]) ? urldecode($this->segments[$i]) : null;
		return $seg === null || !$stripExtension || !strlen($this->extension) ? $seg : preg_replace('@\.'.preg_quote($this->extension).'$@ui', '', $seg);
	}
	public function getUrlExtension($default = null) {
		return $this->extension === '' ? $default : $this->extension;
	}
	public function getUrlRoot() {
		return $this->webroot;
	}
	public function getUrlBase() {
		return $this->server.$this->webroot;
	}
	public function getUrlRequest($ext = true) {
		return $ext || !strlen($this->extension) ? $this->request : preg_replace('@\.'.preg_quote($this->extension).'$@ui','',$this->request);
	}
	public function getUrlServer() {
		return $this->server;
	}
	public function getUrlDomain() {
		return $this->domain;
	}

	public function getServer($key = null, $default = null, $mode = null) {
		return $this->getValue($_SERVER, $key, $default, $mode);
	}
	public function getCookie($key = null, $default = null, $mode = null) {
		return $this->getValue($_COOKIE, $key, $default, $mode);
	}
	public function getQuery($key = null, $default = null, $mode = null) {
		return $this->getValue($_GET, $key, $default, $mode);
	}
	public function getPost($key = null, $default = null, $mode = null) {
		$data = strpos((string)$this->getHeader('Content-Type'), 'json') !== false ? @json_decode($this->body, true) : $_POST;
		return $data !== false && $data !== null ? $this->getValue($data, $key, $default, $mode) : null;
	}
	public function getParams($key = null, $default = null, $mode = null) {
		$data = [];
		if(strpos((string)$this->getHeader('Content-Type'), 'json') !== false) {
			$data = @json_decode($this->body, true);
		}
		else {
			@parse_str($this->body, $data);
		}
		return $data !== false && $data !== null ? $this->getValue($data, $key, $default, $mode) : null;
	}
	public function getRequest($key = null, $default = null, $mode = null) {
		return $this->getValue($_REQUEST, $key, $default, $mode);
	}

	public function getFile($key = null) {
		return $this->getValue($_FILES, $key);
	}

	public function isAjax() {
		return ($this->getHeader('X-Requested-With') === 'XMLHttpRequest');
	}
	public function isCors() {
		return ($this->hasHeader('Origin') && !$this->hasHeader('X-Requested-With') && (!isset($_SERVER['SERVER_NAME']) || !$_SERVER['SERVER_NAME'] || strpos(parse_url($this->getHeader('Origin'), PHP_URL_HOST), $_SERVER['SERVER_NAME']) === false));
	}
	public function isSecure() {
		if(isset($_SERVER) && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
			return true;
		}
		if(!isset($_SERVER) || !isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off') {
			return false;
		}
		return true;
	}
	public function isSelf() {
		return (
			(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1') || 
			(isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['SERVER_ADDR']) && $_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR'])
		);
	}

	public function getResponseFormat($default = 'html') {
		// parse accept header (uses default instead of 406 header)
		$acpt = $this->extension ? 'application/' . $this->extension : ($this->getHeader('Accept') ?: 'application/' . $default);
		$acpt = explode(',', $acpt);
		foreach($acpt as $k => $v) {
			$v = array_pad(explode(';', $v, 2), 2, 'q=1');
			$v[1] = (float)array_pad(explode('q=', $v[1], 2), 2, '1')[1];
			$v[0] = explode('+', array_pad(explode('/', $v[0], 2), 2, 'json')[1])[0];
			$v[2] = $k;
			$acpt[$k] = $v;
		}
		uasort($acpt, function ($a, $b) {
			if($a[1] > $b[1]) { return -1; }
			if($a[1] < $b[1]) { return 1; }
			return $b[2] > $b[1] ? -1 : 1;
		});
		return $acpt[0][0];
	}
	public function getAuthorization() {
		if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			return [ 'username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW'] ];
		}
		if($this->hasHeader('Authorization')) {
			$temp = explode(' ', $this->getHeader('Authorization'));
			switch(strtolower($temp[0])) {
				case 'basic':
					$temp[1] = base64_decode($temp[1]);
					$temp[1] = explode(':', $temp[1], 2);
					return ['username' => $temp[1][0], 'password' => $temp[1][1]];
				case 'token':
				case 'oauth':
				case 'bearer':
					return ['token' => $temp[1]];
			}
		}
		return null;
	}
	public function checkCSRF() {
		// csrf (allow for AJAX & CORS)
		// ajax may not be secure: http://lists.webappsec.org/pipermail/websecurity_lists.webappsec.org/2011-February/007533.html
		if(!$this->isAjax() && !$this->hasHeader('Origin')) {
			if(isset($_SESSION['_csrf_token']) && isset($_POST) && count($_POST) > 0) {
				if(!isset($_POST['_csrf_token']) || $_POST['_csrf_token'] != $_SESSION['_csrf_token']) {
					throw new \Exception('CSRF check fail', 403);
				}
				unset($_POST['_csrf_token']);
			}
			if(!isset($_SESSION['_csrf_token'])) {
				$_SESSION['_csrf_token'] = md5(uniqid(rand(), TRUE));
			}
		}
	}
}