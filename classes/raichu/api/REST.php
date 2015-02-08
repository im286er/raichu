<?php
namespace raichu\api;

class REST
{
	public function __construct(\vakata\route\Route $router, $namespace, $url = '') {
		$url = trim($url, '/');
		$router
			->with($url)
			->options('{**}', function ($matches, $req, $res) {
				if(!$req->isCors()) {
					throw new APIException('Invalid input', 400);
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
			->add([ 'GET', 'POST', 'PUT', 'PATCH', 'HEAD' ], '{**:resource}', function ($matches, $req, $res) use ($namespace) {
				$data = $this->resource($namespace, $matches['resource'], $req->getQuery());
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
						$full = $req->getQuery('full') !== null;
						$temp = $data->toArray($full);
						if(!$full) {
							foreach($data->getTable()->getRelationKeys() as $key) {
								if($data instanceof \vakata\database\orm\TableRowInterface) {
									$temp["resource_url"] = trim($req->getUrl(false), '/');
									$temp[$key] = trim($req->getUrl(false), '/') . '/' . $key;
								}
								if($data instanceof \vakata\database\orm\TableRowsInterface) {
									foreach($temp as $k => $v) {
										$temp[$k]["resource_url"] = trim($req->getUrl(false), '/') . '/' . $v[$data->getTable()->getPrimaryKey()];
										$temp[$k][$key] = $temp[$k]["resource_url"] . '/' . $key;
									}
								}
							}
						}
						if($data instanceof \vakata\database\orm\TableRowsInterface) {
							$temp = [
								'meta' => $data->meta(),
								'data' => $temp
							];
						}
						$etag = md5(serialize($temp));

						$res->setHeader('E-Tag', $etag);
						if($req->hasHeader('If-None-Match') && $req->getHeader('If-None-Match') === $etag) {
							$res->setStatusCode(304);
						}
						switch($req->getResponseFormat()) {
							case 'xml':
								$res->setContentType('xml');
								break;
							case 'json':
								$res->setContentType('json');
								break;
							case 'html':
								$res->setContentType('html');
								break;
							default:
								throw new APIException('Not Acceptable', 406);
						}
						if($req->getMethod() === 'GET') {
							switch($req->getResponseFormat()) {
								case 'xml':
									$temp = array('root' => $temp);
									function array_to_xml($data, $xml) {
										foreach($data as $k => $v) {
											switch(gettype($v)) {
												case 'NULL':
													$v = 'NULL';
													break;
												case 'boolean':
													$v = $v ? 'TRUE' : 'FALSE';
													break;
												case 'object':
													$v = get_object_vars($v);
													break;
												case 'array':
													break;
												default:
													$v = (string)$v;
													break;
											}
											if(is_array($v)) {
												array_to_xml($v, $xml->addChild(is_numeric($k) ? 'item' : $k));
											}
											else {
												if(is_numeric($k) || preg_match('(^[a-z][a-z0-9_]+$)i', $k)) {
													$xml->addChild(is_numeric($k) ? 'item' : $k, $v);
												}
											}
										}
										return $xml;
									};
									echo '<?xml version="1.0" encoding="UTF-8" ?>' . array_to_xml($temp, new \SimpleXMLElement('<root/>'))->root->asXML();
									break;
								case 'json':
									echo json_encode($temp);
									break;
								case 'html':
									echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.htmlspecialchars($req->getUrl()).'</title></head><body style="background:#ebebeb;">' . "\n\n";
									echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
									foreach($res->getHeaders() as $k => $v) {
										echo '<strong style="display:inline-block; text-align:right; min-width:240px; margin-right:20px;">'.$k.':</strong>' . $v . "\n";
									}
									echo '</pre>';
									echo '<pre style="max-width:960px; margin:1em auto; border:1px solid silver; background:white; border-radius:4px; padding:1em; overflow:auto;">' . "\n";
									echo preg_replace('(&quot;(http.+?)&quot;)i', '&quot;<a href="\1">\1</a>&quot;', htmlspecialchars(json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
									echo '</pre>';
									echo '</body></html>';
									break;
							}
						}
						break;
					case 'PUT':
					case 'PATCH':
						if(!($data instanceof \vakata\database\orm\TableRow)) {
							throw new APIException('Invalid method', 405);
						}
						$data->fromArray($req->getParams());
						$data->save();
						$res->setHeader('Location', $req->getUrl());
						$res->setStatusCode(200);
						break;
					case 'POST':
						if(!($data instanceof \vakata\database\orm\TableRows)) {
							throw new APIException('Invalid method', 405);
						}
						$data[] = $req->getParams();
						$id = $data[count($data) - 1]->save();
						$res->setHeader('Location', $req->getUrl(false) . '/' . $id);
						$res->setStatusCode(201);
						break;
					case 'DELETE':
						if(!($this->instance instanceof \vakata\database\orm\TableRowInterface)) {
							throw new APIException('Invalid method', 405);
						}
						$data->delete();
						$res->setStatusCode(204);
						break;
					default:
						throw new APIException('Method not allowed', 405);
						break;
				}
			})
			->with('');
	}
	public function resource($namespace, $commands, array $filter = null) {
		if(!is_array($commands)) {
			$commands = explode('/', trim($commands, '/'));
		}
		if(!count($commands)) {
			throw new APIException('Invalid resource');
		}
		if(strrpos($commands[count($commands)-1], '.') !== false) {
			$commands[count($commands)-1] = substr($commands[count($commands)-1], 0, strrpos($commands[count($commands)-1], '.'));
		}

		try {
			$instance = array_shift($commands);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
			$instance = \raichu\Raichu::instance($namespace . '\\' . $instance);
			if(!$instance || !($instance instanceof \vakata\database\orm\TableInterface)) {
				throw new APIException('Invalid resource');
			}
		} catch(\Exception $e) {
			throw new APIException($e->getMessage(), 404);
		}

		if(!$filter) { $filter = []; }
		$filter = array_merge([
			'l' => null,
			'p' => 0,
			'o' => null,
			'd' => 0,
			'q' => ''
		], $filter);
		$order = isset($filter['o']) && in_array($filter['o'], $instance->getColumns()) ? $filter['o'] : null;
		$limit = isset($filter['l']) && (int)$filter['l'] ? (int)$filter['l'] : null;
		$offst = isset($limit) && isset($filter['p']) ? (int)$filter['p'] * $limit : null;
		if(isset($filter['d']) && isset($order)) {
			$order .= (int)$filter['d'] ? ' DESC' : 'ASC';
		}

		array_unshift($commands, 'read');
		while($command = array_shift($commands)) {
			$arg = [];
			if($command !== 'read' && !in_array($command, $instance->getTable()->getRelationKeys())) {
				throw new APIException('Invalid resource', 404);
			}
			if(is_int(reset($commands)) || is_numeric(reset($commands))) {
				$primary = $instance instanceof \vakata\database\orm\TableInterface ? 
					$instance->getPrimaryKey() : 
					$instance->getTable()->getRelations()[$command]['table']->getPrimaryKey();
				$par = [ (int)array_shift($commands) ];
				$sql = ' '.$primary.' = ? ';
				$arg = [ $sql, $par, null, null, null, true ];
			}
			else if(reset($commands) === false) {
				$sql = [];
				$par = [];
				$columns = $instance instanceof \vakata\database\orm\TableInterface ? 
					$instance->getColumns() : 
					$instance->getTable()->getRelations()[$command]['table']->getColumns();
				foreach($columns as $column) {
					if(isset($filter[$column])) {
						if(!is_array($filter[$column])) {
							$filter[$column] = [$filter[$column]];
						}
						if(isset($filter[$column]['beg']) && isset($filter[$column]['end'])) {
							$sql[] = ' ' . $column . ' BETWEEN ? AND ? ';
							$par[] = $filter[$column]['beg'];
							$par[] = $filter[$column]['end'];
							continue;
						}
						if(count($filter[$column])) {
							$sql[] = ' ' . $column . ' IN ('.implode(',', array_fill(0, count($filter[$column]), '?')).') ';
							$par = array_merge($par, $filter[$column]);
							continue;
						}
					}
				}
				$indexed = $instance instanceof \vakata\database\orm\TableInterface ? 
					$instance->getIndexed() : 
					$instance->getTable()->getRelations()[$command]['table']->getIndexed();
				if(isset($filter['q']) && strlen($filter['q']) && count($indexed)) {
					$sql[] = ' MATCH ('.implode(',', $instance->getTable()->getIndexed()).') AGAINST (?) ';
					$par[] = $filter['q'];
				}
				$sql = !count($sql) ? null : implode(' AND ', $sql);
				$par = !count($par) ? null : $par;
				$arg = [ $sql, $par, $order, $limit, $offst ];
			}
			$instance = call_user_func_array([$instance, $command], $arg);

			if($instance === null) {
				throw new APIException('Invalid resource', 404);
			}
		}
		return $instance;
	}
}