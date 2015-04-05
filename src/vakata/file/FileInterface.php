<?php
namespace vakata\file;

interface FileInterface
{
	public function id();
	public function name();
	public function location();
	public function extension();
	public function modified();
	public function size();
	public function hash();
	public function content();
}