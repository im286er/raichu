<?php
require_once('src/config.php');
use \raichu\Raichu as raichu;

//raichu::response()->addFilter(function ($body, $mime) {
//	if(strpos($mime, 'json') !== false && ($temp = @json_decode($body, true))) {
//		return json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//	}
//	return $body;
//});

raichu::route()
	->with('api')
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
		->with('')
	->with('api/rest')
		->add([ 'GET', 'POST', 'PUT', 'PATCH', 'HEAD' ], function ($matches, $req, $res) {
			$data = raichu::api('rest', array_slice($req->getUrlSegments(), 2), $req->getQuery());
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
		->with('')
	->with('api/soap')
		->add([ 'GET', 'POST' ], function ($matches, $req, $res) {
			$data = raichu::api('soap', array_slice($req->getUrlSegments(), 2));
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
	->with('api/rpc')
		->add([ 'GET', 'POST' ], function ($matches, $req, $res) {
			$data = raichu::api('rpc', array_slice($req->getUrlSegments(), 2), $req->getQuery(), $req->getParams());

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
		})
		->with('')
	->error(function ($matches, $req, $res, $exception = null) {
		$code = 404;
		$mssg = 'В момента не можем да обслужим заявката Ви.';
		if($exception) {
			$code = $exception->getCode() >= 200 && $exception->getCode() <= 503 ? $exception->getCode() : 500;
			$mssg = $exception->getMessage();
			// $mssg = 'В момента не можем да обслужим заявката Ви.';
		}
		$res->removeHeaders();
		$res->setStatusCode($code);
		switch($req->getResponseFormat()) {
			case 'json':
				$res->setContentType('json');
				echo json_encode($mssg);
				break;
			case 'xml':
				$res->setContentType('xml');
				echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n\n";
				echo '<error><![CDATA['.str_replace(']]>', '', $mssg).']]></error>';
				break;
			case 'html':
				$res->setContentType('html');
				echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Грешка</title></head><body style="background:#ebebeb;">' . "\n\n";
				echo '<h1 style="font-size:1.4em; text-align:center; margin:2em 0 0 0; color:#8b0000; text-shadow:1px 1px 0 white;">'.htmlspecialchars($mssg).'</h1>' . "\n\n";
				echo '</body></html>';
				break;
			case 'text':
			default:
				$res->setContentType('txt');
				echo $msg;
				break;
		}
		die();

		//$user = new \vakata\database\orm\Table(raichu::db(), 'users');
		//$user->hasOne('users_authentication', 'user_id', 'auth');
		//echo $user->one(3)->auth()->provider;
		//$tree = new \vakata\database\orm\Table(raichu::db(), 'tree_mixed');
		//$chld = clone $tree;
		//$tree->hasMany($chld, 'pid', 'children');
		//$chld->hasMany(clone $tree, 'pid', 'children');
		//$temp = $tree->filter("pid = 0")->get();
		//die();
	})
	->run(raichu::request(), raichu::response());
