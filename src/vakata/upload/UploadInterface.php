<?php
namespace vakata\upload;

interface UploadInterface
{
	public function upload($needle, $chunk = 0);
	public function hasFiles();
	public function maxSize();
}