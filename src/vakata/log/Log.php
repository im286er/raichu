<?php
namespace vakata\log;

class Log implements LogInterface
{
	protected function log($severity, $message, array $context = []) {
		if (isset($context['exception']) && $context['exception'] instanceof \Exception) {
			$context['exception'] = [
				'code' => $context['exception']->getCode(),
				'mess' => $context['exception']->getMessage(),
				'file' => $context['exception']->getFile(),
				'line' => $context['exception']->getLine(),
				'stck' => $context['exception']->getTrace()
			];
		}
		return (bool)@error_log('LOG ' . ucwords($severity) . ': ' . $message . (count($context) ? "\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '') );
	}
	public function emergency($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function alert($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function critical($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function error($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function warning($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function notice($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function info($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
	public function debug($message, array $context = []) {
		return $this->log(__FUNCTION__, $message, $context);
	}
}