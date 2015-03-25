<?php
// timezone & locale
setlocale(LC_ALL, 'en_US.UTF-8');
date_default_timezone_set('Europe/Sofia');

// error handling
error_reporting(E_ALL);
@ini_set('log_errors', 'On');
@ini_set('error_log', ( defined('LOGROOT') ? LOGROOT : __DIR__ ) . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log');
@ini_set('display_errors', ( defined('DEBUG') && DEBUG ? 'On' : 'Off' ));
@ini_set('display_start_up_errors', ( defined('DEBUG') && DEBUG ? 'On' : 'Off' ));
@ini_set('log_errors_max_len', 0);
@ini_set('ignore_repeated_errors', 1);
@ini_set('ignore_repeated_source', 0);
@ini_set('track_errors', 1);
@ini_set('html_errors', 0);
@ini_set('report_memleaks', 1);
if(defined('DEBUG') && DEBUG) {
	@ini_set('opcache.enable', 0);
}
// create a default exception handler
set_exception_handler(function ($e) {
	$str = 'PHP Exception:' . ((int)$e->getCode() ? ' ' . $e->getCode() . ' -' : '') . ' ' . $e->getMessage() . ' in '.$e->getFile().' on line '.$e->getLine();
	@error_log($str);

	while(ob_get_level() && ob_end_clean());
	if(!headers_sent()) {
		@header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
		@header('Status: 500 Server Error');
		@header('Content-Type: text/html; charset=utf-8');
	}
	echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Грешка</title></head><body style="background:#ebebeb;">';
	echo '<h1 style="font-size:1.4em; text-align:center; margin:2em 0 0 0; color:#8b0000; text-shadow:1px 1px 0 white;">В момента не можем да обслужим заявката Ви.</h1>' . "\n\n";

	if(defined('DEBUG') && DEBUG) {
		echo '<h2 style="color:#222; font-size:1.2em; margin:1em 0 1em 0; text-align:center; text-shadow:1px 1px 0 white;">' . preg_replace('/, called in.*/','',$e->getMessage()) . '</h2>';
		echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding-bottom:1em; overflow:hidden;">';
		echo '<strong style="display:block; background:#ebebeb; text-align:center; border-bottom:1px solid silver; line-height:2em; margin-bottom:1em;">'.(@$e->getFile()).' : '.(@$e->getLine()).'</strong>';
		$file = @file($e->getFile());
		$line = (int)@$e->getLine() - 1;
		if($file && $line) {
			for($i = max($line - 5, 0); $i < max($line - 5, 0) + 11; $i++) {
				if(!isset($file[$i])) { break; }
				echo '<div style="padding:0 1em; line-height:2em; '. ($line === $i ? 'background:lightyellow; position:relative; color:#8b0000; font-weight:bold; box-shadow:0 0 2px rgba(0,0,0,0.7)' : 'background:'.($i%2 ? '#ebebeb' : 'white').';') . '">';
				echo '<strong style="float:left; width:40px;">' . ($i + 1). '. </strong> ' . htmlspecialchars(trim($file[$i],"\r\n")) . "\n";
				echo '</div>';
			}
		}
		echo '</pre>';
		echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding-bottom:1em; overflow:hidden;">';
		echo '<strong style="display:block; background:#ebebeb; text-align:center; border-bottom:1px solid silver; line-height:2em; margin-bottom:1em;">Trace</strong>';
		foreach($e->getTrace() as $k => $trace) {
			if($k === 0) {
				$trace['file'] = @$e->getFile();
				$trace['line'] = @$e->getLine();
			}
			echo '<p style="margin:0; padding:0 1em; line-height:2em; '.($k==0?'background:lightyellow; border-top:1px solid gray; border-bottom:1px solid gray;':'').' '.($k%2 == 1 ? 'background:#ebebeb' : '').'">';
			echo '<span style="display:inline-block; min-width:500px;"><code style="color:green">'.(isset($trace['file'])?$trace['file']:'').'</code>';
			echo '<code style="color:gray"> '.(isset($trace['file'])?':':'').' </code>';
			echo '<code style="color:#8b0000">'.(isset($trace['line'])?$trace['line']:'').'</code></span> ';
			if(isset($trace['class'])) {
				echo '<code style="color:navy">'.$trace['class'].$trace['type'].$trace['function'].'()</code>';
			}
			else {
				echo '<code style="color:navy">'.$trace['function'].'()</code>';
			}
			echo '</p>';
		}
		echo '</pre>';
		
	}
	echo '</body></html>';
	die();
});
// turn all errors into exceptions
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
	// do not touch errors that are not marked for reporting
	if(!($errno & error_reporting())) {
		return true;
	}
	// do not throw exceptions for "lightweight" errors - those will end up in the log and will not break execution
	if($errno === E_NOTICE || $errno === E_DEPRECATED || $errno === E_STRICT || $errno === E_USER_NOTICE || $errno === E_USER_DEPRECATED ) {
		return false;
	}
	// throw exception for all others
	throw new ErrorException($errstr, $errno, $errno, $errfile, $errline);
});

// normalize session
$temp = session_get_cookie_params();
$temp['httponly'] = true;
// $temp['domain'] = 'example.com';
@call_user_func_array('session_set_cookie_params', array_values($temp));
@ini_set('session.use_cookies', "1");
@ini_set('session.use_only_cookies', "1");
@ini_set('session.use_trans_sid', "0");
@ini_set('session.name', 'RAICHUSESSID');
unset($temp);

// restrictions
ini_set('open_basedir', implode(
	PATH_SEPARATOR,
	array_filter(array_unique([
		sys_get_temp_dir(),
		ini_get('upload_tmp_dir'),
		realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR)
	]))
));

// unset globals
if(ini_get('register_globals')) {
	foreach([ &$_SESSION, &$_POST, &$_GET, &$_COOKIE, &$_REQUEST, &$_SERVER, &$_ENV, &$_FILES ] as $data) {
		foreach($data as $k => $v) {
			if($GLOBALS[$k] === $v) {
				unset($GLOBALS[$k]);
			}
		}
	}
}

// charset
@ini_set('default_charset', "UTF-8");
@mb_internal_encoding("UTF-8");

// class loading
require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';