<?php
namespace vakata\upload;

use vakata\file\FileUpload;

class UploadDisk extends Upload implements UploadInterface
{
	protected $dir = null;

	public function __construct($dir) {
		$this->dir = $dir;
	}

	protected function check($needle) {
		parent::check($needle);

		if (!is_dir($this->dir) && !mkdir($this->dir, 0777, true)) {
			throw new UploadException('Could not create upload directory.', 500);
		}
		if (!is_writable($this->dir) && !chmod($this->dir, 0777)) {
			throw new UploadException('Upload directory not writable.', 500);
		}
	}

	protected function getName($needle, $fixed = true, $chunk = 0, $chunks = 0) {
		$name = $_FILES[$needle]['name'] === 'blob' && isset($_POST["name"]) ?
			$_POST["name"] :
			$_FILES[$needle]['name'];
		$name = basename($name);

		if ($fixed) {
			$prefix = md5($chunk . '/' . $chunks . '/' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . '/' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
			$dotp = strrpos($name, ".");
			if ($dotp === false) {
				$extn = '';
				$temp = $name;
			}
			else {
				$extn = substr($name, $dotp);
				$temp = substr($name, 0, $dotp);
			}
			$cntr = mb_strlen($temp);
			do {
				$name = substr($prefix, 0, 10) . '.' . urlencode(mb_substr($temp, 0, $cntr--)) . urlencode($extn) . '.up';
			} while (strlen($name) >= 200); // can not use 255 - allow 8 symbols for counter
		}
		return $name;
	}

	public function upload($needle, $chunk = 0, $chunks = 0) {
		$this->check($needle);

		$prefix = date('Y/m/d');
		$name = $this->getName($needle, true, $chunk, $chunks);

		// just in case chunked upload spans over two days
		if ($chunk && !file_exists($this->dir . '/' . $prefix . '/' . $name . '.0')) {
			$prefix = date('Y/m/d', strtotime('yesterday'));
			if (!file_exists($this->dir . '/' . $prefix . '/' . $name . '.0')) {
				throw new UploadException('Could not merge chunk', 500);
			}
		}

		if (!is_dir($this->dir . '/' . $prefix) && !mkdir($this->dir . '/' . $prefix, 0777, true)) {
			throw new UploadException('Could not create upload directory.', 500);
		}

		$suff = 0;
		do {
			$full = $this->dir . '/' . $prefix . '/' . $name . '.' . $suff;
		} while (file_exists($full) && ++$suff < 1000000);

		if ($chunk) {
			$full = $prefix . '/' . $name . '.' . ($suff - 1);
			if (!move_uploaded_file($_FILES[$needle]['tmp_name'], $this->dir . '/' . $full . '.chunk')) {
				throw new UploadException('Could not merge chunk', 500);
			}
			file_put_contents($this->dir . '/' . $full, file_get_contents($this->dir . '/' . $full . '.chunk'), FILE_APPEND);
			unlink($this->dir . '/' . $full . '.chunk');
		}
		else {
			$full = $prefix . '/' . $name . '.' . $suff;
			if (!move_uploaded_file($_FILES[$needle]['tmp_name'], $this->dir . '/' . $full)) {
				throw new UploadException('Could not move uploaded file', 404);
			}
			@chmod($this->dir . '/' . $full, 0644);
		}
		return new FileUpload($full, $this->dir);
	}
}
