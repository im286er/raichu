<?php
namespace vakata\websocket;

/*
class Client extends Base
{
	private $socket;
	private $console;

	public function __construct($ip = '127.0.0.1', $port = '8080') {
		$this->socket = fsockopen($ip, $port);
		if (!$this->socket) {
			throw new Exception('Could not connect');
		}

		$key = $this->generateKey();

		$headers = '' .
			'GET / HTTP/1.1' . "\r\n" .
			'Host: ' . $ip . ':' . $port . "\r\n" .
			'Origin: http://' . $ip . "\r\n" .
			'User-Agent: websocket-client-php' . "\r\n" .
			'Connection: Upgrade' . "\r\n" .
			'Upgrade: websocket' . "\r\n" .
			'Sec-WebSocket-Key: ' . $key . "\r\n" .
			'Sec-WebSocket-Version: 13' . "\r\n" .
			"\r\n";
		fwrite($this->socket, $headers);

		$data = $this->receive($this->socket, false);

		if (!preg_match('(Sec-WebSocket-Accept:\s*(.*)$)mUi', $data, $matches)) {
			throw new Exception('Bad response');
		}
		if (trim($matches[1]) !== base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')))) {
			throw new Exception('Bad key');
		}
		
		while (true) {
			$changed = [ $this->socket ];
			if (@stream_select($changed, $write = null, $except = null, null) > 0) {
				foreach ($changed as $socket) {
					$message = $this->receive($socket);
					if ($message !== false) {
						echo $message . "\r\n";
					}
				}
			}
			usleep(5000);
		}
	}
	private function generateKey() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
		$key = '';
		$chars_length = strlen($chars);
		for ($i = 0; $i < 16; $i++) {
			$key .= $chars[mt_rand(0, $chars_length-1)];
		}
		return base64_encode($key);
	}
}
//*/

///*
class Client extends Base
{
	private $socket;
	private $console;

	public function __construct($ip = '127.0.0.1', $port = '8080') {
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!socket_connect($this->socket, $ip, $port)) {
			throw new Exception('Could not connect');
		}

		$key = $this->generateKey();

		$headers = '' .
			'GET / HTTP/1.1' . "\r\n" .
			'Host: ' . $ip . ':' . $port . "\r\n" .
			'Origin: http://' . $ip . "\r\n" .
			'User-Agent: websocket-client-php' . "\r\n" .
			'Connection: Upgrade' . "\r\n" .
			'Upgrade: websocket' . "\r\n" .
			'Sec-WebSocket-Key: ' . $key . "\r\n" .
			'Sec-WebSocket-Version: 13' . "\r\n" .
			"\r\n";
		socket_write($this->socket, $headers);

		$data = $this->receive($this->socket, false);

		if (!preg_match('(Sec-WebSocket-Accept:\s*(.*)$)mUi', $data, $matches)) {
			throw new Exception('Bad response');
		}
		if (trim($matches[1]) !== base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')))) {
			throw new Exception('Bad key');
		}
		
		while (true) {
			$changed = [ $this->socket ];
			if (@socket_select($changed, $write = null, $except = null, null, null) > 0) {
				foreach ($changed as $socket) {
					$message = $this->receive($socket);
					if ($message !== false) {
						echo $message . "\r\n";
					}
				}
			}
			usleep(5000);
		}
	}
	private function generateKey() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
		$key = '';
		$chars_length = strlen($chars);
		for ($i = 0; $i < 16; $i++) {
			$key .= $chars[mt_rand(0, $chars_length-1)];
		}
		return base64_encode($key);
	}
}
//*/