<?php
namespace vakata\user\authentication\TOTP;

use vakata\user\UserException;
use vakata\user\authentication\AuthenticationInterface;

class TOTP implements AuthenticationInterface
{
	protected $service;
	protected $options;

	public function __construct(AuthenticationInterface $service, array $options = []) {
		$this->service	= $service;
		$this->options	= array_merge([
			'secret'		=> '',
			'title'			=> 'raichu',
			'session'		=> 'totp',
			'code_timeout'	=> 60,
			'code_length'	=> 6,
			'slice_length'	=> 30
		], $options);
		if (!isset($_SESSION[$this->options['session']])) {
			$_SESSION[$this->options['session']] = false;
		}
	}

	public function provider() {
		return $this->service->provider();
	}
	public function authenticate($data = null) {
		$temp = $this->service->authenticate($data);
		if ($temp) {
			$temp['totp'] = $this->getSecret($temp);
			if (!$_SESSION[$this->options['session']]) {
				if (!isset($data['totp'])) {
					throw new TOTPException('Моля въведете код');
				}
				$this->verifyCode($temp['totp'], $data['totp']);
				$_SESSION[$this->options['session']] = true;
			}
		}
		return $temp;
	}
	public function clear() {
		$_SESSION[$this->options['session']] = false;
		return $this->service->clear();
	}
	public function restore($data = null) {
		return $this->service->restore($data);
	}

	protected function getSecret($user) {
		return substr($this->base32_encode(sha1($this->options['secret'] . '.' . $user['id'])), 0, 16);
	}
	protected function getQRCode($secret, $size = 200) {
		return 'data:image/png;base64,' . @base64_encode(file_get_contents('https://chart.googleapis.com/chart?chs='.((int)$size).'x'.((int)$size).'&chld=M|0&cht=qr&chl=' . urlencode('otpauth://totp/'.$this->options['title'].'?secret='.$secret)));
	}
	protected function generateCode($secret, $now = null) {
		if ($now === null) {
			$now = floor(microtime(true) / $this->options['slice_length']);
		}
		$hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $now), $this->base32_decode($secret), true);
		$offs = ord($hash[19]) & 0xf;
		$hash = (
			((ord($hash[$offs+0]) & 0x7f) << 24 ) |
			((ord($hash[$offs+1]) & 0xff) << 16 ) |
			((ord($hash[$offs+2]) & 0xff) << 8  ) |
			 (ord($hash[$offs+3]) & 0xff)
		) % pow(10, $this->options['code_length']);
		return str_pad($hash, $this->options['code_length'], '0', STR_PAD_LEFT);
	}
	protected function sendCode($code, $data) {
		// could send to sms or as notification
	}
	protected function verifyCode($secret, $code) {
		$code = str_replace(' ', '', $code);
		$now = floor(microtime(true) / $this->options['slice_length']);
		$ctm = ceil($this->options['code_timeout'] / $this->options['slice_length']);

		for ($i = -$ctm; $i <= $ctm; $i++) {
			if ($this->generateCode($secret, $now + $i) === $code) {
				return true;
			}
		}
		$this->clear();
		throw new UserException('Невалиден код');
	}
	protected static function base32_decode($b32) {
		$lut = [
			"A" => 0,	"B" => 1,	"C" => 2,	"D" => 3,
			"E" => 4,	"F" => 5,	"G" => 6,	"H" => 7,
			"I" => 8,	"J" => 9,	"K" => 10,	"L" => 11,
			"M" => 12,	"N" => 13,	"O" => 14,	"P" => 15,
			"Q" => 16,	"R" => 17,	"S" => 18,	"T" => 19,
			"U" => 20,	"V" => 21,	"W" => 22,	"X" => 23,
			"Y" => 24,	"Z" => 25,	"2" => 26,	"3" => 27,
			"4" => 28,	"5" => 29,	"6" => 30,	"7" => 31
		];
		$b32 = strtoupper($b32);
		$l = strlen($b32);
		$n = 0;
		$j = 0;
		$binary = "";
		for ($i = 0; $i < $l; $i++) {
			$n = $n << 5;				// Move buffer left by 5 to make room
			$n = $n + $lut[$b32[$i]];	// Add value into buffer
			$j = $j + 5;				// Keep track of number of bits in buffer
			if ($j >= 8) {
				$j = $j - 8;
				$binary .= chr(($n & (0xFF << $j)) >> $j);
			}
		}
		return $binary;
	}
	protected static function base32_encode($str) {
		$lut = [
			'A',	'B',	'C',	'D',
			'E',	'F',	'G',	'H',
			'I',	'J',	'K',	'L',
			'M',	'N',	'O',	'P',
			'Q',	'R',	'S',	'T',
			'U',	'V',	'W',	'X',
			'Y',	'Z',	'2',	'3',
			'4',	'5',	'6',	'7'
		];
		$bin = '';
		foreach (str_split($str) as $s) {
			$bin .= str_pad(decbin(ord($s)), 8, 0, STR_PAD_LEFT);
		}
		$bin = explode(' ', trim(chunk_split($bin, 5, ' ')));
		if (count($bin) % 8 !== 0) {
			$bin = array_pad($bin, count($bin) + (8 - count($bin) % 8), null);
		}
		$b32 = '';
		foreach ($bin as $b) {
			$b32 .= is_null($b) ? '=' : $lut[bindec(str_pad($b, 5, 0, STR_PAD_RIGHT))];
		}
		return $b32;
	}
}
