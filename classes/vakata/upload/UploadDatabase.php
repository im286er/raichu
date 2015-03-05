<?php
namespace vakata\upload;

use vakata\file\FileDatabase;
use vakata\database\DatabaseInterface;

class UploadDatabase extends Upload
{
	protected $db = null;
	protected $tb = null;
	public function __construct($dir, DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct($dir);
		$this->db = $db;
		$this->tb = $tb;
	}
	public function upload($needle, $chunk = 0) {
		$file = parent::upload($needle, $chunk);
		$id = (int)$this->db->one('SELECT id FROM '.$this->tb.' WHERE new = ?', array($file->location));
		try {
			if($id) {
				$this->db->query(
					'UPDATE '.$this->tb.' SET size = ?, uploaded = ?, hash = ? WHERE id = ?',
					array(
						$file->size,
						date('Y-m-d H:i:s', $file->modified),
						$file->hash,
						$file->id
					)
				);
			}
			else {
				$this->db->query(
					'INSERT INTO '.$this->tb.' (name, new, ext, size, uploaded, hash) VALUES (?,?,?,?,?,?)',
					array(
						$file->name,
						$file->location,
						$file->extension,
						$file->size,
						date('Y-m-d H:i:s', $file->modified),
						$file->hash
					)
				);
				$id = $this->db->insertId();
			}
		}
		catch (\Exception $e) {
			throw new UploadException('Could not store uploaded file in database', 404);
		}
		return new FileDatabase($id);
	}
}