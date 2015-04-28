<?php
namespace vakata\raichu;

class Raichu
{
	private static $conf = [];

	private static $repl = [];
	private static $inst = [];
	private static $args = [];

	private static $name = [];

	private static $expr = [];

	private function __construct() { }

	public static function register($alias, $class, array $args = []) {
		if(isset(static::$name[strtolower($alias)])) {
			throw new \Exception('Shortcut already registered', 500);
		}
		static::$name[strtolower($alias)] = [ $class, $args ];
	}
	public static function instance($class, array $args = [], $named_only = false) {
		if($named_only && !isset(static::$name[strtolower($class)])) {
			throw new \Exception('Class not found', 404);
		}
		if(isset(static::$name[strtolower($class)])) {
			list($class, $args) = static::$name[strtolower($class)];
		}
		if(isset(static::$repl[$class])) {
			$class = static::$repl[$class];
		}
		if(isset(static::$args[$class])) {
			$args = array_merge($args, static::$args[$class]);
		}
		if(isset(static::$inst[$class])) {
			return static::$inst[$class];
		}

		$args			= array_values($args);
		$reflection		= new \ReflectionClass($class);
		$constructor	= $reflection->getConstructor();
		$arguments		= [];
		$instance		= null;
		try {
			if($constructor) {
				foreach($constructor->getParameters() as $k => $v) {
					if($v->getClass()) {
						$name = $v->getClass()->name;
						if(count($args) && $args[0] instanceof $name) {
							$arguments[] = array_shift($args);
							continue;
						}
						$temp = static::instance('\\' . $name);
						if($temp !== null) {
							$arguments[] = $temp;
							continue;
						}
						$arguments[] = $v->isOptional() ? $v->getDefaultValue() : null;
						continue;
					}
					if(count($args)) {
						$arguments[] = array_shift($args);
						continue;
					}
					$arguments[] = $v->isOptional() ? $v->getDefaultValue() : null;
				}
			}
			$instance = count($arguments) ? $reflection->newInstanceArgs($arguments) : new $reflection->name;
		} catch(\ReflectionException $ignore) {
			$instance = null;
		}
		if(array_key_exists($class, static::$inst)) {
			static::$inst[$class] = $instance;
		}
		return $instance;
	}
	public static function __callStatic($class, array $args = []) {
		return static::instance($class, $args);
	}
	public static function invoke($class, $method, array $args = [], array $construct = [], $named_only = false) {
		if(is_string($class)) {
			$class = static::instance($class, $construct, $named_only);
		}
		$clsn = strtolower(get_class($class));
		$exec = [];
		foreach(static::$expr as $k => $v) {
			$k = explode('->', $k);
			if($k[0] === '*' || strpos($clsn, $k[0]) !== false || is_a($class, $k[0])) {
				if(!isset($k[1]) || $k[1] === '*' || strtolower($method) === $k[1]) {
					$exec[] = &$v;
				}
			}
		}
		try {
			foreach($exec as $v) {
				foreach($v as $kk => $vv) {
					if($kk === 'before') {
						call_user_func($vv, [ 'class' => $class, 'method' => $method, 'args' => $args ]);
					}
				}
			}
			$data = call_user_func_array([$class, $method], $args);
			foreach($exec as $v) {
				foreach($v as $kk => $vv) {
					if($kk === 'after') {
						call_user_func($vv, [ 'class' => $class, 'method' => $method, 'args' => $args, 'result' => $data ]);
					}
				}
			}
		}
		catch(\Exception $e) {
			$excp = strtolower(get_class($e));
			$rethrow = true;
			foreach(static::$expr as $k => $v) {
				$k = explode('->', $k);
				if(strpos($excp, $k[0]) !== false) {
					if(isset($v['exception'])) {
						$rethrow = $rethrow && call_user_func($vv, [ 'class' => $class, 'method' => $method, 'args' => $args, 'exception' => $e ]) !== false;
					}
				}
			}
			if($rethrow) {
				throw $e;
			}
		}
		return $data;
	}
	public static function decorate($expression, callable $f, $mode = 'after') {
		$expression = explode(',', $expression);
		foreach($expression as $e) {
			static::$expr[trim(strtolower($e))][$mode] = $f;
		}
	}

