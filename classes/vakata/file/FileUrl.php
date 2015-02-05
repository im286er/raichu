<?php
namespace vakata\file;

class FileUrl extends AbstractFile
{
	public function __construct($path) {
		$context = stream_context_create(array('http' => array('method' => 'HEAD')));
		$stream = @fopen($path, 'r', false, $context);
		if(!$stream) {
			throw new FileException('Could not open URL', 404);
		}
		$meta = @stream_get_meta_data($stream);
		@fclose($stream);
		if(!$meta || $meta['timed_out'] || !isset($meta['wrapper_data']) || !is_array($meta['wrapper_data'])) {
			throw new FileException('Could not parse meta data from URL', 404);
		}

		$isok = false;
		$size = 0;
		$lmod = 0;
		foreach($meta['wrapper_data'] as $wd) { 
			$wd = strtolower($wd);
			if(strpos($wd, '200 ok') !== false) { $isok = true; }
			if(strpos($wd, 'content-length') !== false) { $size = (int)@trim(end(explode(':', $wd)), ' '); }
			if(strpos($wd, 'last-modified') !== false)  { $lmod = (int)@strtotime(trim(substr($wd,strpos($wd, 'last-modified')+14))); }
		}
		if(!$isok) {
			throw new FileException('Error fetching URL', 404);
		}
		$this->data['id']			= $path;
		$this->data['name']			= basename(preg_replace(array('(\?.*$)','(/$)'), '', $path));
		$this->data['location']		= $path;
		$this->data['extension']	= substr($this->data['name'], strrpos($this->data['name'], ".") + 1);
		$this->data['size']			= $size;
		$this->data['modified']		= $lmod;
		$this->data['hash']			= md5($path);
	}
}