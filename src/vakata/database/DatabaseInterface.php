<?php
namespace vakata\database;

interface DatabaseInterface
{
	public function query($sql, array $data = null);
	public function get($sql, array $data = null, $key = null, $skip_key = false, $mode = "assoc");
	public function all($sql, array $data = null, $key = null, $skip_key = false, $mode = "assoc");
	public function one($sql, array $data = null, $mode = "assoc");
	public function prepare($sql);
	public function execute(array $data = null);
	public function escape($data);
	public function begin();
	public function commit();
	public function rollback();
	public function driver();
}