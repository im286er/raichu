<?php
namespace vakata\database\orm;

interface TableRowInterface extends \ArrayAccess
{
	public function getID();
	public function getTable();
	public function toArray($full = true);
	public function fromArray(array $data);
	public function save();
	public function delete();
}