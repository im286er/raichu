<?php
namespace vakata\upload;

use vakata\file\FileUpload;

class Upload implements UploadInterface
{
	protected $dir = null;
	public function __construct($dir) {
		$this->dir = $dir;
	}
	public function upload($needle, $chunk = 0) {
		if(!isset($_FILES) || !is_array($_FILES) || !isset($_FILES[$needle])) {
			throw new UploadException('Uploaded file not found');
		}
		if((int)$_FILES[$needle]['error']) {
			throw new UploadException('File upload error', 404);
		}
		if(!(int)$_FILES[$needle]["size"]) {
			throw new UploadException('File upload invalid filesize', 404);
		}
		if(!is_dir($this->dir) && !mkdir($this->dir, 0777, true)) {
			throw new UploadException('Could not create upload directory.', 500);
		}
		if(!is_writable($this->dir) && !chmod($this->dir, 0777)) {
			throw new UploadException('Upload directory not writable.', 500);
		}

		$bnm = $_FILES[$needle]['name'] === 'blob' && isset($_POST) && isset($_POST["name"]) ? $_POST["name"] : $_FILES[$needle]['name'];
		$bnm = basename($bnm);
		$fnm = substr(str_replace('/','=',trim(base64_encode(basename($bnm)),"=")), 0, 250); // ntfs / fat32 / exfat max filename length is 255
		$cnt = 0;
		do {
			$new = $this->dir . DIRECTORY_SEPARATOR . $fnm . '_' . $cnt;
		} while(file_exists($new) && ++$cnt < 1000000);
		if($chunk) {
			if(!$cnt) {
				throw new UploadException('Could not merge chunk', 500);
			}
			$new = $this->dir . DIRECTORY_SEPARATOR . $fnm . '_' . ($cnt - 1);
			move_uploaded_file($_FILES[$needle]['tmp_name'], $new . '_chunk');
			file_put_contents($new, file_get_contents($new . '_chunk'), FILE_APPEND);
			unlink($new . '_chunk');
		}
		else {
			if(!move_uploaded_file($_FILES[$needle]['tmp_name'], $new)) {
				throw new UploadException('Could not move uploaded file', 404);
			}
			@chmod($new, 0644);
		}
		return new FileUpload(basename($new), $this->dir);
	}
	public function hasFiles() { 
		return (isset($_FILES) && is_array($_FILES) && count($_FILES));
	}
	public function maxSize() {
		$size = array(ini_get('upload_max_filesize'), ini_get('post_max_size'));
		foreach($size as $k => $val) {
			$unit = strtolower($val[strlen($val)-1]);
			$val = (int)$val;
			switch($unit) {
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