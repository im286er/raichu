<?php
namespace vakata\file;

interface ArchiveInterface extends FileInterface
{
	public function listing();
}