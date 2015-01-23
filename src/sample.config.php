<?php
define('DEBUG',   true);
define('LOGROOT', __DIR__);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

\raichu\Raichu::config([
	'database'         => 'mysqli://root@127.0.0.1/raichu',
	'memcache'         => false,
	'filecache'        => __DIR__,
	'sessionDatabase'  => true,
	'sessionCache'     => false,
	'session'          => true,
	'csrf'             => true,
	'uploadsDirectory' => __DIR__,
	'uploadsDatabase'  => true,
	'user'             => [
		'oauth'           => [
			'facebook'        => [ ],
			'google'          => [ ],
			'microsoft'       => [ ],
			'github'          => [ ],
			'linkedin'        => [ ]
		],
		//'ldap'            => 'is-bg.net',
		'token'           => '*S3cr$T(t0%En_KeY)*',
		'password'        => true,
		'certificate'     => true,
		'session'         => true,
		'database'        => true
	]
]);