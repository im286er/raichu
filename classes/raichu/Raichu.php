<?php
namespace raichu;

class Raichu
{
	private static $dice = null;
	private static $repl = [];

	private function __construct() { }

	public static function instance($c, array $args = []) {
		if(!static::$dice) {
			static::$dice = new \Dice\Dice;
		}
		if(isset(static::$repl[$c])) {
			$c = static::$repl[$c];
		}
		return static::$dice->create($c, $args);
	}
	public static function __callStatic($m, $a) {
		list($c, $m) = array_pad(explode('_', $m, 2), 2, null);
		$c = static::instance($c);
		if(!$m && count($a)) { $m = 'get'; }
		return !$m ? $c : call_user_func_array([$c, $m], $a);
	}

	public static function config(array $settings = []) {
		if(!static::$dice) {
			static::$dice = new \Dice\Dice;
		}

		// single instances
		$shared = new \Dice\Rule;
		$shared->shared = true;
		static::$dice->addRule([
			'vakata\\database\\DB',
			'vakata\\cache\\Memcache',
			'vakata\\cache\\Filecache',
			'vakata\\http\\Request',
			'vakata\\http\\Response',
			'vakata\\route\\Route',
			'vakata\\upload\\Upload',
			'vakata\\upload\\UploadDatabase',
			'vakata\\user\\User',
			'vakata\\log\\Log',
			'vakata\\event\\Event',
			'vakata\\random\\Random'
		], $shared);

		// interface substitutions
		$substitution = new \Dice\Rule;
		$substitution->substitutions['vakata\\database\\DatabaseInterface'] = new \Dice\Instance('vakata\\database\\DB');
		$substitution->substitutions['vakata\\cache\\CacheInterface'] = isset($settings['cache']) && $settings['cache'] && strpos($settings['cache'], 'memcache://') !== false ?
			new \Dice\Instance('vakata\\cache\\Memcache') :
			new \Dice\Instance('vakata\\cache\\Filecache');
		$substitution->substitutions['vakata\\upload\\UploadInterface'] = isset($settings['uploads']) && $settings['uploads'] && isset($settings['uploads']['database']) ?
			new \Dice\Instance('vakata\\upload\\UploadDatabase') :
			new \Dice\Instance('vakata\\upload\\Upload');
		$substitution->substitutions['vakata\\user\\UserInterface'] = new \Dice\Instance('vakata\\user\\User');
		static::$dice->addRule('*', $substitution);

		// constructor parameters
		if(isset($settings['database']) && $settings['database']) {
			$database = new \Dice\Rule;
			$database->shared = true;
			$database->constructParams = [$settings['database']];
			static::$dice->addRule('vakata\\database\\DB', $database);
			static::$repl['db'] = 'vakata\\database\\DB';
		}
		static::$repl['cache'] = 'vakata\\cache\\Dummy';
		if(isset($settings['cache']) && $settings['cache']) {
			$cache = new \Dice\Rule;
			$cache->shared = true;
			$cache->constructParams = [explode('://', $settings['cache'])[1]];
			static::$dice->addRule('vakata\\cache\\' . (strpos($settings['cache'], 'memcache://') !== false ? 'Memcache' : 'Filecache'), $cache);
			static::$repl['cache'] = 'vakata\\cache\\' . (strpos($settings['cache'], 'memcache://') !== false ? 'Memcache' : 'Filecache');
		}
		if(isset($settings['uploads']) && $settings['uploads']) {
			$uploads = new \Dice\Rule;
			$uploads->shared = true;
			$uploads->constructParams = [$settings['uploads']['directory']];
			static::$dice->addRule('vakata\\upload\\Upload', $uploads);
			static::$repl['upload'] = 'vakata\\upload\\Upload';
			if(isset($settings['uploads']['database']) && $settings['uploads']['database']) {
				$uploads_database = new \Dice\Rule;
				$uploads_database->shared = true;
				$uploads_database->constructParams = [$settings['uploads']['directory'], $settings['uploads']['database']];
				static::$dice->addRule('vakata\\upload\\UploadDatabase', $uploads_database);
				static::$repl['upload'] = 'vakata\\upload\\UploadDatabase';
			}
		}

		// session handling
		if(isset($settings['session']) && $settings['session']) {
			if(isset($settings['session']['storage'])) {
				if($settings['session']['storage'] === 'file' && isset($settings['session']['location']) && $settings['session']['location']) {
					session_save_path($settings['session']['location']);
				}
				if($settings['session']['storage'] === 'database') {
					session_set_save_handler(static::$dice->create('vakata\\session\\SessionDatabase', [$settings['session']['location']]));
				}
				if($settings['session']['storage'] === 'cache') {
					session_set_save_handler(static::$dice->create('vakata\\session\\SessionCache', [$settings['session']['location']]));
				}
			}
			if(session_status() === PHP_SESSION_NONE) {
				session_start();
			}
		}

		$lg = static::$dice->create('vakata\\log\\Log');
		$rt = static::$dice->create('vakata\\route\\Route');
		$rq = static::$dice->create('vakata\\http\\Request');
		$rs = static::$dice->create('vakata\\http\\Response');

		// user handling
		$m = static::$dice->create('\\vakata\\user\\authentication\\Dummy');
		if(isset($settings['user']) && $settings['user']) {
			$auth = [];
			if(isset($settings['user']['oauth']) && is_array($settings['user']['oauth'])) {
				foreach($settings['user']['oauth'] as $provider => $args) {
					$args[] = $rq->getUrlBase() . 'login/' . $provider . '/callback';
					$auth[] = static::$dice->create('\\vakata\\user\\authentication\\OAuth\\' . ucwords($provider), $settings);
				}
			}
			if(isset($settings['user']['password']) && $settings['user']['password']) {
				$auth[] = static::$dice->create('\\vakata\\user\\authentication\\Password', [$settings['user']['password']]);
			}
			if(isset($settings['user']['ldap']) && $settings['user']['ldap']) {
				$auth[] = static::$dice->create('\\vakata\\user\\authentication\\Ldap', [$settings['user']['ldap']]);
			}
			if(isset($settings['user']['certificate']) && $settings['user']['certificate']) {
				$auth[] = static::$dice->create('\\vakata\\user\\authentication\\Certificate');
			}
			$m = static::$dice->create('\\vakata\\user\\authentication\\Manager', [$auth]);
			if(isset($settings['user']['session']) && $settings['user']['session']) {
				$m = static::$dice->create('\\vakata\\user\\authentication\\DecoratorSession', [$m]);
			}
			if(isset($settings['user']['token']) && $settings['user']['token']) {
				$m = static::$dice->create('\\vakata\\user\\authentication\\DecoratorToken', [$m, $settings['user']['token']]);
			}
			if(isset($settings['user']['database']) && $settings['user']['database']) {
				$m = static::$dice->create('\\vakata\\user\\authentication\\DecoratorDatabase', [$m, $settings['user']['database']]);
			}
		}
		$user = new \Dice\Rule;
		$user->shared = true;
		$user->constructParams = [$m];
		static::$dice->addRule('vakata\\user\\User', $user);
		static::$repl['user'] = 'vakata\\user\\User';

		static::$repl['route'] = 'vakata\\route\\Route';
		static::$repl['request'] = 'vakata\\http\\Request';
		static::$repl['response'] = 'vakata\\http\\Response';
		static::$repl['log'] = 'vakata\\log\\Log';
		static::$repl['event'] = 'vakata\\event\\Event';
		static::$repl['random'] = 'vakata\\random\\Random';

		// IE header and debug JSON
		$rs->addFilter(function ($body, $mime) use ($rs) {
			if(strpos($mime, 'htm') !== false) {
				$rs->setHeader("X-UA-Compatible", "IE=edge,chrome=1");
			}
			if(defined('DEBUG') && DEBUG && strpos($mime, 'json') !== false && ($temp = @json_decode($body, true))) {
				return json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			}
			return $body;
		});
		// csrf
		if(isset($settings['csrf']) && $settings['csrf'] && session_status() === PHP_SESSION_ACTIVE) {
			$rq->checkCSRF();
			$rs->addFilter(function ($body, $mime) {
				if(strpos($mime, 'htm') !== false) {
					if(isset($_SESSION) && is_array($_SESSION) && isset($_SESSION['_csrf_token'])) {
						$body = preg_replace(
							'@<form[^>]+method="post"[^>]*>@ui', 
							"\\0" . '<input type="hidden" style="display:none;" name="_csrf_token" value="'.htmlspecialchars($_SESSION['_csrf_token']).'" />', 
							$body
						);
					}
				}
				return $body;
			});
		}
		// csp
		if(isset($settings['csp']) && $settings['csp']) {
			$csp = $rq->getUrlBase() . 'csp-report';
			$rs->addFilter(function ($body, $mime) use ($rs, $rq, $csp) {
				if(strpos($mime, 'htm') !== false) {
					$rs->setHeader("Content-Security-Policy", "default-src 'self'; script-src 'self' 'unsafe-inline' *.".$rq->getUrlDomain()." ajax.googleapis.com; style-src 'self' 'unsafe-inline' *.".$rq->getUrlDomain()."; img-src *; font-src *; frame-src 'self' facebook.com *.facebook.com *.twitter.com *.google.com; object-src youtube.com *.youtube.com vbox7.com *.vbox7.com vimeo.com *.vimeo.com; media-src youtube.com *.youtube.com vbox7.com *.vbox7.com vimeo.com *.vimeo.com; report-uri " . $csp);
				}
				return $body;
			});
			if(trim($rq->getUrl(false), '/') === $csp) {
				$rs->removeHeaders();
				$rs->setBody(null);
				while(ob_get_level()) { ob_end_clean(); }
				$lg->warning('CSP:' . trim($rq->getBody()));
				die();
			}
		}
		// flush routes and response at end
		register_shutdown_function(function () use ($rq, $rs, $rt) {
			if(!$rt->isEmpty()) {
				$rt->run($rq, $rs);
			}
			$rs->send();
		});
	}
}


