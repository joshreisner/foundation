<?php
/**
 * 
 * HTTP
 * 
 * Methods for the location bar and cookies
 * 
 * @package Joshlib
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
	  * Convenience function for sites that use mod_rewrite to detect whether current page is the home page (usually located at /)
	  *
	  * @param	string	$path	The location of the home page
	  */
	static function home($path='/') {
		return (self::request('path') == $path);
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
	  * Tell if POST variables are present, and if specified, whether it's a particular form
	  *
	  * @param	string	$form	This works with a hidden form_id field in the form class to identify which form is being posted
	  * @return	boolean	
	  */
	static function posting($form=false) {
		if (!$form) return (!empty($_POST));
		if (!isset($_POST['form_id'])) return false;
		return ($form == $_POST['form_id']);
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
}
