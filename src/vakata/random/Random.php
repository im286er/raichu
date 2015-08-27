<?php
namespace vakata\random;

class Random
{
	protected static function generate($length = 64) {
		$size = 64;
		$seeds = [];
		// high
		$file = '/dev/random';
		if (@file_exists($file) && ($file = @fopen($file, 'rb'))) {
			if (function_exists('stream_set_read_buffer')) {
				stream_set_read_buffer($file, 0);
			}
			$seeds[] = fread($file, $size);
			fclose($file);
		}
		// high
		if (function_exists('openssl_random_pseudo_bytes')) {
			$seeds[] = openssl_random_pseudo_bytes($size);
		}
		// medium
		$file = '/dev/urandom';
		if (@file_exists($file) && ($file = @fopen($file, 'rb'))) {
			if (function_exists('stream_set_read_buffer')) {
				stream_set_read_buffer($file, 0);
			}
			$seeds[] = fread($file, $size);
			fclose($file);
		}
		// low
		$tmp = '';
		while (strlen($tmp) < $size) {
			$tmp = uniqid($tmp, true);
		}
		$seeds[] = substr($tmp, 0, $size);

		// low
		$tmp = '';
		for ($i = 0; $i < $size; $i++) {
			$tmp .= chr((mt_rand() ^ mt_rand()) % 256);
		}
		$seeds[] = $tmp;

		// low
		$tmp = '';
		for ($i = 0; $i < $size; $i++) {
			$tmp .= chr((rand() ^ rand()) % 256);
		}
		$seeds[] = $tmp;

		// combine seeds
		$result = hash_hmac('sha512', $seeds[0], '', true);
		for ($i = 1; $i < count($seeds); $i++) {
			$result ^= hash_hmac('sha512', $seeds[$i], $seeds[$i - 1], true);
		}
		while (strlen($result) < $length) {
			$result .= static::generate();
		}
		if (strlen($result) > $length) {
			$result = substr($result, 0, $length);
		}
		return $result;
	}
	public static function string($length = 32, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
		$rand = static::generate($length * 2);
		$lngt = mb_strlen($characters, 'UTF-8');
		$rslt = '';
		for ($i = 0; $i < strlen($rand); $i++) {
			$rslt .= mb_substr($characters, ord($rand[$i]) % $lngt, 1, 'UTF-8');
		}
		return mb_substr($rslt, 0, $length, 'UTF-8');
	}
	public static function number($min = 0, $max = PHP_INT_MAX) {
		if ($max === $min) { return $max; }
		$rand = static::generate(4);
		$rand = hexdec(bin2hex($rand));
		return $min + abs($rand % ($max - $min + 1));
	}
}