/*
public static function api($class, $method = null, array $data = array()) {
	if(!defined('CLASSES') || !is_dir(CLASSES)) {
		throw new ApiException('Classes dir not found', 404);
	}
	if(!self::$classes) {
		self::$classes = scandir(CLASSES);
		foreach(self::$classes as $k => $v) {
			if(!is_file(CLASSES . DIRECTORY_SEPARATOR . $v) || !preg_match('(^class\.(.*?)\.php$)i', $v) || strpos($v, 'class._') === 0) {
				unset(self::$classes[$k]);
				continue;
			}
			self::$classes[$k] = str_replace(['class.','.php'], '', $v);
		}
	}

	$instance = strip_tags(basename(str_replace('\\','/',$class)));
	$temp = null;
	if(strpos($instance,':')) {
		$temp = explode(':', $instance);
		$instance = in_array($temp[0], self::$classes) ? $temp[0] : $temp[1];
		$temp = $temp[0];
	}
	if(!in_array($instance, self::$classes)) {
		throw new ApiException('Not found: ' . $instance, 404);
	}

	$instance = 'raichu\\api\\' . $instance;
	$instance = ($instance === 'raichu\\api\\model') ? new $instance($temp) : new $instance();
	if(!$method) {
		return $instance;
	}

	$method = strip_tags(basename($method));
	if(!is_callable([$instance, $method])) {
		throw new ApiException('Not found: ' .  $method, 404);
	}
	return call_user_func_array([$instance, $method], [$data]);
}
// get API
public static function url_api($class, $method) {
	return url::get(SEGMENTS_API . '/' . $class . '/' . $method);
}
// get FILE
public static function url_file($file, $width = false, $height = false) {
	$segs = [ SEGMENTS_FILE ];
	$data = [];
	try {
		if(!($file instanceof \vakata\io\FileStruct)) {
			$file = file::get($file);
		}
	}
	catch(\Exception $e) { return null; }
	$segs[] = $file->id;

	if((int)$width) { $segs[] = (int)$width; }
	if((int)$width && (int)$height) { $segs[] = (int)$height; }
	if((int)$width) { $segs[] = (int)$height; }
	if((int)$width) { $data['h'] = md5($file->name . (int)$width . (int)$height . $file->hash . (defined('SECRET') ? SECRET : 'secret') ); }
	$segs[] = $file->name;
	return url::get(implode('/', $segs), (int)$width ? $data : false);
}

// no place to put this?
public static function plupload($settings = array(), $populate = array(), $pics = false, $no_script = false, $setting = []) {
	$settings = array_merge(
		array(
			//'prefix'			=> md5(date('U').microtime().rand(0,99)),
			'prefix'			=> random::string(),
			'name'				=> 'file',
			'max_file_size'		=> (file::upload_max_size() - 1024 * 8),
			'max_total_size'	=> false,
			'file_limit'		=> false,
			'disabled'			=> false
		), $settings);
	$settings['max_file_size'] = min($settings['max_file_size'],(file::upload_max_size() - 1024 * 8));
	if($settings['file_limit'] != 1) { $settings['name'] .= '[]'; }
	$str  = '';
	$str .= '<div '.($no_script ? ' data-config=\'["'.str_replace('/api/','/',url::get('file')).'", "'.$settings['prefix'].'", { "image" : '.($pics ? 'true' : 'false').', "plupload" : "true", "prefix" : "'.$settings['prefix'].'", "_csrf_token" : "'.$_SESSION['_csrf_token'].'" }, "'.$settings['name'].'", '.(int)$settings['max_file_size'].', '.(int)$settings['file_limit'].', '.(int)$settings['max_total_size'].', "'.str_replace('/api/','/',url::get('file')).'", '.($settings['disabled'] ? 'true' : 'false').', '.json_encode($setting, JSON_HEX_TAG | JSON_HEX_APOS).']\' '  : '').' id="plup_'.$settings['prefix'].'" class="plup_container '.($settings['disabled'] ? 'plup_disabled' : '').' cf '.($settings['file_limit'] == 1 ? 'plup_single' : '' ).' '.($pics && (int)$settings['file_limit'] !== 1 ? 'plup_pics' : '').'">';
	$str .= '  <input type="file" name="'.$settings['name'].'" '.($settings['disabled'] ? ' disabled="disabled" ' : '').' />';
	$str .= '  <a id="plup_pick_'.$settings['prefix'].'" href="#"  class="plup_pick btn btn-warning" style="display:none;"><i class="glyphicon glyphicon-upload icon-white"></i> '.($settings['file_limit'] == 1 ? 'Избери файл' : 'Избери файлове' ).'</a>';
	if((int)$settings['file_limit'] !== 1 && is_array($populate) && count($populate)) {
		$str .= '  <a href="'.raichu::url_get('file/zip/').'" class="plup_pick btn btn-info download-all" title="изтегли всички"><i class="glyphicon glyphicon-download icon-white"></i></a>';
	}
	/*
				$setting = [
					'field1'	=> [
						'type'		=> 'select',
						'label'		=> 'Джувотно',
						'visible'	=> true,
						'options'	=> [ 'прасе', 'катерица' ],
						'default'	=> 'носорог'
					],
					'field2'	=> [
						'type'		=> 'text',
						'label'		=> 'Тест',
						'visible'	=> true,
						'default'	=> ''
					]
				];* /
	$extra = [];
	if($setting && count($setting)) {
		$str .= '<div class="plup_modal"><div>';
		foreach($setting as $name => $field) {
			$extra[] = '<span rel="'.$name.'">' . (isset($field['default']) && $field['default'] ? $field['default'] : '') . '</span>';
			$str .= '<div>';
			switch($field['type']) {
				case 'select':
					$str .= '<div class="form-group">';
					$str .= '<label class="col-md-2 control-label">' . $field['label'] . '</label>';
					$str .= '<div class="col-md-10">';
					$str .= '<select name="'.$name.'" class="form-control">';
					foreach($field['options'] as $k => $v) {
						$str .= '<option value="'.htmlspecialchars($v).'">'.htmlspecialchars($v).'</option>';
					}
					$str .= '</select>';
					$str .= '</div>';
					$str .= '</div>';
					break;
				case 'text':
					$str .= '<div class="form-group">';
					$str .= '<label class="col-md-2 control-label">' . $field['label'] . '</label>';
					$str .= '<div class="col-md-10">';
					$str .= '<input type="text" name="'.$name.'" class="form-control" value="'.$field['default'].'" />';
					$str .= '</div>';
					$str .= '</div>';
					break;
				case 'checkbox':
					$str .= '<div class="form-group">';
					$str .= '<div class="col-md-10 col-md-offset-2">';
					$str .= '<label class="checkbox">';
					$str .= '<input type="checkbox" name="'.$name.'" class="form-control" value="1" />';
					$str .= $field['label'] . '</label>';
					$str .= '</div>';
					$str .= '</div>';
					break;
			}
			$str .= '</div>';
		}
		
		$str .= '<div style="text-align:center;"><a href="#" class="btn btn-primary plup_setting_save">Запази</a> <a href="#" class="btn btn-link plup_setting_close">откажи</a></div></div></div>';
	}
	$extra[] = 'качен на ';
	$str .= '  <div style="display:none;" class="new_file_info">';
	if($setting && count($setting) && !$settings['disabled']) {
		$str .= '<a class="plup_setting" href="#"><i class="glyphicon glyphicon-cog"></i></a>';
	}
	$str .= '<small style="color:silver; float:right; margin-right:10px;">'.implode('&nbsp;&nbsp;&bull;&nbsp;&nbsp;', $extra).'</small>';
	$str .= '  </div>';
	if((int)$settings['file_limit'] === 1) {
		$str .=  '<input class="plup_single_filler" '.($settings['disabled'] ? ' disabled="disabled" ' : '').' type="hidden" name="' . $settings['name'] . '" value="" />';
	}
	$str .= '  <div class="plup_files cf" style="display:none;">';
	if(is_array($populate)) {
		foreach($populate as $file) {
			$file = file::get($file);
			if($file) {
				$str .= '<div class="plup_file plup_ok '.($pics && (int)$settings['file_limit'] !== 1 ? 'plup_pic' : '').'" data-setting=\''.( @json_encode(json_decode($file->settings, true), JSON_HEX_APOS | JSON_HEX_TAG) ).'\' id="file_' . $file->id . '" data-thumbnail="'.raichu::url_file($file, 120, 120).'">';
				// $str .=  '<span class="plup_icon"></span>';
				if(!$settings['disabled']) {
					$str .=  '<span class="plup_close" title="Премахни"><i class="glyphicon glyphicon-remove"></i></span>';
				}
				$extra = [];
				if($setting && count($setting)) {
					if(!$settings['disabled']) {
						$str .=  '<a class="plup_setting" href="#"><i class="glyphicon glyphicon-cog"></i></a>';
					}
					$fdata = @json_decode($file->settings, true);
					if(is_array($setting)) {
						foreach($setting as $name => $field) {
							if(isset($field['visible'])) {
								if(is_array($fdata) && isset($fdata[$name])) {
									$extra[] = '<span rel="'.$name.'">' . $fdata[$name] . '</span>';
								}
								else {
									$extra[] = '<span rel="'.$name.'">' . (isset($field['default']) && $field['default'] ? $field['default'] : '') . '</span>';
								}
							}
						}
					}
				}
				$extra[] = 'качен на '.date('d.m.Y H:i', $file->modified);
				if(count($extra)) {
					$str .= '<small style="color:silver; float:right; margin-right:10px;">'.implode('&nbsp;&nbsp;&bull;&nbsp;&nbsp;', $extra).'</small>';
				}
				//$str .=  '<span class="plup_date">' . date('d.m.Y H:i', $file->modified) . '</span>';
				$size = $file->size / 1024;
				$unit = 'KB';
				if($size > 1000) {
					$size = $size / 1024;
					$unit = 'MB';
				}
				if($size > 1000) {
					$size = $size / 1024;
					$unit = 'GB';
				}
				$str .=  '<a draggable="false" target="_blank" href="'.raichu::url_file($file).'" class="plup_title" title="изтегли файла ('.ceil($size).' '.$unit.')"><i class="glyphicon glyphicon-download"></i> ' . $file->name .  '</a>';
				$str .=  '<span class="plup_progress"><span class="plup_progress_inner" style="width:100%;">&#160;</span></span>';
				$str .=  '<input class="plup_file_array" '.($settings['disabled'] ? ' disabled="disabled" ' : '').' type="hidden" name="' . $settings['name'] . '" value="' . $file->id . '" title="' . $file->size . '" rel="' . $file->name . '" />';
				$str .= '</div>';
			}
		}
	}
	$str .= '  </div>';
	$str .= '</div>';
	$tmp = '/api/';
	if(!$no_script) {
		$str .= '<scr'.'ipt type="text/javascript">';
		$str .= '$(function () { plupload.vakata.create("'.str_replace($tmp,'/',url::get('file')).'", "'.$settings['prefix'].'", { "image" : '.($pics ? 'true' : 'false').', "plupload" : "true", "prefix" : "'.$settings['prefix'].'", "_csrf_token" : "'.$_SESSION['_csrf_token'].'" }, "'.$settings['name'].'", '.(int)$settings['max_file_size'].', '.(int)$settings['file_limit'].', '.(int)$settings['max_total_size'].', "'.str_replace($tmp,'/',url::get('file')).'", '.($settings['disabled'] ? 'true' : 'false').', '.json_encode($setting).'); });';
		$str .= '</script>';
	}
	return $str;
}

public static function net_api($class, $func = '', $data = array(), $method = "POST", $format = 'json') {
	$prefix = 'api/';
	$context = array(
		'http'		=> array(
			'method'		=> $method,
			'user_agent'	=> self::user_get('ua'),
			'header'		=> ''
			//,'ignore_errors'	=> true
		)
		// 'socket' => array('bindto' => '127.0.0.1:0')
	);
	if(is_array($data) && count($data)) {
		$data = http_build_query($data);
		$context['http']['header']  = "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($data) . "\r\n";
		$context['http']['content'] = $data;
	}
	$context['http']['ignore_errors'] = DEBUG; // adding this so that it is debuggable
	
	$context['http']['header'] .= 'X-Raichu-User: ' . user::get('id') . "\r\n";
	$context['http']['header'] .= 'X-Raichu-Ip: ' . user::get('ip') . "\r\n";
	$context['http']['header'] .= 'X-Raichu-Session: ' . session_id() . "\r\n";
	if(isset($_SESSION['_csrf_token'])) {
		$context['http']['header'] .= 'X-Raichu-Csrf: ' . $_SESSION['_csrf_token'] . "\r\n";
	}
	$url = self::url_get($prefix.'rpc/'.$class.'/'.$func, array('format' => $format));
	session_write_close();
	$data = @file_get_contents($url, false, stream_context_create($context));
	session_start();
	if(isset($http_response_header)) {
		foreach($http_response_header as $r) {
			if(strpos($r, 'Status:') === 0) {
				$r = (int)str_replace('Status:','',$r);
				break;
			}
		}
		if($r && $r > 399) {
			$temp = $data;
			$data = json_decode($data, true);
			if(!$data) { $data = $temp; }
			throw new \Exception((string)$data, $r);
		}
	}
	if($format === 'json' && $data) {
		$temp = $data;
		$data = json_decode($data, true);
		if($data === null) {
			throw new \Exception($temp, 500);
		}
	}
	return $data;
}
*/