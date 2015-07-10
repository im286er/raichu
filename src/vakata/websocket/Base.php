<?php
namespace vakata\websocket;

/*
abstract class Base
{
	protected static $opcodes = [
		'continuation' => 0,
		'text'         => 1,
		'binary'       => 2,
		'close'        => 8,
		'ping'         => 9,
		'pong'         => 10
	];
	protected static $fragment_size = 4096;

	public function send(&$socket, $data, $encode = true, $opcode = 'text', $masked = true) {
		if(!$encode) {
			return fwrite($socket, $data) > 0;
		}
		while (strlen($data)) {
			$temp = substr($data, 0, static::$fragment_size);
			$data = substr($data, static::$fragment_size);
			$temp = $this->encode($temp, $opcode, $masked, strlen($data) === 0);
			fwrite($socket, $temp);
			$opcode = 'continuation';
		}
		return true;
	}
	public function receive(&$socket, $decode = true) {
		if(!$decode) {
			$data = '';
			$read = self::$fragment_size;
			do {
				$buff = fread($socket, $read);
				if($buff === false) {
					return false;
				}
				$data .= $buff;
				$meta = stream_get_meta_data($socket);
				$read = min((int)$meta['unread_bytes'], self::$fragment_size);
				usleep(1000);
			} while (!feof($socket) && (int)$meta['unread_bytes'] > 0);
			return $data;
		}

		$data   = fread($socket, 2);
		if($data === false || strlen($data) < 2) { return false; }
		$final  = (bool)(ord($data[0]) & 1 << 7);
		$rsv1   = (bool)(ord($data[0]) & 1 << 6);
		$rsv2   = (bool)(ord($data[0]) & 1 << 5);
		$rsv3   = (bool)(ord($data[0]) & 1 << 4);
		$opcode = ord($data[0]) & 31;
		$masked = (bool)(ord($data[1]) >> 7);

		$payload = '';
		$length  = (int)(ord($data[1]) & 127); // Bits 1-7 in byte 1
		if($length > 125) {
			$temp  = $length === 126 ? fread($socket, 2) : fread($socket, 8);
			if($temp === false) { return false; }
			$length = '';
			for($i = 0; $i < strlen($temp); $i++) {
				$length .= sprintf("%08b", ord($temp[$i]));
			}
			$length = bindec($length);
		}

		if($masked) {
			$mask  = fread($socket, 4);
			if($mask === false) { return false; }
		}
		if($length > 0) {
			$temp = '';
			do {
				$buff = fread($socket, min($length, static::$fragment_size));
				if($buff === false) { return false; }
				$temp .= $buff;
			} while(strlen($temp) < $length);
			if($masked) {
				for($i = 0; $i < $length; $i++) {
					$payload .= ($temp[$i] ^ $mask[$i % 4]);
				}
			}
			else {
				$payload = $temp;
			}
		}

		if($opcode === static::$opcodes['close']) {
			return false;
		}
		return $final ? $payload : $payload . $this->receive($socket, true);
	}

	protected function encode($data, $opcode = 'text', $masked = true, $final = true) {
		$length = strlen($data);

		$head  = '';
		$head .= (bool)$final ? '1' : '0';
		$head .= '000';
		$head .= sprintf('%04b', self::$opcodes[$opcode]);
		$head .= (bool)$masked ? '1' : '0';
		if($length > 65535) {
			$head .= decbin(127);
			$head .= sprintf('%064b', $length);
		}
		elseif($length > 125) {
			$head .= decbin(126);
			$head .= sprintf('%016b', $length);
		}
		else {
			$head .= sprintf('%07b', $length);
		}

		$frame = '';
		foreach(str_split($head, 8) as $binstr) {
			$frame .= chr(bindec($binstr));
		}
		if($masked) {
			$mask = '';
			for($i = 0; $i < 4; $i++) {
				$mask .= chr(rand(0, 255));
			}
			$frame .= $mask;
		}
		for($i = 0; $i < $length; $i++) {
			$frame .= ($masked === true) ? $data[$i] ^ $mask[$i % 4] : $data[$i];
		}
		return $frame;
	}
}
//*/

///*
abstract class Base
{
	protected static $opcodes = [
		'continuation' => 0,
		'text'         => 1,
		'binary'       => 2,
		'close'        => 8,
		'ping'         => 9,
		'pong'         => 10
	];
	protected static $fragment_size = 4096;

