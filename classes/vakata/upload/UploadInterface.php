<?php
namespace vakata\upload;

interface UploadInterface
{
	public function upload($needle, $is_chunk = false);
	public function hasFiles();
	public function maxSize();
}