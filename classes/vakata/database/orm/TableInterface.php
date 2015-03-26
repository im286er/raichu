<?php
namespace vakata\database\orm;

use vakata\database\DatabaseInterface;

interface TableInterface extends \Iterator, \ArrayAccess, \JsonSerializable
{
	public function getTableName();
	public function getColumns();
	public function getIndexed();
	public function getPrimaryKey();
	public function getDefinition();

	public function getDatabase();

	public function getRelations();
	public function getRelationKeys();
	public function hasOne($tb, $key = null, $field = null);
	public function hasMany($tb, $key = null, $field = null);
	public function belongsTo($tb, $key = null, $field = null);
	public function manyToMany($tb, $pivot = null, $field = null);

	public function search($term);
	public function filter($sql, array $params = []);
	public function reset();
	public function select($order = null, $limit = 0, $offset = 0, array $fields = null);
	public function read($settings = null);
	public function count();

	public function toArray($full = true);

	public function save(array $data = []);
	public function delete();
}