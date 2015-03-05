<?php
namespace vakata\database\orm;

interface TableRowsInterface extends \Iterator, \ArrayAccess, \Countable
{
	public function getTable();
	public function toArray($full = true);
	public function save();
}