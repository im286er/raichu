<?php
namespace raichu\api;

class RESTResource
{
	protected $namespace = null;
	protected $instance = null;

	// filters (users with limited visibility), special fields (created / updated / user), limited changes (locked fields), permissions
	// paging - where?
	public function __construct($namespace, $commands, array $filter = null) {
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

		$filter = array_merge([
			'l' => 0,
			'p' => 0,
			'o' => null,
			'd' => 0,
			'q' => ''
		], $filter);
		$order = isset($filter['o']) && in_array($filter['o'], $instance->getColumns()) ? $filter['o'] : null;
		$limit = isset($filter['l']) && (int)$filter['l'] ? (int)$filter['l'] : 0;
		$offst = (int)$filter['p'] * $limit;
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
				$params = [ (int)array_shift($commands) ];
				$filter = ' '.$primary.' = ? ';
				$arg = [ $filter, $params, null, null, null, true ];
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

		$this->instance = $instance;
	}

	public function create(array $params) {
		if($this->instance instanceof \vakata\database\orm\TableRows) {
			$this->instance[] = $params;
			return $this->instance[count($this->instance) - 1]->save();
		}
		throw new APIException('Invalid method', 405);
	}
	public function update(array $params) {
		if($this->instance instanceof \vakata\database\orm\TableRow) {
			$this->instance->fromArray($params);
			return $this->instance->save();
		}
		throw new APIException('Invalid method', 405);
	}
	public function read($full = true) {
		$temp = $this->instance->toArray($full);
		if(!$full) {
			foreach($this->instance->getTable()->getRelationKeys() as $key) {
				if($this->instance instanceof \vakata\database\orm\TableRowInterface) {
					$temp["resource_url"] = trim(\raichu\Raichu::request()->getUrl(false), '/');
					$temp[$key] = trim(\raichu\Raichu::request()->getUrl(false), '/') . '/' . $key;
				}
				if($this->instance instanceof \vakata\database\orm\TableRowsInterface) {
					foreach($temp as $k => $v) {
						$temp[$k]["resource_url"] = trim(\raichu\Raichu::request()->getUrl(false), '/') . '/' . $v[$this->instance->getTable()->getPrimaryKey()];
						$temp[$k][$key] = $temp[$k]["resource_url"] . '/' . $key;
					}
				}
			}
		}
		return $temp;
	}
	public function delete() {
		if($this->instance instanceof \vakata\database\orm\TableRowInterface) {
			$this->instance->delete();
		}
		throw new APIException('Invalid method', 405);
	}
	public function raw() {
		return $this->instance;
	}
}