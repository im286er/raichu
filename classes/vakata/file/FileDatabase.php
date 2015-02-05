<?php
namespace vakata\file;

class FileDatabase extends AbstractFile
{
	public function __construct($id, \vakata\database\DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct();
		$temp = $db->one('SELECT * FROM '.$tb.' WHERE id = ?', [ (int)$id ]);
		if(!$temp || !is_file($temp['new']) || !is_readable($temp['new'])) {
			throw new FileException('File not found', 404);
		}
		$this->data['id']			= $temp['id'];
		$this->data['name']			= $temp['name'];
		$this->data['location']		= $temp['new'];
		$this->data['extension']	= $temp['ext'];
		$this->data['size']			= (int)$temp['size'];
		$this->data['modified']		= (int)strtotime($temp['uploaded']);
		$this->data['hash']			= $temp['hash'];
		$this->data['settings']		= $temp['settings'];
	}
}