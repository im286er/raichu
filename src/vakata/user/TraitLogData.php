<?php
namespace vakata\user;

trait TraitLogData
{
	public function sessionId() {
		return (string)session_id();
	}
	public function userAgent() {
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}
	public function ipAddress() {
		$ip = '0.0.0.0';
		// TODO: check if remote_addr is a cloudflare one and only then read the connecting ip
		// https://www.cloudflare.com/ips-v4
		// https://www.cloudflare.com/ips-v6
		if (false && isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
			$ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}
		elseif (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		if (strpos($ip, ',') !== false) {
			$ip = @end(explode(',', $ip));
		}
		$ip = trim($ip);
		if (false === ($ip = filter_var($ip, FILTER_VALIDATE_IP))) {
			$ip = '0.0.0.0';
		}
		return $ip;
	}
}