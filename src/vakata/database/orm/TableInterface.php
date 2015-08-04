<?php
namespace vakata\database\orm;

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
	public function hasOne($to_table, $name = null, $to_table_column = null);
	public function hasMany($to_table, $name = null, $to_table_column = null);
	public function belongsTo($to_table, $name = null, $to_table_column = null);
	public function manyToMany($to_table, $pivot, $name = null, $to_table_column = null, $local_column = null);

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