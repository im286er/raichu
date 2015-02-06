<?php
namespace raichu\api;

class RPCResource
{
	protected $namespace = null;
	protected $instance  = null;
	protected $operation = null;
	protected $filter    = null;
	protected $data      = null;

	public function __construct($namespace, $commands, array $filter = null, array $data = null) {
		$this->namespace = $namespace;

		if(!is_array($commands)) {
			$commands = explode('/', trim($commands, '/'));
		}
		if(strrpos($commands[count($commands)-1], '.') !== false) {
			$commands[count($commands)-1] = substr($commands[count($commands)-1], 0, strrpos($commands[count($commands)-1], '.'));
		}

		try {
			$instance = array_shift($commands);
			if(!$instance) {
				throw new APIException('Invalid resource');
			}
			$instance = \raichu\Raichu::get()->instance($this->namespace . '\\' . $instance);
			if(!$instance || !($instance instanceof \vakata\database\orm\TableInterface)) {
				throw new APIException('Invalid resource');
			}
		} catch(\Exception $e) {
			throw new APIException($e->getMessage(), 404);
		}
		$operation = array_shift($commands);
		if(!method_exists($instance, $operation)) {
			throw new \Exception('Invalid method');
		}

		$this->filter = $filter;
		$this->data   = $data;

		$this->instance = $instance;
		$this->operation = $operation;
	}

	public function read() {
		$filter = array_merge([
			'l' => 0,
			'p' => 0,
			'o' => null,
			'd' => 0,
			'q' => ''
		], $this->filter);
		$order = isset($filter['o']) && in_array($filter['o'], $this->instance->getColumns()) ? $filter['o'] : null;
		$limit = isset($filter['l']) && (int)$filter['l'] ? (int)$filter['l'] : 0;
		$offst = (int)$filter['p'] * $limit;
		if(isset($filter['d']) && isset($order)) {
			$order .= (int)$filter['d'] ? ' DESC' : 'ASC';
		}
		$sql = [];
		$par = [];
		$col = 0;
		foreach($this->instance->getColumns() as $column) {
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
		$indexed = $this->instance->getIndexed();
		if(isset($filter['q']) && strlen($filter['q']) && count($indexed)) {
			$sql[] = ' MATCH ('.implode(',', $indexed).') AGAINST (?) ';
			$par[] = $filter['q'];
		}
		$sql = !count($sql) ? null : implode(' AND ', $sql);
		$par = !count($par) ? null : $par;

		if(isset($filter[$this->instance->getPrimaryKey()]) && $col === 1 && count($filter[$this->instance->getPrimaryKey()]) == 1) {
			return $this->instance->read($sql, $par, $order, $limit, $offst, true);
		}
		$temp = $this->instance->read($sql, $par, $order, $limit, $offst);
		return [
			'meta' => [ 'filter' => $filter, 'count' => $temp->cnt() ],
			'data' => $temp->toArray(true)
		];
	}
	public function process() {
		if($this->operation === 'read' && $this->instance instanceof \vakata\database\orm\TableInterface) {
			return $this->read();
		}
		return call_user_func([$this->instance, $this->operation], $this->data);
	}
	public function raw() {
		return $this->instance;
	}
}