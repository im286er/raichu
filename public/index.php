<?php
require_once('../config.php');
use \raichu\Raichu as raichu;

raichu::instance('\\raichu\\api\\REST', ['demoapp', 'api/rest']);
raichu::instance('\\raichu\\api\\RPC',  ['demoapp', 'api/rpc']);
raichu::instance('\\raichu\\api\\SOAP', ['demoapp', 'api/soap']);

raichu::user()->login();
var_dump(raichu::user()->valid());
die();