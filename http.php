<?php
/**
 * 
 * HTTP
 * 
 * Methods for the location bar and cookies
 * 
 * @package Foundation
 */

class http {

	private static $cache = array(); //cache parsed urls
	private static $request = false; //cache constructed full request URL string


	/**
	  * Check whether AJAX request
	  *
	  * @return	boolean			True for ajax request, false for not
	  */
	static function ajax() {
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'));
	}

	/**
	  * Send a content-type header
	  *
	  * @param	string 	$type		The content type to send
	  */
	static function content_type($type) {
		$types = array(
			'html'	=> 'text/html',
			'xml'	=> 'application/xml',
		);

		if (!isset($types[$type])) error_handle('content::type called for unhandled type ' . $type);

		if (!headers_sent()) header('Content-type: ' . $types[$type] . '; charset=' . config::get('charset', true));
	}


	/**
	  * Get or set a cookie
	  *
	  * @param	string	$key	The name of the cookie
	  * @param	string	$value	Optional value to set
	  * @return	string			If called with just one argument, will return cookie string value
	  */
	static function cookie($key) {
		if (func_num_args() > 1) {
			//setting cookie value
			$value = func_get_arg(1);
			$time = (empty($value)) ? time()-3600 : mktime(0, 0, 0, 1, 1, 2030);
			if (func_num_args() == 3) $time = 0;
			$_COOKIE[$key] = $value;
			setcookie($key, $value, $time, '/', '.' . self::request('domain'));
		} else {
			//getting cookie value
			if (!isset($_COOKIE[$key]) || empty($_COOKIE[$key])) return false;
			return str::escape($_COOKIE[$key]);
		}
	}

	/**
	  * Tell if $_GET variables are present 
	  *
	  * @param	mixed	$keys		String, array, or comma-separated
	  * @param  mixed   $default 	Optional default in case not found (only applies to single $key)
	  * @return	boolean	or string
	  */
	static function get($keys=false, $default=null) {
		if (!$keys) return (!empty($_GET));

		$keys = a::arguments($keys);

		if (count($keys) == 1) return a::get($_GET, $keys[0], $default);

		foreach ($keys as $key) {
			if (!a::get($_GET, $key)) return false;
		}

		return true;
	}

	/**
	  * Redirect to a URL
	  *
	  * @param	string	$url	Optional, the target location.  If unspecified, refreshes the current URL, good for clearing post input
	  */
	static function go($url=false) {
		if ($url === false) $url = self::request();
		if (!headers_sent()) {
			header('Location: ' . $url, true, 302);
		} else {
			echo '<script type="text/javascript">window.location.href="' . $url . '";</script>';
			echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
		}
        exit;
    }
	
	/**
	  * Convenience function for sites that use mod_rewrite to detect whether current page is the home page (usually located at /)
	  *
	  * @param	string	$path	The location of the home page
	  */
	static function home($path='/') {
		return (self::request('path') == $path);
	}

	/**
	  * Detect a browser's preferred language
	  *
	  * @return	string			Two letter language, eg en
	  */
	static function language() {
		//return en, es, fr, ru from browser settings
		if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return config::get('language');
	    $code = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
	    $code = explode(',', $code[0]);
	    return substr($code[0], 0, 2);
	}
	
	/**
	  * Parse a given URL
	  *
	  * @param	string	$url	The URL to parse
	  * @param	string  $part	The optional sub-part of the URL to look for
	  * @return	mixed			The array of URL parts	
	  */
	static function parse($url, $part=false) {
	
		if (!isset(self::$cache[$url])) {
			
			//start with php's version but still save the $url
			$parts = array_merge(parse_url($url));
			$parts['full'] = $url;
					
			//create a path_query with the path and the query string together
			$parts['path_query'] = (empty($parts['query'])) ? $parts['path'] : $parts['path'] . '?' . $parts['query'];
			
			//get array of folders
			$parts['folders'] = array_values(array_filter(explode('/', $parts['path'])));
			if (($last = count($parts['folders'])) && strstr($parts['folders'][$last - 1], '.')) $parts['file'] = array_pop($parts['folders']);
			
			//set folder and subfolder variables, not sure if necessary
			if (!empty($parts['folders'][0])) $parts['folder']			= $parts['folders'][0];
			if (!empty($parts['folders'][1])) $parts['subfolder']		= $parts['folders'][1];
			if (!empty($parts['folders'][2])) $parts['subsubfolder']	= $parts['folders'][2];
			
			//if there's a special ?id=234 get that number
			if (!empty($_GET['id'])) $parts['id'] = intval($_GET['id']);
			
			//get domain, the variable formerly known as sanswww
			$parts['domain'] = str::starts($parts['host'], 'www.');
			if (!$parts['domain']) $parts['domain'] = $parts['host'];
			
			//be fancy
			ksort($parts);
			
			//save to cache
			self::$cache[$url] = $parts;
		}

		//die(draw_array($parts));

		return ($part) ? @self::$cache[$url][$part] : self::$cache[$url];
	}
	
