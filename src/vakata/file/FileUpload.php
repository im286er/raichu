<?php
namespace vakata\file;

class FileUpload extends FileDisk
{
	public function __construct($path, $directory) {
		$path = realpath($directory . DIRECTORY_SEPARATOR . $path);
		if (!$path || !is_file($path) || !is_readable($path)) {
			throw new FileException('File not found', 404);
		}
		parent::__construct($path);
		$this->data['name'] = implode('.', array_slice(explode('.', $this->data['name']), 1, -2));
	}
}
