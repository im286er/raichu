<?php
namespace vakata\database;

interface DatabaseInterface
{
	public function query($sql, $data = null);
	public function get($sql, $data = null, $key = null, $skip_key = false, $mode = "assoc");
	public function all($sql, $data = null, $key = null, $skip_key = false, $mode = "assoc");
	public function one($sql, $data = null, $mode = "assoc");
	public function prepare($sql);
	public function execute($data = null);
	public function escape($data);
	public function begin();
	public function commit();
	public function rollback();
	public function isTransaction();
	public function driver();
}