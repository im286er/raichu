<?php
namespace vakata\file;

class ZipDecorator implements ArchiveInterface
{
	protected $file = null;
	public function __construct(FileInterface $file) {
		$this->file = $file;
	}
	public function id() {
		$this->file->id();
	}
	public function name() {
		$this->file->name();
	}
	public function location() {
		$this->file->location();
	}
	public function extension() {
		$this->file->extension();
	}
	public function modified() {
		$this->file->modified();
	}
	public function size() {
		$this->file->size();
	}
	public function hash() {
		$this->file->hash();
	}
	public function settings() {
		$this->file->settings();
	}
	protected function __get($property) {
		return $this->file->{$property};
	}
	public function &content() {
		return $this->file->content();
	}
	public function listing() {
		try {
			$za = new \ZipArchive();
			if($za->open($this->location()) !== true) {
				return [];
			}
			$tmp = [];
			for($i = 0; $i < $za->numFiles; $i++) {
				$tmp[] = mb_convert_encoding($za->getNameIndex($i), 'UTF-8','CP866');
			}
			return $tmp;
		}
		catch(\Exception $e) {
			return [];
		}
	}
}