	public function send(&$socket, $data, $encode = true, $opcode = 'text', $masked = true) {
		if(!$encode) {
			return socket_write($socket, $data) > 0;
		}
		while (strlen($data)) {
			$temp = substr($data, 0, static::$fragment_size);
			$data = substr($data, static::$fragment_size);
			$temp = $this->encode($temp, $opcode, $masked, strlen($data) === 0);
			socket_write($socket, $temp);
			$opcode = 'continuation';
		}
	}
	public function receive(&$socket, $decode = true) {
		if(!$decode) {
			$data = '';
			$buff = '';
			do {
				$read = @socket_recv($socket, $buff, self::$fragment_size, 0);
				if(!$read) { return false; }
				$data .= $temp;
				usleep(1000);
			} while ($read === self::$fragment_size);
			return $data;
		}

		$data = null;
		if(!socket_recv($socket, $data, 2, 0) || $data === null) { return false; }
		$final  = (bool)(ord($data[0]) & 1 << 7);
		$rsv1   = (bool)(ord($data[0]) & 1 << 6);
		$rsv2   = (bool)(ord($data[0]) & 1 << 5);
		$rsv3   = (bool)(ord($data[0]) & 1 << 4);
		$opcode = ord($data[0]) & 31;
		$masked = (bool)(ord($data[1]) >> 7);

		$payload = '';
		$length  = (int)(ord($data[1]) & 127); // Bits 1-7 in byte 1
		if($length > 125) {
			$temp = null;
			if(!socket_recv($socket, $temp, $length === 126 ? 2 : 8, 0) || $temp === null) { return false; }
			$length = '';
			for($i = 0; $i < strlen($temp); $i++) {
				$length .= sprintf("%08b", ord($temp[$i]));
			}
			$length = bindec($length);
		}

		if($masked) {
			$mask  = null;
			if(!socket_recv($socket, $mask, 4, 0) || $mask === null) { return false; }
		}
		if($length > 0) {
			$temp = '';
			do {
				$buff = null;
				if(!socket_recv($socket, $buff, min($length, static::$fragment_size), 0) || $buff === null) { return false; }
				$temp .= $buff;
			} while(strlen($temp) < $length);
			if($masked) {
				for($i = 0; $i < $length; $i++) {
					$payload .= ($temp[$i] ^ $mask[$i % 4]);
				}
			}
			else {
				$payload = $temp;
			}
		}

		if($opcode === static::$opcodes['close']) {
			return false;
		}
		return $final ? $payload : $payload . $this->receive($socket, true);
	}

	protected function encode($data, $opcode = 'text', $masked = true, $final = true) {
		$length = strlen($data);

		$head  = '';
		$head .= (bool)$final ? '1' : '0';
		$head .= '000';
		$head .= sprintf('%04b', self::$opcodes[$opcode]);
		$head .= (bool)$masked ? '1' : '0';
		if($length > 65535) {
			$head .= decbin(127);
			$head .= sprintf('%064b', $length);
		}
		elseif($length > 125) {
			$head .= decbin(126);
			$head .= sprintf('%016b', $length);
		}
		else {
			$head .= sprintf('%07b', $length);
		}

		$frame = '';
		foreach(str_split($head, 8) as $binstr) {
			$frame .= chr(bindec($binstr));
		}
		if($masked) {
			$mask = '';
			for($i = 0; $i < 4; $i++) {
				$mask .= chr(rand(0, 255));
			}
			$frame .= $mask;
		}
		for($i = 0; $i < $length; $i++) {
			$frame .= ($masked === true) ? $data[$i] ^ $mask[$i % 4] : $data[$i];
		}
		return $frame;
	}

	protected function decode($data) {
		$final  = (bool)(ord($data[0]) & 1 << 7);
		$rsv1   = (bool)(ord($data[0]) & 1 << 6);
		$rsv2   = (bool)(ord($data[0]) & 1 << 5);
		$rsv3   = (bool)(ord($data[0]) & 1 << 4);
		$opcode = ord($data[0]) & 31;
		$masked = (bool)(ord($data[1]) >> 7);

		$payload = '';
		$length  = (int)(ord($data[1]) & 127); // Bits 1-7 in byte 1
		$offs    = 2;
		if($length > 125) {
			$temp  = $length === 126 ? substr($data, $offs, 2) : substr($data, $offs, 8);
			$offs += $length === 126 ? 2 : 8;
			$length = '';
			for($i = 0; $i < strlen($temp); $i++) {
				$length .= sprintf("%08b", ord($temp[$i]));
			}
			$length = bindec($length);
		}
		if($masked) {
			$mask  = substr($data, $offs, 4);
			$offs += 4;
		}
		if($length > 0) {
			$temp  = substr($data, $offs, $length);
			$offs += $length;
			if($masked) {
				for($i = 0; $i < $length; $i++) {
					$payload .= ($temp[$i] ^ $mask[$i % 4]);
				}
			}
			else {
				$payload = $temp;
			}
		}

		if($opcode === static::$opcodes['close']) {
			return false;
		}
		$data = substr($data, $offs);
		return $final || !strlen($data) ? $payload : $payload . $this->decode($data);
	}
}
//*/