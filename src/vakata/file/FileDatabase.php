<?php
namespace vakata\file;

class FileDatabase extends AbstractFile
{
	public function __construct($id, \vakata\database\DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct();
		$temp = $db->one('SELECT * FROM '.$tb.' WHERE id = ?', [ (int)$id ]);
		if (!$temp) {
			throw new FileException('File not found', 404);
		}
		$this->data['id']			= $temp['id'];
		$this->data['name']			= $temp['name'];
		$this->data['extension']	= $temp['ext'];
		$this->data['size']			= (int)$temp['bytesize'];
		$this->data['modified']		= (int)strtotime($temp['uploaded']);
		$this->data['hash']			= $temp['hash'];
		$this->data['settings']		= $temp['settings'];
		$this->data['data']			= $temp['data'];
		$this->data['location']		= null;
	}
	public function &content() {
		if (strlen($this->data['data'])) {
			return $this->data['data'];
		}
	}
}
