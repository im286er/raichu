<?php
namespace vakata\upload;

use vakata\file\FileDiskDatabase;
use vakata\database\DatabaseInterface;

class UploadDiskDatabase extends UploadDisk
{
	protected $dr = null;
	protected $db = null;
	protected $tb = null;

	public function __construct($dir, DatabaseInterface $db, $tb = 'uploads') {
		parent::__construct($dir);
		$this->dr = realpath($dir);
		$this->db = $db;
		$this->tb = $tb;
	}
	public function upload($needle, $chunk = 0, $chunks = 0) {
		$file = parent::upload($needle, $chunk, $chunks);
		$id = (int)$this->db->one('SELECT id FROM '.$this->tb.' WHERE new = ?', array(str_replace($this->dr . DIRECTORY_SEPARATOR, '', $file->location)));
		try {
			if ($id) {
				$this->db->query(
					'UPDATE '.$this->tb.' SET size = ?, uploaded = ?, hash = ? WHERE id = ?',
					array(
						$file->size,
						date('Y-m-d H:i:s', $file->modified),
						$file->hash,
						$id
					)
				);
			}
			else {
				$this->db->query(
					'INSERT INTO '.$this->tb.' (name, new, ext, size, uploaded, hash, settings, data) VALUES (?,?,?,?,?,?,?,?)',
					array(
						$file->name,
						str_replace($this->dr . DIRECTORY_SEPARATOR, '', $file->location),
						substr($file->name, strrpos($file->name, ".") + 1),
						$file->size,
						date('Y-m-d H:i:s', $file->modified),
						$file->hash,
						'',
						''
					)
				);
				$id = $this->db->insertId();
			}
		}
		catch (\Exception $e) {
			throw new UploadException('Could not store uploaded file in database', 404);
		}
		return new FileDiskDatabase($id, $this->dr, $this->db, $this->tb);
	}
}
