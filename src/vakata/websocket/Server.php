<?php
namespace vakata\websocket;

/*
class Server extends Base
{
	private $server;
	private $sockets;

	public function __construct($ip = '127.0.0.1', $port = '8080') {
		$this->server = @stream_socket_server('tcp://' . $ip . ':' . $port, $ern = null, $ers = null, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
		$this->sockets = [];
		$this->sockets[] = $this->server;

		while(true) {
			$changed = $this->sockets;
			if(@stream_select($changed, $write = null, $except = null, null) > 0) {
				$messages = [];
				foreach($changed as $socket) {
					if($socket === $this->server) {
						$temp = stream_socket_accept($this->server);
						$this->connect($temp);
					}
					else {
						$message = $this->receive($socket);
						//echo $message."\r\n";
						if($message === false) {
							$this->disconnect($socket);
						}
						else {
							$messages[] = [
								'sender'  => (int)$socket,
								'message' => $message
							];
						}
					}
				}
				foreach($messages as $message) {
					foreach($this->sockets as $k => $recv) {
						if($recv !== $this->server && $k !== $message['sender']) {
							$this->send($recv, $message['message']);
						}
					}
				}
			}
			usleep(5000);
		}
	}
	private function connect(&$socket) {
		$this->sockets[(int)$socket] = $socket;

		$message = $this->receive($socket, false);
		$message = explode("\r\n", $message);
		$new = [];
		$new['resource'] = explode(' ', $message[0]);
		if(count($new['resource']) < 1) {
			return false;
		}
		$new['resource'] = $new['resource'][1];
		foreach($message as $k) {
			$k = explode(':', $k, 2);
			if(count($k) === 2) {
				$new[trim(strtolower($k[0]))] = trim($k[1]);
			}
		}
		if(!isset($new['sec-websocket-key']) || !isset($new['host'])) {
			return false;
		}
		$headers = [];
		$headers[] = "HTTP/1.1 101 WebSocket Protocol Handshake";
		$headers[] = "Upgrade: WebSocket";
		$headers[] = "Connection: Upgrade";
		$headers[] = "Sec-WebSocket-Version: 13";
		$headers[] = "Sec-WebSocket-Location: ws://".$new['host'].$new['resource'];
		$headers[] = "Sec-WebSocket-Accept: ".base64_encode(sha1($new['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		if(isset($new['origin'])) {
			$headers[] = "Sec-WebSocket-Origin: ".$new['origin'];
		}
		return $this->send($socket, implode("\r\n", $headers)."\r\n\r\n", false) > 0;
	}
	private function disconnect(&$socket) {
		unset($this->sockets[(int)$socket], $socket);
	}
}
//*/

///*
class Server extends Base
{
	private $server;
	private $sockets;

	public function __construct($ip = '127.0.0.1', $port = '8080') {
		$this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($this->server, $ip, $port);
		socket_listen($this->server);
		$this->sockets = [];
		$this->sockets[] = $this->server;

		while(true) {
			$changed = $this->sockets;
			if(@socket_select($changed, $write = null, $except = null, null) > 0) {
				$messages = [];
				foreach($changed as $socket) {
					if($socket === $this->server) {
						$temp = socket_accept($this->server);
						$this->connect($temp);
					}
					else {
						$message = $this->receive($socket);
						//echo $message;
						if($message === false) {
							$this->disconnect($socket);
						}
						else {
							$messages[] = [
								'sender'  => (int)$socket,
								'message' => $message
							];
						}
					}
				}
				foreach($messages as $message) {
					foreach($this->sockets as $k => $recv) {
						if($recv !== $this->server && $k !== $message['sender']) {
							$this->send($recv, $message['message']);
						}
					}
				}
			}
			usleep(5000);
		}
	}
	private function connect(&$socket) {
		$this->sockets[(int)$socket] = $socket;

		$message = socket_read($socket, 4096, PHP_BINARY_READ);
		$message = explode("\r\n", $message);
		$new = [];
		$new['resource'] = explode(' ', $message[0]);
		if(count($new['resource']) < 1) {
			return false;
		}
		$new['resource'] = $new['resource'][1];
		foreach($message as $k) {
			$k = explode(':', $k, 2);
			if(count($k) === 2) {
				$new[trim(strtolower($k[0]))] = trim($k[1]);
			}
		}
		if(!isset($new['sec-websocket-key']) || !isset($new['host'])) {
			return false;
		}
		$headers = [];
		$headers[] = "HTTP/1.1 101 WebSocket Protocol Handshake";
		$headers[] = "Upgrade: WebSocket";
		$headers[] = "Connection: Upgrade";
		$headers[] = "Sec-WebSocket-Version: 13";
		$headers[] = "Sec-WebSocket-Location: ws://".$new['host'].$new['resource'];
		$headers[] = "Sec-WebSocket-Accept: ".base64_encode(sha1($new['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		if(isset($new['origin'])) {
			$headers[] = "Sec-WebSocket-Origin: ".$new['origin'];
		}
		return socket_write($socket, implode("\r\n", $headers)."\r\n\r\n") > 0;
	}
	private function disconnect(&$socket) {
		unset($this->sockets[(int)$socket], $socket);
	}
}
//*/