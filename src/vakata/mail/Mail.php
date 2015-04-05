<?php
namespace vakata\mail;

class Mail
{
	protected $to       = [];
	protected $from     = null;
	protected $html     = true;
	protected $subject  = null;
	protected $message  = null;
	protected $headers  = [];
	protected $attached = [];

	public function __construct($to, $from, $subject = null, $message = null) {
		if(!is_array($to)) {
			$to = explode(',', $to);
		}
		$to = array_filter(array_map(function ($mail) {
			return filter_var(trim($mail), FILTER_VALIDATE_EMAIL) ? trim($mail) : null;
		}, $to));

		$this->to = $to;
		$this->from = $from;
		$this->subject = $subject;
		$this->message = $message;
	}

	protected function cleanHeaderName($name) {
		if(strncmp($name, 'HTTP_', 5) === 0) {
			$name = substr($name, 5);
		}
		$name = str_replace('_', ' ', strtolower($name));
		$name = str_replace('-', ' ', strtolower($name));
		$name = str_replace(' ', '-', ucwords($name));
		return $name;
	}

	public function getSubject() {
		return $this->subject;
	}
	public function setSubject($subject) {
		$this->subject = $subject;
	}
	public function getMessage() {
		return $this->message;
	}
	public function setMessage($message, $isHTML = true) {
		$this->message = $message;
		$this->html = $isHTML;
	}
	public function isHTML() {
		return $this->html;
	}

	public function getHeaders() {
		return $this->headers;
	}
	public function setHeader($header, $value) {
		$this->headers[$this->cleanHeaderName($header)] = $value;
	}
	public function hasHeader($header) {
		return isset($this->headers[$this->cleanHeaderName($header)]);
	}
	public function getHeader($header) {
		return isset($this->headers[$this->cleanHeaderName($header)]) ? $this->headers[$this->cleanHeaderName($header)] : null;
	}
	public function removeHeader($header) {
		unset($this->headers[$this->cleanHeaderName($header)]);
	}
	public function removeHeaders() {
		$this->headers = [];
	}

	public function hasAttachments() {
		return count($this->attached);
	}
	public function addAttachment(\vakata\file\FileInterface $file) {
		$this->attached[] = $file;
	}
	public function getAttachments() {
		return $this->attached;
	}
	public function removeAttachments() {
		$this->attached = [];
	}

	public function send() {
		$this->setHeader('From: ', $this->from);
		if($this->hasAttachments()) {
			$b = '==Multipart_Boundary_x'.md5(microtime()).'x';
			$this->setHeader('MIME-Version: 1.0;');
			$this->setHeader('Content-Type: multipart/mixed; boundary="'.$b.'"');

			$m  = '';
			$m .= '--' . $b . "\n";
			$m .= 'Content-Type: text/html; charset="utf-8"' . "\n";
			$m .= 'Content-Transfer-Encoding: 8bit' . "\n\n";
			$m .= $this->message . "\n\n";

			foreach($this->attached as $file) {
				$content = &$file->content();
				if(!$content) { continue; }
				$size = mb_strlen($content, '8bit');
				$content = chunk_split(base64_encode($content));
				$m .= '--' . $b . "\n";
				$m .= 'Content-Type: application/octet-stream; name="' . '=?utf-8?B?'.base64_encode($file->name).'?=' . '"' . "\n";
				$m .= 'Content-Disposition: attachment; size=' . $size . "\n";
				$m .= 'Content-Transfer-Encoding: base64' . "\n\n";
				$m .= $content . "\n\n";
			}
			$m .= '--' . $b . '--';
			$this->message = $m;
		}
		else {
			$this->setHeader('MIME-Version', '1.0;');
			$this->setHeader('Content-Type', 'text/'.($this->html ? 'html' : 'plain').'; charset="utf-8"');
		}
		return @mail(implode(', ', $this->to), '=?utf-8?B?'.base64_encode((string)$this->subject).'?=', (string)$this->message, implode("\r\n", $this->headers)) ? true : false;
	}
}