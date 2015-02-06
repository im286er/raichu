<?php
require_once('./../config.php');
use \raichu\Raichu as raichu;

raichu::route()
	->options('{**}', function ($matches, $req, $res) {
		if(!$req->isCors()) {
			throw new Exception('Invalid input', 400);
		}
		$res->setHeader('Access-Control-Allow-Origin', $req->getHeader('Origin'));
		$res->setHeader('Access-Control-Max-Age', '3600');
		$res->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, HEAD, DELETE');
		$headers = [];
		if($req->hasHeader('Access-Control-Request-Headers')) {
			$headers = array_map('trim', array_filter(explode(',', $req->getHeader('Access-Control-Request-Headers'))));
		}
		$headers[] = 'Authorization';
		$res->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
	})
	->add([ 'GET', 'POST', 'PUT', 'PATCH', 'HEAD' ], '/', function ($matches, $req, $res) {
		throw new Exception('Choose REST, RPC or SOAP', 400);
	})
	->add([ 'GET', 'POST', 'PUT', 'PATCH', 'HEAD' ], 'rest/{**}', function ($matches, $req, $res) {
		$data = raichu::api('rest', array_slice($req->getUrlSegments(), 1), $req->getQuery());
		$full = $data->read($req->getQuery('full') !== null);
		$etag = md5(serialize($full));

		if($req->isCors()) {
			$res->setHeader('Access-Control-Allow-Origin', $req->getHeader('Origin'));
			$headers = [];
			if($req->hasHeader('Access-Control-Request-Headers')) {
				$headers = array_map('trim', array_filter(explode(',', $req->getHeader('Access-Control-Request-Headers'))));
			}
			$headers[] = 'Authorization';
			$res->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
		}

		switch($req->getMethod()) {
			case 'HEAD':
			case 'GET':
				$res->setHeader('E-Tag', $etag);
				if($req->hasHeader('If-None-Match') && $req->getHeader('If-None-Match') === $etag) {
					$res->setStatusCode(304);
				}
				switch($req->getResponseFormat()) {
					case 'json':
						$res->setContentType('json');
						break;
					case 'html':
						$res->setContentType('html');
						break;
					default:
						throw new Exception('Not Acceptable', 406);
				}
				if($req->getMethod() === 'GET') {
					switch($req->getResponseFormat()) {
						case 'json':
							echo json_encode($full);
							break;
						case 'html':
							echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.htmlspecialchars($req->getUrl()).'</title></head><body style="background:#ebebeb;">' . "\n\n";
							echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
							foreach($res->getHeaders() as $k => $v) {
								echo '<strong style="display:inline-block; text-align:right; min-width:240px; margin-right:20px;">'.$k.':</strong>' . $v . "\n";
							}
							echo '</pre>';
							echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
							echo preg_replace('(&quot;(http.+?)&quot;)i', '&quot;<a href="\1">\1</a>&quot;', htmlspecialchars(json_encode($full, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
							echo '</pre>';
							echo '</body></html>';
							break;
					}
				}
				break;
			case 'PUT':
			case 'PATCH':
				$id = $data->update($req->getParams());
				$res->setHeader('Location', $req->getUrl());
				$res->setStatusCode(200);
				break;
			case 'POST':
				$id = $data->create($req->getParams());
				$res->setHeader('Location', $req->getUrl(false) . '/' . $id);
				$res->setStatusCode(201);
				break;
			case 'DELETE':
				$data->delete();
				$res->setStatusCode(204);
				break;
			default:
				throw new Exception('Method not allowed', 405);
				break;
		}
	})
	->add([ 'GET', 'POST' ], 'soap/{**}', function ($matches, $req, $res) {
		$data = raichu::api('soap', array_slice($req->getUrlSegments(), 1));
		try {
			$soap = new SoapServer(null, array(
				'uri' => $req->getUrl(false)
			));
			$soap->setObject($data);
			$soap->handle();
		}
		catch(Exception $ex) {
			$soap->fault($ex->getCode(), $ex->getMessage());
		}
	})
	->add([ 'GET', 'POST' ], 'rpc/{**}', function ($matches, $req, $res) {
		$data = raichu::api('rpc', array_slice($req->getUrlSegments(), 1), $req->getQuery(), $req->getParams());

		if($req->isCors()) {
			$res->setHeader('Access-Control-Allow-Origin', $req->getHeader('Origin'));
			$headers = [];
			if($req->hasHeader('Access-Control-Request-Headers')) {
				$headers = array_map('trim', array_filter(explode(',', $req->getHeader('Access-Control-Request-Headers'))));
			}
			$headers[] = 'Authorization';
			$res->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
		}

		switch($req->getResponseFormat()) {
			case 'json':
				echo json_encode($data);
				break;
			case 'html':
				echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.htmlspecialchars($req->getUrl()).'</title></head><body style="background:#ebebeb;">' . "\n\n";
				echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
				foreach($res->getHeaders() as $k => $v) {
					echo '<strong style="display:inline-block; text-align:right; min-width:240px; margin-right:20px;">'.$k.':</strong>' . $v . "\n";
				}
				echo '</pre>';
				echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
				echo preg_replace('(&quot;(http.+?)&quot;)i', '&quot;<a href="\1">\1</a>&quot;', htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
				echo '</pre>';
				echo '</body></html>';
				break;
		}
	});
