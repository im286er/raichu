<?php
define('DEBUG',   true);
define('LOGROOT', __DIR__);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'autoload.php';

\raichu\Raichu::config([
	'database' => 'mysqli://root@127.0.0.1/raichu',
	'cache'    => 'file://' . __DIR__, // memcache://127.0.0.1
	'session'  => [
		'storage'    => 'database', // or 'cache' or 'file'
		'location'   => 'sessions' // table or path or null
	],
	'uploads'  => [
		'directory'  => __DIR__,
		'database'   => 'uploads' // table or null
	],
	'user'     => [
		'oauth'      => [
			'facebook'  => [ ],
			'google'    => [ ],
			'microsoft' => [ ],
			'github'    => [ ],
			'linkedin'  => [ ]
		],
		'ldap'        => false, // domain here
		'ldapdeco'    => false, // [ 'domain', 'user', 'pass' ],
		'token'       => 'secret_token_key_here',
		'password'    => 'users_password',
		'certificate' => true,
		'session'     => true,
		'database'    => 'users'
	],
	'csp'      => false,
	'csrf'     => true,
	'cors'     => false // enable CORS globally
]);