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
		if(!isset($this->data['location'])) {
			throw new FileException('Invalid file', 404);
		}
		$temp = file_get_contents($this->data['location']);
		if($temp === false) {
			throw new FileException('Invalid file', 404);
		}
		return $temp;
	}

	public function serve(\vakata\http\ResponseInterface $r, $file_name = null, $width = null, $height = null, $chunks = false) {
		$extension = $file_name ? substr($file_name, strrpos($file_name, ".") + 1) : $this->extension;
		$file_name = $file_name ? : $this->name;

		if( ((int)$width || (int)$height) && in_array(strtolower($extension), array('png','jpg','gif','jpeg','bmp'))) {
			$width  = min(2048, (int)$width);
			$height = min(2048, (int)$height);
			$cropped_name = $this->location.'_'.(int)$width.'x'.(int)$height;
			if(!is_file($cropped_name) || !is_readable($cropped_name)) {
				if(extension_loaded('imagick')) {
					try { 
						$tmp = new \imagick($this->location);
						if(!$height || !$width) {
							$iw = $tmp->getImageWidth();
							$ih = $tmp->getImageHeight();
							if(!$width)  { $width  = $height / $ih * $iw; }
							if(!$height) { $height = $width  / $iw * $ih; }
						}
						$tmp->cropThumbnailImage((int)$width, (int)$height);
						if(!@$tmp->writeImage($cropped_name)) {
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
				else if(extension_loaded('gd') && function_exists('gd_info')) {
					try {
						$tm = getimagesize($this->location);
						$iw = $tm[0];
						$ih = $tm[1];
						if(!$height || !$width) {
							if(!$width)  { $width  = $height / $ih * $iw; }
							if(!$height) { $height = $width  / $iw * $ih; }
						}
						switch(strtolower($this->extension)) {
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
						switch(strtolower($this->extension)) {
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
			if(is_file($cropped_name) && is_readable($cropped_name)) {
				$file_name		= preg_replace('@\.'.preg_quote($this->extension).'$@i', '_'.$width.'x'.$height.'.'.$this->extension, $file_name);
				$this->location	= $cropped_name;
				$this->size		= filesize($cropped_name);
				$this->modified	= filemtime($cropped_name);
				$this->hash		= md5_file($cropped_name);
				$this->content	= null;
			}
		}

		$r->setGzip(false);
		$expires = 60*60*24*30; // 1 месец
		if($this->modified) {
			$r->setHeader('Last-Modified', gmdate("D, d M Y H:i:s", $this->modified) . ' GMT');
		}
		if($this->hash) {
			$r->setHeader('Etag', $this->hash);
		}
		$r->setHeader('Pragma','public');
		$r->setHeader('Cache-Control','maxage='.$expires);
		$r->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

		// ако клиента има кеширано копие пускаме 304 и не качваме брояча за downloaded
		if(
			($this->modified && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $this->modified) ||
			($this->hash && isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $this->hash)
		) {
			$r->setStatusCode(304);
			return $r;
		}

		// ако получаваме заявка за чънкове (resume/chunk поддръжка чрез HTTP_RANGE) 
		// но само ако имаме размера
		if($chunks && $this->size) {
			if(isset($_SERVER['HTTP_RANGE'])) {
				if(!preg_match('@^bytes=\d*-\d*(,\d*-\d*)*$@', $_SERVER['HTTP_RANGE'])) {
					$r->setHeader(416);
					$r->setHeader('Content-Range','bytes */' . $this->size);
					return $r;
				}
				$range = current(explode(',', substr($_SERVER['HTTP_RANGE'], 6)));
				list($seek_beg, $seek_end) = explode('-', $range, 2);
				$seek_beg = max((int)$seek_beg, 0);
				$seek_end = !(int)$seek_end ? ((int)$this->size - 1) : min((int)$seek_end, ((int)$this->size - 1));
				if ($seek_beg > $seek_end) {
					$this->setHeader(416);
					$this->setHeader('Content-Range', 'bytes */' . $f['size']);
					return $r;
				}
				$r->setHeader(206);
				$r->setHeader($extension);
				$r->setHeader('Accept-Ranges: bytes');
				$r->setHeader('Content-Range: bytes '.$seek_beg.'-'.$seek_end.'/'.$this->size);
				$r->setHeader('Content-Disposition: attachment; filename="'.preg_replace('([^a-z0-9.-]+)i', '_', $file_name).'"; filename*=UTF-8\'\'' . rawurlencode($file_name)); // whitespace and comma in filename trigger error in chrome - ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION
				$r->setHeader('Content-Length: ' . ($seek_end - $seek_beg) . ' '); 

				$fp = @fopen($this->location, 'rb');
				if(!$fp) {
					throw new FileException('File not readable: '.$this->location.'.');
				}
				fseek($fp, $seek_beg);
				$r->setBody(fread($fp, ($seek_end - $seek_beg)));
				fclose($fp);
				return $r;
			}
		}

		// send the whole file
		$r->setStatusCode(200);
		$r->setContentType($extension);
		$r->setHeader('Content-Disposition', ( in_array(strtolower($extension), array('txt','png','jpg','gif','jpeg','html','htm')) ? 'inline' : 'attachment' ).'; filename="'.preg_replace('([^a-z0-9.-]+)i', '_', $file_name).'"; filename*=UTF-8\'\'' . rawurlencode($file_name) . '; size=' . $this->size);
		if($this->size) {
			$r->setHeader('Content-Length', $this->size . ' '); 
		}
		$r->setBody(file_get_contents($this->location));
		return $r;
	}
}