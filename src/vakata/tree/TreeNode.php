<?php
namespace vakata\tree;

class TreeNode
{
	protected $db = null;
	protected $tb = '';
	protected $id = 0;
	protected $fields = array(
		'id'			=> 'id',
		'left'			=> 'lft',
		'right'			=> 'rgt',
		'level'			=> 'lvl',
		'parent_id'		=> 'pid',
		'position'		=> 'pos'
	);
	protected $data = [];
	protected $a = null;
	protected $c = null;
	protected $d = null;
	protected $p = null;

	public function __construct(\vakata\database\DatabaseInterface $db, $tb, $id, array $fields = [], array $data = null) {
		$this->db = $db;
		$this->tb = $tb;
		$this->id = $id;
		$this->fields = array_merge($this->fields, $fields);
		$this->data = $data ? $data : $this->db->one("SELECT * FROM {$this->tb} WHERE {$this->fields['id']} = ?", [ $this->id ]);
		if (!$this->data) {
			throw new TreeException('Node does not exist', 404);
		}
	}
	public function __get($key) {
		if (isset($this->fields[$key])) {
			$key = $this->fields[$key];
		}
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}
		return null;
	}

	public function parent() {
		if (isset($this->p)) {
			return $this->p;
		}
		return $this->p = new self($this->db, $this->tb, $this->data[$this->fields['parent_id']], $this->fields);
	}
	public function hasChildren() {
		return $this->data[$this->fields['right']] - $this->data[$this->fields['left']] > 1;
	}
	public function childrenCount() {
		return count($this->children());
	}
	public function children() {
		if (isset($this->c)) {
			return $this->c;
		}
		$temp = [];
		foreach ($this->db->all("SELECT * FROM {$this->tb} WHERE {$this->fields['parent_id']} = ? ORDER BY {$this->fields['position']}", [ $this->id ]) as $data) {
			$temp[] = new self($this->db, $this->tb, $data[$this->fields['id']], $this->fields, $data);
		}
		return $this->c = $temp;
	}
	public function descendants() {
		if (isset($this->d)) {
			return $this->d;
		}
		$temp = [];
		foreach ($this->db->all(
			"SELECT * FROM {$this->tb} WHERE {$this->fields['left']} > ? AND {$this->fields['right']} < ? ORDER BY {$this->fields['left']}",
			[ $this->left, $this->right ]
		) as $data) {
			$temp[] = new self($this->db, $this->tb, $data[$this->fields['id']], $this->fields, $data);
		}
		return $this->d = $temp;
	}
	public function ancestors($id) {
		if (isset($this->a)) {
			return $this->a;
		}
		$temp = [];
		foreach ($this->db->all(
			"SELECT * FROM {$this->tb} WHERE {$this->fields['left']} < ? AND {$this->fields['right']} > ? ORDER BY {$this->fields['left']}",
			[ $this->left, $this->right ]
		) as $data) {
			$temp[] = new self($this->db, $this->tb, $data[$this->fields['id']], $this->fields, $data);
		}
		return $this->a = $temp;
	}
}