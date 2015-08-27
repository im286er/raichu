<?php
namespace vakata\file;

class FileDatabase extends AbstractFile
{
	public function __construct($id, $directory, \vakata\database\DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct();
		$temp = $db->one('SELECT * FROM '.$tb.' WHERE id = ?', [ (int)$id ]);
		if (!$temp || !is_file($directory . DIRECTORY_SEPARATOR . $temp['new']) || !is_readable($directory . DIRECTORY_SEPARATOR . $temp['new'])) {
			throw new FileException('File not found', 404);
		}
		$this->data['id']			= $temp['id'];
		$this->data['name']			= $temp['name'];
		$this->data['location']		= realpath($directory . DIRECTORY_SEPARATOR . $temp['new']);
		$this->data['extension']	= $temp['ext'];
		$this->data['size']			= (int)$temp['size'];
		$this->data['modified']		= (int)strtotime($temp['uploaded']);
		$this->data['hash']			= $temp['hash'];
		$this->data['settings']		= $temp['settings'];
	}
}