	/**
	  * Tell if $_POST variables are present 
	  *
	  * @param	mixed	$keys		String, array, or comma-separated
	  * @param  mixed   $default 	Optional default in case not found (only applies to single $key)
	  * @return	boolean	or string
	  */
	static function post($keys=false, $default=null) {
		if (!$keys) return (!empty($_POST));

		$keys = a::arguments($keys);

		if (count($keys) == 1) return a::get($_POST, $keys[0], $default);

		foreach ($keys as $key) {
			if (!a::get($_POST, $key)) return false;
		}

		return true;
	}

	/**
	  * Gets the requested URL, or part of the requested URL
	  *
	  * @param	string	$part	The optional sub-part of the request URL to look for
	  * @return	string	
	  */
	static function request($part='full') {
		//build and cache the full requested URL
		if (self::$request === false) {
			$s = empty($_SERVER['HTTPS']) ? '' : ($_SERVER['HTTPS'] == "on") ? 's' : '';
			$sp = strtolower($_SERVER['SERVER_PROTOCOL']);
			$protocol = substr($sp, 0, strpos($sp, '/')) . $s;
			$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (':' . $_SERVER['SERVER_PORT']);
			self::$request = $protocol . '://' . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
		}
		$return = self::parse(self::$request);
		//echo html::dump($return);
		return (isset($return[$part])) ? $return[$part] : false;
	}
	
	/**
	  * Gets the referring URL, or part of the referring URL.  Needed for error handling.
	  *
	  * @param	string	$part	The optional sub-part of the referrer URL to look for
	  * @return	string	
	  */
	static function referrer($part='full') {
		if (empty($_SERVER['HTTP_REFERER'])) return false;
		$return = self::parse($_SERVER['HTTP_REFERER']);
		return @$return[$part];
	}

	/**
	  * Tell if $_SESSION variables are present 
	  *
	  * @param	mixed	$keys		String, array, or comma-separated
	  * @param  mixed   $default 	Optional default in case not found (only applies to single $key)
	  * @return	boolean	or string
	  */
	static function session($keys=false, $default=null) {
		if (!isset($_SESSION)) session_start();

		if (!$keys) return (!empty($_SESSION)); //added for consistency with get and post, not sure if works

		$keys = a::arguments($keys);

		if (count($keys) == 1) return a::get($_SESSION, $keys[0], $default);

		foreach ($keys as $key) {
			if (!a::get($_SESSION, $key)) return false;
		}

		return true;
	}

	/**
	  * Throw a status header
	  *
	  * @param	int	$code		code to throw, eg 404 or 200
	  */
	public static function status($code) {

		switch ($code) {
			case 100: $text = 'Continue'; break;
			case 101: $text = 'Switching Protocols'; break;
			case 200: $text = 'OK'; break;
			case 201: $text = 'Created'; break;
			case 202: $text = 'Accepted'; break;
			case 203: $text = 'Non-Authoritative Information'; break;
			case 204: $text = 'No Content'; break;
			case 205: $text = 'Reset Content'; break;
			case 206: $text = 'Partial Content'; break;
			case 300: $text = 'Multiple Choices'; break;
			case 301: $text = 'Moved Permanently'; break;
			case 302: $text = 'Moved Temporarily'; break;
			case 303: $text = 'See Other'; break;
			case 304: $text = 'Not Modified'; break;
			case 305: $text = 'Use Proxy'; break;
			case 400: $text = 'Bad Request'; break;
			case 401: $text = 'Unauthorized'; break;
			case 402: $text = 'Payment Required'; break;
			case 403: $text = 'Forbidden'; break;
			case 404: $text = 'Not Found'; break;
			case 405: $text = 'Method Not Allowed'; break;
			case 406: $text = 'Not Acceptable'; break;
			case 407: $text = 'Proxy Authentication Required'; break;
			case 408: $text = 'Request Time-out'; break;
			case 409: $text = 'Conflict'; break;
			case 410: $text = 'Gone'; break;
			case 411: $text = 'Length Required'; break;
			case 412: $text = 'Precondition Failed'; break;
			case 413: $text = 'Request Entity Too Large'; break;
			case 414: $text = 'Request-URI Too Large'; break;
			case 415: $text = 'Unsupported Media Type'; break;
			case 500: $text = 'Internal Server Error'; break;
			case 501: $text = 'Not Implemented'; break;
			case 502: $text = 'Bad Gateway'; break;
			case 503: $text = 'Service Unavailable'; break;
			case 504: $text = 'Gateway Time-out'; break;
			case 505: $text = 'HTTP Version not supported'; break;
			
			default:
			trigger_error('Unknown http status code "' . htmlentities($code) . '"');
			break;
		}

		$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

		if (!headers_sent()) header($protocol . ' ' . $code . ' ' . $text);
	}

	/**
	  * Get or set a $_SESSION user_id
	  *
	  * @param	mixed	$id			The ID to set, if specified
	  * @return	int 				The returned ID, if not specified
	  */
	static function user($id=false) {
		if (!isset($_SESSION)) session_start();

		if ($id) {
			$_SESSION[config::get('session.user_id')] = $id;;
		} else {
			return self::session(config::get('session.user_id'));
		}
	}

}
