<?php
namespace vakata\user\authentication;

use vakata\user\UserException;

class DecoratorSession implements AuthenticationInterface
{
	use \vakata\user\TraitLogData;

	protected $settings = '';
	protected $auth = null;

	public function __construct(AuthenticationInterface $auth, array $settings = []) {
		$this->auth = $auth;
		$this->settings = array_merge([
			'key'				=> 'user',
			'match_ip'			=> false,
			'match_user_agent'	=> true,
			'session_timeout'	=> 1800,
			'login_timeout'		=> 2592000,
			'regenerate'		=> 1800
		], $settings);

		if(!isset($_SESSION[$this->settings['key']])) {
			$_SESSION[$this->settings['key']] = [];
		}
		if(!isset($_SESSION[$this->settings['key']]['ip'])) {
			$_SESSION[$this->settings['key']]['ip'] = $this->ipAddress();
		}
		if(!isset($_SESSION[$this->settings['key']]['ua'])) {
			$_SESSION[$this->settings['key']]['ua'] = $this->userAgent();
		}
		if(!isset($_SESSION[$this->settings['key']]['lastlogin'])) {
			$_SESSION[$this->settings['key']]['lastlogin'] = 0;
		}
		if(!isset($_SESSION[$this->settings['key']]['lastregenerate'])) {
			$_SESSION[$this->settings['key']]['lastregenerate'] = 0;
		}
		if(!isset($_SESSION[$this->settings['key']]['sessionstart'])) {
			$_SESSION[$this->settings['key']]['sessionstart'] = time();
		}
		if(!isset($_SESSION[$this->settings['key']]['lastseen'])) {
			$_SESSION[$this->settings['key']]['lastseen'] = time();
		}
		if(!isset($_SESSION[$this->settings['key']]['data'])) {
			$_SESSION[$this->settings['key']]['data'] = null;
		}
	}
	public function provider() {
		return isset($_SESSION[$this->settings['key']]['provider']) ? $_SESSION[$this->settings['key']]['provider'] : $this->auth->provider();
	}
	public function authenticate($data = null) {
		$temp = $this->auth->authenticate($data);
		if($temp) {
			session_regenerate_id(true);
			$_SESSION[$this->settings['key']]['ip']             = $this->ipAddress();
			$_SESSION[$this->settings['key']]['ua']             = $this->userAgent();
			$_SESSION[$this->settings['key']]['lastseen']       = time();
			$_SESSION[$this->settings['key']]['lastlogin']      = time();
			$_SESSION[$this->settings['key']]['lastregenerate'] = time();
			$_SESSION[$this->settings['key']]['provider']       = $this->auth->provider();
			$temp['ip']      = $_SESSION[$this->settings['key']]['ip'];
			$temp['ua']      = $_SESSION[$this->settings['key']]['ua'];
			$temp['login']   = $_SESSION[$this->settings['key']]['lastlogin'];
			$temp['seen']    = $_SESSION[$this->settings['key']]['lastseen'];
			return $_SESSION[$this->settings['key']]['data'] = $temp;
		}
		if(isset($_SESSION[$this->settings['key']]['data'])) {
			if($this->settings['match_ip'] && $this->ipAddress() !== $_SESSION[$this->settings['key']]['ip']) {
				throw new UserException('Достъпвате системата от различно IP.');
			}
			if($this->settings['match_user_agent'] && $this->userAgent() !== $_SESSION[$this->settings['key']]['ua']) {
				throw new UserException('Достъпвате системата от различен браузър.');
			}
			if($this->settings['session_timeout'] && time() - (int)$_SESSION[$this->settings['key']]['lastseen'] >= $this->settings['session_timeout']) {
				throw new UserException('Изтекла сесия.');
			}
			if($this->settings['login_timeout'] && time() - (int)$_SESSION[$this->settings['key']]['lastlogin'] >= $this->settings['login_timeout']) {
				throw new UserException('Моля влезте отново.');
			}
			// валиден
			$_SESSION[$this->settings['key']]['data']['regenerated'] = 0;
			$_SESSION[$this->settings['key']]['data']['seen'] = $_SESSION[$this->settings['key']]['lastseen'] = time();
			if($this->settings['regenerate'] && time() - (int)$_SESSION[$this->settings['key']]['lastregenerate'] > $this->settings['regenerate']) {
				$_SESSION[$this->settings['key']]['data']['regenerated'] = $_SESSION[$this->settings['key']]['lastregenerate'] = $_SESSION[$this->settings['key']]['data']['seen'];
				session_regenerate_id(true);
			}
			return $_SESSION[$this->settings['key']]['data'];
		}
		return null;
	}
	public function restore($data = null) {
		return $this->auth->restore($data);
	}
	public function clear() {
		$this->auth->clear();
		$_SESSION[$this->settings['key']]['lastlogin'] = 0;
		$_SESSION[$this->settings['key']]['data']      = null;
		$_SESSION[$this->settings['key']]['provider']  = null;
	}

	/*
	public function ipRange($ip, $range) {
		if(strpos($range, '/') !== false) {
			// $range is in IP/NETMASK format
			list($range, $netmask) = explode('/', $range, 2);
			if (strpos($netmask, '.') !== false) {
				// $netmask is a 255.255.0.0 format
				$netmask = str_replace('*', '0', $netmask);
				$netmask_dec = ip2long($netmask);
				return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
			}
			// $netmask is a CIDR size block
			// fix the range argument
			$x = explode('.', $range);
			while(count($x)<4) $x[] = '0';
			list($a,$b,$c,$d) = $x;
			$range = sprintf("%u.%u.%u.%u", empty($a)?'0':$a, empty($b)?'0':$b,empty($c)?'0':$c,empty($d)?'0':$d);
			$range_dec = ip2long($range);
			$ip_dec = ip2long($ip);

			# Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
			#$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

			# Strategy 2 - Use math to create it
			$wildcard_dec = pow(2, (32-$netmask)) - 1;
			$netmask_dec = ~ $wildcard_dec;

			return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
		}
		// range might be 255.255.*.* or 1.2.3.0-1.2.3.255
		if (strpos($range, '*') !==false) { // a.b.*.* format
			// Just convert to A-B format by setting * to 0 for A and 255 for B
			$lower = str_replace('*', '0', $range);
			$upper = str_replace('*', '255', $range);
			$range = "$lower-$upper";
		}
		if (strpos($range, '-')!==false) { // A-B format
			list($lower, $upper) = explode('-', $range, 2);
			$lower_dec = (float)sprintf("%u",ip2long($lower));
			$upper_dec = (float)sprintf("%u",ip2long($upper));
			$ip_dec = (float)sprintf("%u",ip2long($ip));
			return ( ($ip_dec>=$lower_dec) && ($ip_dec<=$upper_dec) );
		}
		echo 'Range argument is not in 1.2.3.4/24 or 1.2.3.4/255.255.255.0 format';
		return false;
	}
	*/
}