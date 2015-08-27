<?php
namespace vakata\file;

class AbstractFile implements FileInterface
{
	protected $data = null;
	public function __construct() {
		$this->data = [
			'id'		=> null,
			'name'		=> null,
			'location'	=> null,
			'extension'	=> null,
			'modified'	=> 0,
			'size'		=> 0,
			'hash'		=> null,
			'settings'	=> null
		];
	}
	public function id() {
		$this->__get(__FUNCTION__);
	}
	public function name() {
		$this->__get(__FUNCTION__);
	}
	public function location() {
		$this->__get(__FUNCTION__);
	}
	public function extension() {
		$this->__get(__FUNCTION__);
	}
	public function modified() {
		$this->__get(__FUNCTION__);
	}
	public function size() {
		$this->__get(__FUNCTION__);
	}
	public function hash() {
		$this->__get(__FUNCTION__);
	}
	public function settings() {
		$this->__get(__FUNCTION__);
	}
	public function __get($property) {
		return isset($this->data[$property]) ? $this->data[$property] : null;
	}
	public function &content() {
		if (!isset($this->data['location'])) {
			throw new FileException('Invalid file', 404);
		}
		$temp = file_get_contents($this->data['location']);
		if ($temp === false) {
			throw new FileException('Invalid file', 404);
		}
		return $temp;
	}

	public function serve(\vakata\http\ResponseInterface $res, $file_name = null, $width = null, $height = null, $chunks = false) {
		$extension = $file_name ? substr($file_name, strrpos($file_name, ".") + 1) : $this->extension;
		$file_name = $file_name ? : $this->name;

		if ( ((int)$width || (int)$height) && $this->location && in_array(strtolower($extension), array('png','jpg','gif','jpeg','bmp'))) {
			$width  = min(4096, (int)$width);
			$height = min(4096, (int)$height);
			$cropped_name = $this->location.'_'.(int)$width.'x'.(int)$height;
			if (!is_file($cropped_name) || !is_readable($cropped_name)) {
				if (extension_loaded('imagick')) {
					try {
						$tmp = new \imagick($this->location);
						if (!$height || !$width) {
							$iw = $tmp->getImageWidth();
							$ih = $tmp->getImageHeight();
							if (!$width)  { $width  = $height / $ih * $iw; }
							if (!$height) { $height = $width  / $iw * $ih; }
						}
						$tmp->cropThumbnailImage((int)$width, (int)$height);
						if (!@$tmp->writeImage($cropped_name)) {
							$file_name		= preg_replace('@\.'.preg_quote($this->extension).'$@i', '_'.$width.'x'.$height.'.'.$this->extension, $file_name);
							$this->content	= (string)$tmp;
							$this->size		= mb_strlen($this->content, '8bit');
							$this->hash		= md5($this->content);
						}
						$tmp->destroy();
					} catch(\Exception $ex) {
						throw new FileException('Could not create / read thumbnal', 500);
					}
				}
				elseif (extension_loaded('gd') && function_exists('gd_info')) {
					try {
						$tm = getimagesize($this->location);
						$iw = $tm[0];
						$ih = $tm[1];
						if (!$height || !$width) {
							if (!$width)  { $width  = $height / $ih * $iw; }
							if (!$height) { $height = $width  / $iw * $ih; }
						}
						switch (strtolower($this->extension)) {
							case 'jpeg':
							case 'jpg':
								$si = imagecreatefromjpeg($this->location);
								break;
							case 'gif':
								$si = imagecreatefromgif($this->location);
								break;
							case 'png':
								$si = imagecreatefrompng($this->location);
								break;
							case 'bmp':
								$si = imagecreatefromwbmp($this->location);
								break;
							default:
								return false;
						}
						$mr = max($width / $iw, $height / $ih);
						$tm = imagecreatetruecolor($iw * $mr, $ih * $mr);
						imagecopyresized($tm, $si, 0, 0, 0, 0, $iw * $mr, $ih * $mr, $iw, $ih);
						imagedestroy($si);
						$si = $tm;
						$iw = $iw * $mr;
						$ih = $ih * $mr;

						$di = imagecreatetruecolor($width, $height);
						imagecopyresampled($di, $si, 0, 0, ($iw - $width) / 2, ($ih - $height) / 2, $width, $height, $width, $height);
						switch (strtolower($this->extension)) {
							case 'jpeg':
							case 'jpg':
								imagejpeg($di, $cropped_name, 100);
								break;
							case 'gif':
								imagegif($di, $cropped_name);
								break;
							case 'png':
								imagepng($di, $cropped_name, 0);
								break;
							case 'bmp':
								imagewbmp($di, $cropped_name);
								break;
							default:
								imagedestroy($si);
								imagedestroy($di);
								return false;
						}
						imagedestroy($si);
						imagedestroy($di);
					} catch(\Exception $ex) {
						throw new FileException('Could not create / read thumbnail', 500);
					}
				}
			}
			if (is_file($cropped_name) && is_readable($cropped_name)) {
				$res->file(new FileDisk($cropped_name), $file_name);
				return $res;
			}
		}
		$res->file($this, $file_name);
		return $res;
	}
}