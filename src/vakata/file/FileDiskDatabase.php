<?php
namespace vakata\file;

class FileDiskDatabase extends AbstractFile
{
	public function __construct($id, $directory, \vakata\database\DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct();
		$temp = $db->one('SELECT * FROM '.$tb.' WHERE id = ?', [ (int)$id ]);
		if (!$temp) {
			throw new FileException('File not found', 404);
		}
		if(!is_file($directory . '/' . $temp['location']) || !is_readable($directory . '/' . $temp['location'])) {
			throw new FileException('File not found', 404);
		}
		$this->data['id']			= $temp['id'];
		$this->data['name']			= $temp['name'];
		$this->data['extension']	= $temp['ext'];
		$this->data['size']			= (int)$temp['bytesize'];
		$this->data['modified']		= (int)strtotime($temp['uploaded']);
		$this->data['hash']			= $temp['hash'];
		$this->data['settings']		= $temp['settings'];
		$this->data['data']			= '';
		$this->data['location']		= realpath($directory . DIRECTORY_SEPARATOR . $temp['location']);
	}
}