	public static function get($key) {
		$key = explode('.', $key);
		$tmp = static::$conf;
		foreach($key as $k) {
			if(!isset($tmp[$k])) {
				return null;
			}
			$tmp = $tmp[$k];
		}
		return $tmp;
	}
	public static function config(array $settings = []) {
		static::$conf = $settings;

		// single instances
		static::$inst['\\vakata\\database\\DB'] = null;
		static::$inst['\\vakata\\cache\\Dummy'] = null;
		static::$inst['\\vakata\\cache\\Memcache'] = null;
		static::$inst['\\vakata\\cache\\Filecache'] = null;
		static::$inst['\\vakata\\http\\Request'] = null;
		static::$inst['\\vakata\\http\\Response'] = null;
		static::$inst['\\vakata\\http\\Url'] = null;
		static::$inst['\\vakata\\route\\Route'] = null;
		static::$inst['\\vakata\\upload\\Upload'] = null;
		static::$inst['\\vakata\\upload\\UploadDatabase'] = null;
		static::$inst['\\vakata\\user\\User'] = null;
		static::$inst['\\vakata\\log\\Log'] = null;
		static::$inst['\\vakata\\event\\Event'] = null;
		static::$inst['\\vakata\\random\\Random'] = null;
		static::$inst['\\vakata\\user\\User'] = null;
		static::$inst['\\vakata\\localization\\Lang'] = null;

		// database
		if(isset($settings['database']) && $settings['database']) {
			static::$repl['\\vakata\\database\\DatabaseInterface'] = static::$repl['db'] = '\\vakata\\database\\DB';
			static::$args[static::$repl['db']] = [$settings['database']];
		}

		// cache
		static::$repl['vakata\\cache\\CacheInterface'] = static::$repl['cache'] = '\\vakata\\cache\\Dummy';
		if(isset($settings['cache']) && $settings['cache'] && strpos($settings['cache'], 'memcache://') !== false) {
			static::$repl['\\vakata\\cache\\CacheInterface'] = static::$repl['cache'] = '\\vakata\\cache\\Memcache';
		}
		if(isset($settings['cache']) && $settings['cache'] && strpos($settings['cache'], 'file://') !== false) {
			static::$repl['\\vakata\\cache\\CacheInterface'] = static::$repl['cache'] = '\\vakata\\cache\\Filecache';
		}
		if(isset($settings['cache']) && $settings['cache']) {
			static::$args[static::$repl['cache']] = [explode('://', $settings['cache'])[1]];
		}

		// uploads
		if(isset($settings['uploads']) && $settings['uploads']) {
			static::$repl['file'] = '\\vakata\\file\\FileUpload';
			static::$args['\\vakata\\file\\FileUpload'] = [$settings['uploads']['directory'],$settings['uploads']['database']];
			static::$repl['upload'] = static::$repl['\\vakata\\cache\\UploadInterface'] = '\\vakata\\upload\\Upload';
			static::$args['\\vakata\\upload\\Upload'] = [$settings['uploads']['directory']];
		}
		if(isset($settings['uploads']) && $settings['uploads'] && isset($settings['uploads']['database'])) {
			static::$repl['file'] = '\\vakata\\file\\FileDatabase';
			static::$args['\\vakata\\file\\FileDatabase'] = [$settings['uploads']['directory'],$settings['uploads']['database']];
			static::$repl['upload'] = static::$repl['\\vakata\\cache\\UploadInterface'] = '\\vakata\\upload\\UploadDatabase';
			static::$args['\\vakata\\upload\\UploadDatabase'] = [$settings['uploads']['directory'],$settings['uploads']['database']];
		}

		// session handling
		if(isset($settings['session']) && $settings['session']) {
			if(isset($settings['session']['storage'])) {
				if($settings['session']['storage'] === 'file' && isset($settings['session']['location']) && $settings['session']['location']) {
					session_save_path($settings['session']['location']);
				}
				if($settings['session']['storage'] === 'database') {
					session_set_save_handler(static::instance('\\vakata\\session\\SessionDatabase', [$settings['session']['location']]));
				}
				if($settings['session']['storage'] === 'cache') {
					session_set_save_handler(static::instance('\\vakata\\session\\SessionCache', [$settings['session']['location']]));
				}
			}
			if(session_status() === PHP_SESSION_NONE) {
				session_start();
			}
		}

		$lg = static::instance('\\vakata\\log\\Log');
		$rt = static::instance('\\vakata\\route\\Route');
		$ur = static::instance('\\vakata\\http\\Url');
		$rq = static::instance('\\vakata\\http\\Request');
		$rs = static::instance('\\vakata\\http\\Response');

		// user
		$m = static::instance('\\vakata\\user\\authentication\\Dummy');
		if(isset($settings['user']) && $settings['user']) {
			$auth = [];
			if(isset($settings['user']['oauth']) && is_array($settings['user']['oauth'])) {
				foreach($settings['user']['oauth'] as $provider => $args) {
					$args[] = $ur->base() . 'login/' . $provider . '/callback';
					$auth[] = static::instance('\\vakata\\user\\authentication\\OAuth\\' . ucwords($provider), $args);
				}
			}
			if(isset($settings['user']['password']) && $settings['user']['password']) {
				$auth[] = static::instance('\\vakata\\user\\authentication\\Password', is_array($settings['user']['password']) ? $settings['user']['password'] : [$settings['user']['password']]);
			}
			if(isset($settings['user']['ldap']) && $settings['user']['ldap']) {
				$auth[] = static::instance('\\vakata\\user\\authentication\\Ldap', [$settings['user']['ldap']]);
			}
			if(isset($settings['user']['certificate']) && $settings['user']['certificate']) {
				$auth[] = static::instance('\\vakata\\user\\authentication\\Certificate');
			}
			$m = static::instance('\\vakata\\user\\authentication\\Manager', [$auth]);
			if(isset($settings['user']['session']) && $settings['user']['session']) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorSession', [$m]);
			}
			if(isset($settings['user']['token']) && $settings['user']['token']) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorToken', [$m, $settings['user']['token']]);
			}
			if(isset($settings['user']['database']) && $settings['user']['database']) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorDatabase', [$m, $settings['user']['database']]);
			}
			if(isset($settings['user']['ldapdeco']) && is_array($settings['user']['ldapdeco'])) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorLDAP', array_merge([$m], $settings['user']['ldapdeco']));
			}
			if(isset($settings['user']['permissions']) && $settings['user']['permissions']) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorPermissions', array_merge([$m], [$settings['user']['permissions']]));
			}
			if(isset($settings['user']['groups']) && $settings['user']['groups']) {
				$m = static::instance('\\vakata\\user\\authentication\\DecoratorGroups', array_merge([$m], [$settings['user']['groups']]));
			}
			if(isset($settings['user']['totp']) && $settings['user']['totp']) {
				$c = is_array($settings['user']['totp']) && isset($settings['user']['totp']['table']) && $settings['user']['totp']['table'] ?
						'\\vakata\\user\\authentication\\TOTP\\DatabaseTOTP' :
						'\\vakata\\user\\authentication\\TOTP\\TOTP';
				$a = is_array($settings['user']['totp']) ? $settings['user']['totp'] : [];
				if(isset($a['table']) && $a['table'] === true) { unset($a['table']); }
				$m = static::instance($c, [$m, $a]);
			}
		}
		static::$repl['user'] = static::$repl['\\vakata\\user\\UserInterface'] = '\\vakata\\user\\User';
		static::$args['\\vakata\\user\\User'] = [$m];
		
		// misc replacements
		static::$repl['route']		= '\\vakata\\route\\Route';
		static::$repl['url']		= '\\vakata\\http\\Url';
		static::$repl['request']	= '\\vakata\\http\\Request';
		static::$repl['response']	= '\\vakata\\http\\Response';
		static::$repl['log']		= '\\vakata\\log\\Log';
		static::$repl['event']		= '\\vakata\\event\\Event';
		static::$repl['random']		= '\\vakata\\random\\Random';
		static::$repl['lang']		= '\\vakata\\localization\\Lang';

		// languages
		if(isset($settings['lang']) && is_array($settings['lang'])) {
			$ln = static::instance('\\vakata\\localization\\Lang');
			foreach($settings['lang'] as $k => $v) {
				$ln->add($k, $v);
			}
			$ln->setDefault($rq->getLanguage(array_keys($settings['lang'])[0]));
		}

		// views
		static::$repl['view']		= '\\vakata\\view\\View';
		if(isset($settings['views']) && is_dir(realpath($settings['views']))) {
			\vakata\view\View::dir($settings['views']);
		}

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
			$csp = $ur->base() . 'csp-report';
			$rs->addFilter(function ($body, $mime) use ($rs, $rq, $csp) {
				if(strpos($mime, 'htm') !== false) {
					$rs->setHeader("Content-Security-Policy", "default-src 'self'; script-src 'self' 'unsafe-inline' *.".$ur->domain()." ajax.googleapis.com; style-src 'self' 'unsafe-inline' *.".$ur->domain()."; img-src *; font-src *; frame-src 'self' facebook.com *.facebook.com *.twitter.com *.google.com; object-src youtube.com *.youtube.com vbox7.com *.vbox7.com vimeo.com *.vimeo.com; media-src youtube.com *.youtube.com vbox7.com *.vbox7.com vimeo.com *.vimeo.com; report-uri " . $csp);
				}
				return $body;
			});
			if(trim($ur->current(false), '/') === $csp) {
				$rs->removeHeaders();
				$rs->setBody(null);
				while(ob_get_level()) { ob_end_clean(); }
				$lg->warning('CSP:' . trim($rq->getBody()));
				die();
			}
		}
		// cors
		if(isset($settings['cors']) && $settings['cors']) {
			$rs->enableCors($rq);
		}
		// flush routes and response at end
		register_shutdown_function(function () use ($ur, $rq, $rs, $rt) {
			$rt->run($ur, $rq, $rs);
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