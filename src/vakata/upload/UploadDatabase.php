<?php
namespace vakata\upload;

use vakata\file\FileDatabase;
use vakata\database\DatabaseInterface;

class UploadDatabase extends Upload
{
	protected $dr = null;
	protected $db = null;
	protected $tb = null;

	public function __construct(DatabaseInterface $db, $tb = 'uploads') {
		$this->db = $db;
		$this->tb = $tb;
	}
	public function upload($needle, $chunk = 0, $chunks = 0) {
		$name = $this->getName($needle);

		if ($chunks > 0 && $chunk > 0) {
			$data = $this->db->one(
				'SELECT id, data FROM '.$this->tb.' WHERE new LIKE ? AND uploaded > ? ORDER BY uploaded DESC',
				[ $name . '%', date('Y-m-d H:i:s', time() - 24 * 3600) ]
			);
			if (!$data) {
				throw new UploadException('Could not merge chunk', 500);
			}
			$data['data'] .= file_get_contents($_FILES[$needle]['tmp_name']);
			try {
				$this->db->query(
					'UPDATE '.$this->tb.' SET size = ?, uploaded = ?, hash = ?, data = ? WHERE id = ?',
					array(
						strlen($data['data']),
						date('Y-m-d H:i:s'),
						md5($data['data']),
						$data['data'],
						$data['id']
					)
				);
				$id = $data['id'];
			}
			catch (\Exception $e) {
				throw new UploadException('Could not store uploaded file in database', 500);
			}
		}
		else {
			$suff = 0;
			do {
				$full = $name . '.' . $suff;
			} while (
				$this->db->one(
					'SELECT 1 FROM '.$this->tb.' WHERE new = ? AND uploaded > ?',
					[ $full, date('Y-m-d H:i:s', time() - 24 * 3600) ]
				) && ++$suff < 1000000
			);
			try {
				$data = file_get_contents($_FILES[$needle]['tmp_name']);
				$id = $this->db->query(
					'INSERT INTO '.$this->tb.' (name, new, ext, size, uploaded, hash, settings, data) VALUES (?,?,?,?,?,?,?,?)',
					array(
						implode('.', array_slice(explode('.', $name . '.' . $suff), 1, -2)),
						$name . '.' . $suff,
						array_slice(explode('.', $name), -2, 1)[0],
						strlen($data),
						date('Y-m-d H:i:s'),
						md5($data),
						'',
						$data
					)
				)->insertId();
			}
			catch (\Exception $e) {
				throw new UploadException('Could not store uploaded file in database', 500);
			}
		}
		return new FileDatabase($id, $this->db, $this->tb);
	}
}
