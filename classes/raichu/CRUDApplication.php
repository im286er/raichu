<?php
namespace raichu;

class CRUDApplication extends Application
{
	public function instance($instance) {
		try {
			return parent::instance($instance);
		} catch(\Exception $e) {
			if(($temp = $this->config('modules'))) {
				//echo $instance; die();
				if(isset($temp[strtolower($instance)]) && isset($temp[strtolower($instance)]['crud'])) {
					return raichu::instance('\\raichu\\CRUDModule', [$temp[strtolower($instance)]['crud']]);
				}
				foreach($temp as $module) {
					if(isset($module['submenu']) && isset($module['submenu'][strtolower($instance)]) && isset($module['submenu'][strtolower($instance)]['crud'])) {
						return raichu::instance('\\raichu\\CRUDModule', [$module['submenu'][strtolower($instance)]['crud']]);
					}
				}
			}
			throw new \Exception('Invalid class', 404);
		}
	}
}