<?php
spl_autoload_register(function ($class) {
	$class = __DIR__ . DIRECTORY_SEPARATOR . trim(str_replace('\\', DIRECTORY_SEPARATOR, $class), DIRECTORY_SEPARATOR) . '.php';
	if(is_file($class) && is_readable($class)) {
		require_once $class;
	}
});