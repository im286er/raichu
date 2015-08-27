<?php
namespace vakata\file;

class FileDisk extends AbstractFile
{
	public function __construct($path) {
		$path = realpath($path);
		if (!$path || !is_file($path) || !is_readable($path)) {
			throw new FileException('File not found', 404);
		}
		$this->data['id']			= $path;
		$this->data['name']			= basename($path);
		$this->data['location']		= $path;
		$this->data['extension']	= substr($path, strrpos($path, ".") + 1);
		$this->data['size']			= filesize($path);
		$this->data['modified']		= filemtime($path);
		$this->data['hash']			= md5_file($path);
	}
}