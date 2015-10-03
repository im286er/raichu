<?php
namespace vakata\log;

class Log implements LogInterface
{
	const EMERGENCY	= 1;
	const ALERT		= 2;
	const CRITICAL	= 4;
	const ERROR		= 8;
	const WARNING	= 16;
	const NOTICE	= 32;
	const INFO		= 64;
	const DEBUG		= 128;
	const ALL		= 255;

	protected $level;
	protected $directory;
	protected $additional_context;

	public function __construct($level = null, $directory = null, array $additional_context = []) {
		$this->level = $level !== null ? $level : static::ALL;
		$this->directory = rtrim($directory, '/\\');
		$this->additional_context = $additional_context;
	}

	protected function getLevel($severity) {
		switch ($severity) {
			case 1:		return 'emergency';
			case 2:		return 'alert';
			case 4:		return 'critical';
			case 8:		return 'error';
			case 16:	return 'warning';
			case 32:	return 'notice';
			case 64:	return 'info';
			case 128:	return 'debug';
		}
	}
	protected function getDirectory() {
		if ($this->directory) {
			return $this->directory;
		}
		if (ini_get('error_log')) {
			return dirname(ini_get('error_log'));
		}
		if (isset($_SERVER['SCRIPT_NAME'])) {
			return dirname($_SERVER['SCRIPT_NAME']);
		}
		if (getcwd()) {
			return getcwd();
		}
	}
	protected function log($severity, $message, array $context = []) {
		if (!((int)$severity & $this->level)) {
			return true;
		}
		if (isset($context['exception']) && $context['exception'] instanceof \Exception) {
			$context['exception'] = [
				'code' => $context['exception']->getCode(),
				'mess' => $context['exception']->getMessage(),
				'file' => $context['exception']->getFile(),
				'line' => $context['exception']->getLine(),
				'stck' => $context['exception']->getTrace()
			];
		}
		$context = array_merge($this->additional_context, $context);
		$directory = $this->getDirectory();
		if (!is_dir($directory)) {
			mkdir($directory, 0644, true);
		}
		return (bool)@error_log(
			date("[d-M-Y H:i:s e] ") . $message . "\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n",
			3,
			$directory . '/' . $this->getLevel($severity) . '.log'
		);
	}

	public function emergency($message, array $context = []) {
		return $this->log(static::EMERGENCY, $message, $context);
	}
	public function alert($message, array $context = []) {
		return $this->log(static::ALERT, $message, $context);
	}
	public function critical($message, array $context = []) {
		return $this->log(static::CRITICAL, $message, $context);
	}
	public function error($message, array $context = []) {
		return $this->log(static::ERROR, $message, $context);
	}
	public function warning($message, array $context = []) {
		return $this->log(static::WARNING, $message, $context);
	}
	public function notice($message, array $context = []) {
		return $this->log(static::NOTICE, $message, $context);
	}
	public function info($message, array $context = []) {
		return $this->log(static::INFO, $message, $context);
	}
	public function debug($message, array $context = []) {
		return $this->log(static::DEBUG, $message, $context);
	}
}
