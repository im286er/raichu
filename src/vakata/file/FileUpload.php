<?php
namespace vakata\file;

class FileUpload extends FileDisk
{
	public function __construct($path, $directory) {
		$directory = realpath($directory . DIRECTORY_SEPARATOR . $path);
		if (!$directory || !is_file($directory) || !is_readable($directory)) {
			throw new FileException('File not found', 404);
		}
		parent::__construct($directory);
		$this->data['id'] = $path;
		$this->data['name'] = urldecode(implode('.', array_slice(explode('.', $this->data['name']), 1, -2)));
		$this->data['extension'] = substr($this->data['name'], strrpos($this->data['name'], ".") + 1);
	}
}
