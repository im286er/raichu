<?php
namespace vakata\file;

class FileString extends AbstractFile
{
	protected $body = '';
	public function __construct(&$body, $name = '') {
		parent::__construct();
		$this->body = &$body;
		$hash = md5($this->body);
		$this->data['id']			= $hash;
		$this->data['name']			= basename($name);
		$this->data['location']		= null;
		$this->data['extension']	= strpos($name, '.') !== false ? substr($name, strrpos($name, ".") + 1) : '';
		$this->data['size']			= strlen($this->body);
		$this->data['modified']		= time();
		$this->data['hash']			= $hash;
	}
	public function &content() {
		return $this->body;
	}
}