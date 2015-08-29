<?php
namespace vakata\upload;

use vakata\file\FileUpload;

abstract class Upload implements UploadInterface
{
	abstract public function upload($needle, $chunk = 0);

	protected function check($needle) {
		if (!isset($_FILES) || !is_array($_FILES) || !isset($_FILES[$needle])) {
			throw new UploadException('Uploaded file not found');
		}
		if ((int)$_FILES[$needle]['error']) {
			throw new UploadException('File upload error', 404);
		}
		if (!(int)$_FILES[$needle]["size"]) {
			throw new UploadException('File upload invalid filesize', 404);
		}
	}
	protected function getName($needle) {
		$name = $_FILES[$needle]['name'] === 'blob' && isset($_POST["name"]) ?
			$_POST["name"] :
			$_FILES[$needle]['name'];
		$prefix = md5(session_id() . '/' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
		return substr($prefix, 0, 10) . '.' . basename($name) . '.up';
	}

	public function hasFiles() {
		if (!isset($_FILES) || !is_array($_FILES) || !count($_FILES)) {
			return false;
		}
		foreach ($_FILES as $k => $v) {
			if (is_uploaded_file($v['tmp_name'])) {
				return true;
			}
		}
		return false;
	}
	public function maxSize() {
		$size = array(ini_get('upload_max_filesize'), ini_get('post_max_size'));
		foreach ($size as $k => $val) {
			$unit = strtolower($val[strlen($val)-1]);
			$val = (int)$val;
			switch ($unit) {
				case 'g':
					$val *= 1024;
				case 'm':
					$val *= 1024;
				case 'k':
					$val *= 1024;
			}
			$size[$k] = $val;
		}
		return min($size);
	}
}
