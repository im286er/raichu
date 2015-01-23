<?php
namespace vakata\database\orm;

use vakata\database\DatabaseInterface;

interface TableInterface
{
	public function getTable();
	public function getColumns();
	public function getPrimaryKey();

	public function getDatabase();
	public function getRelations();
	public function getDefinition();

	public function filter($sql, array $par = []);
	
	public function cnt();
	public function get($order = null, $limit = null, $offset = null);
	public function all($order = null, $limit = null, $offset = null);
	public function one($id = null, $order = null, $offset = null);

	public function insert(array $data);
	public function update(array $data);
	public function delete();

	public function hasOne($tb, $key = null, $field = null);
	public function hasMany($tb, $key = null, $field = null);
	public function belongsTo($tb, $key = null, $field = null);
	public function manyToMany($tb, $pivot = null, $field = null);
}