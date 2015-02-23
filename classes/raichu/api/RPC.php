<?php
namespace raichu\api;

class RPC
{
	public function __construct(\vakata\route\Route $router, $classes, $url = '') {
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
			->add([ 'GET', 'POST' ], '{**:resource}', function ($matches, $req, $res) use ($classes) {
				$data = $this->process($classes, $matches['resource'], $req->getQuery(), $req->getParams());

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
					case 'xml':
						$res->setContentType('xml');
						$data = array('root' => $data);
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
						echo '<?xml version="1.0" encoding="UTF-8" ?>' . array_to_xml($data, new \SimpleXMLElement('<root/>'))->root->asXML();
						break;
					case 'json':
						$res->setContentType('json');
						echo json_encode($data);
						break;
					case 'html':
						$res->setContentType('html');
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
			->with('');
	}
	public function process($classes, $commands, array $filter = null, array $data = null) {
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

			$class = null;
			if(is_string($classes)) {
				$class = $classes . '\\' . $instance;
			}
			if(!$class && is_array($classes) && isset($classes[$instance])) {
				$class = $classes[$instance];
			}
			if(!$class && is_array($classes)) {
				foreach($classes as $candidate) {
					if(array_reverse(explode('\\', $candidate))[0] === $instance) {
						$class = $candidate;
						break;
					}
				}
			}
			if(!$class) {
				throw new APIException('Invalid resource');
			}

			$instance = \raichu\Raichu::instance($class);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
		} catch(\Exception $e) {
			throw new APIException($e->getMessage(), 404);
		}
		$operation = array_shift($commands);
		if(!$operation || !method_exists($instance, $operation)) {
			throw new APIException('Invalid method', 405);
		}
		if($operation !== 'read' || !($instance instanceof \vakata\database\orm\TableInterface)) {
			return call_user_func([$instance, $operation], $data);
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
			$order .= (int)$filter['d'] ? ' DESC' : ' ASC';
		}
		$sql = [];
		$par = [];
		$col = 0;
		foreach($instance->getColumns() as $column) {
			if(isset($filter[$column])) {
				$col ++;
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
		$indexed = $instance->getIndexed();
		if(isset($filter['q']) && strlen($filter['q']) && count($indexed)) {
			$sql[] = ' MATCH ('.implode(',', $indexed).') AGAINST (?) ';
			$par[] = $filter['q'];
		}
		$sql = !count($sql) ? null : implode(' AND ', $sql);
		$par = !count($par) ? null : $par;

		if(isset($filter[$instance->getPrimaryKey()]) && $col === 1 && count($filter[$instance->getPrimaryKey()]) == 1) {
			return $instance->read($sql, $par, $order, $limit, $offst, true)->toArray(true);
		}
		$temp = $instance->read($sql, $par, $order, $limit, $offst);
		return [
			'meta' => $temp->meta(),
			'data' => $temp->toArray(true)
		];

	}
}