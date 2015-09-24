<?php
namespace vakata\upload;

use vakata\file\FileUrl;

class UploadUrl extends Upload implements UploadInterface
{
	protected $url = null;

	public function __construct($url) {
		$this->url = $url;
	}

	protected function check($needle) {
		parent::check($needle);
	}

	public function upload($needle, $chunk = 0, $chunks = 0) {
		$this->check($needle);
		$name = $this->getName($needle, false);
		$bndr = 'multipart-boundry-'.md5(microtime());

		$data  = '--' . $bndr . "\r\n";
		$data .= 'Content-Disposition: form-data; name="name"' . "\r\n";
		$data .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
		$data .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
		$data .= $name . "\r\n";
		$data .= '--' . $bndr . "\r\n";
		$data .= 'Content-Disposition: form-data; name="chunk"' . "\r\n\r\n";
		$data .= $chunk . "\r\n";
		$data .= '--' . $bndr . "\r\n";
		$data .= 'Content-Disposition: form-data; name="chunks"' . "\r\n\r\n";
		$data .= $chunks . "\r\n";
		$data .= '--' . $bndr . "\r\n";
		$data .= 'Content-Disposition: form-data; name="file"; filename="blob";' . "\r\n";
		//$data .= 'Content-Disposition: form-data; name="file"; ' .
		//		 'filename="'.preg_replace('([^a-z0-9.-]+)i', '_', $name).'"; ' .
		//		 'filename*=UTF-8\'\'' . rawurlencode($name) . '' . "\r\n";
		$data .= 'Content-Transfer-Encoding: binary' . "\r\n\r\n";
		$data .= file_get_contents($_FILES[$needle]['tmp_name']) . "\r\n";
		$data .= '--' . $bndr . '--' . "\r\n\r\n";

		$rslt = file_get_contents($this->url, false, stream_context_create([
			'http' => [
				'method'  => 'POST',
				'header'  => 'Content-Type: multipart/form-data; boundary=' . $bndr . "\r\n",
				'content' => $data
			]
		]));

		$rslt = explode("\n", str_replace("\r", '', $rslt));
		return new FileUrl($rslt[4]);
	}
}
