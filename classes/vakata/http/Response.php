<?php
namespace vakata\http;

class Response implements ResponseInterface
{
	protected $http = '1.1';
	protected $code = 200;
	protected $head = [];
	protected $body = null;
	protected $gzip = true;

	protected $filters = [];

	public function __construct() {
		ob_start();
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

	public function getBody() {
		return $this->body;
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

	public function getProtocolVersion() {
		return $this->http;
	}
	public function setProtocolVersion($version) {
		$this->http = $version;
	}

	public function getStatusCode() {
		return $this->code;
	}
	public function setStatusCode($code) {
		$this->code = $code;
	}

	public function getGzip() {
		return $this->gzip;
	}
	public function setGzip($gzip) {
		$this->gzip = $gzip;
	}

	public function setContentType($type) {
		switch(mb_strtolower($type)) {
			case "txt"  :
			case "text" : $type = "text/plain; charset=UTF-8"; break;
			case "xml"  : 
			case "xsl"  : $type = "text/xml; charset=UTF-8"; break;
			case "json" : $type = "application/json; charset=UTF-8"; break;
			case "pdf"  : $type = "application/pdf"; break;
			case "exe"  : $type = "application/octet-stream"; break;
			case "zip"  : $type = "application/zip"; break;
			case "docx" :
			case "doc"  : $type = "application/msword"; break;
			case "xlsx" :
			case "xls"  : $type = "application/vnd.ms-excel"; break;
			case "ppt"  : $type = "application/vnd.ms-powerpoint"; break;
			case "gif"  : $type = "image/gif"; break;
			case "png"  : $type = "image/png"; break;
			case "jpeg" :
			case "jpg"  : $type = "image/jpg"; break;
			case "html" :
			case "php"  :
			case "htm"  : $type = "text/html; charset=UTF-8"; break;
			default     : return;
		}
		$this->setHeader('Content-Type', $type);
	}
	public function setHeader($header, $value) {
		$this->head[$this->cleanHeaderName($header)] = $value;
		if($this->cleanHeaderName($header) === 'Location') {
			$this->setStatusCode(302);
		}
	}
	public function removeHeader($header) {
		unset($this->head[$this->cleanHeaderName($header)]);
	}
	public function removeHeaders() {
		$this->head = [];
	}

	public function setBody($body) {
		$this->body = $body;
	}

	public function addFilter(callable $f) {
		$this->filters[] = $f;
	}

	public function redirectUrl($url) {
		$this->setHeader('Location', $url);
	}

	public function send() {
		if(!$this->hasHeader('Content-Type')) {
			$this->setContentType('html');
		}
		if($this->body === null) {
			$this->body = ob_get_clean();
		}
		while(ob_get_level() && ob_end_clean()) ;

		// above headers, so that filters can send headers
		if($this->body !== null && strlen($this->body)) {
			$type = explode(';', $this->getHeader('Content-Type'))[0];
			foreach($this->filters as $filter) {
				$this->body = call_user_func($filter, $this->body, $type);
			}
		}

		if(!headers_sent()) {
			http_response_code($this->code);
			foreach($this->head as $key => $header) {
				header($key . ': ' . $header);
			}
		}
		if($this->body !== null && strlen($this->body)) {
			if($this->gzip && !(bool)@ini_get('zlib.output_compression') && extension_loaded('zlib')) {
				ob_start('ob_gzhandler');
			}
			echo $this->body;
		}
		@ob_end_flush();
	}
}