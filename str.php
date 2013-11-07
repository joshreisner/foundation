<?php
/**
 * 
 * STR
 * 
 * Methods for string manipulation
 * 
 * @package Foundation
 */

class str {

	/**
	  * Shorten a $string to a particular $length
	  *
	  * @param	mixed	$string		Input string
	  * @param	string	$length 	
	  * @param	boolean	$relative 	If today, return time
	  * @return	string				The formatted date string
	  */
	static function clip($string, $length=50, $append='…') {
		//todo, write function
		return $string;
	}

	/**
	  * Format a date
	  *
	  * @param	mixed	$timestamp	Either a string or a unix date
	  * @param	string	$format 	Optional format per php date
	  * @param	boolean	$relative 	If today, return time
	  * @return	string				The formatted date string
	  */
	static function date($timestamp, $relative=true, $format=false) {
		if (empty($timestamp)) return null;
		if (!is_int($timestamp)) $timestamp = strtotime($timestamp);
		if ($format == 'sql') return date('Y-m-d H:i:00', $timestamp);
		if ($relative && (date('Y-m-d') == date('Y-m-d', $timestamp))) {
			//show time for dates that are today
			if (!$format) $format = config::get('time.format');
		} else {
			if (!$format) $format = config::get('date.format');
		}
		return strftime($format, $timestamp);
	}

	/**
	  * Encode a string for email obfuscation
	  *
	  * @param	string	$string		The string to encode
	  * @return	string				The encoded string
	  */
	static function encode($string) {
		$return = '';
		$length = strlen($string);
		for ($i = 0; $i < $length; $i++) $return .= '&#' . ord($string[$i]);
		return $return;
	}

	/**
	  * See if $haystack ends in $needle
	  * Todo: check if this is utf8 compatible
	  *
	  * @param	string	$haystack			The string to search in
	  * @param	string	$needle				The string to search for
	  * @param	string	$return_if_false	What to return if no match
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function ends($haystack, $needle, $return_if_false=false) {
		if ($needle == $haystack) return true;
		$length = strlen($needle);
		if (strtolower(substr($haystack, (0 - $length))) == strtolower($needle)) return substr($haystack, 0, strlen($haystack) - $length);
		return $return_if_false;
	}

	/**
	  * Unescape a string for quote-encapsulation
	  *
	  * @param	string	$string		The string
	  * @return	string				The string
	  */
	static function escape($string) {
		if (!is_string($string)) return false;
		return str_replace("'", "''", stripslashes($string));
		return $string;
	}
	
	/**
	  * Generate a random alphanumeric string
	  *
	  * @param	int		$length		The length of the string to be generated
	  * @return	string				The random string
	  */
	static function random($lengthgth=20) {
		$characters = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
		$rand_max	= count($characters) - 1;
		$return		= '';
		for ($i = 0; $i < $lengthgth; $i++) $return .= $characters[rand(0, $rand_max)];
		return $return;
	}
	
	/**
	  * Sanitze a string for database or urls
	  * Todo: check if this is utf8 compatible
	  *
	  * @param	string	$string 	The string to operate on
	  * @param	string	$delimiter	replace-spaces-with-this-string
	  * @return	string				Returns sanitized string
	  */
	static function sanitize($string, $delimiter='-') {

		$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
		$string = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $string);
		$string = strtolower(trim($string, '-'));
		$string = preg_replace("/[\/_|+ -]+/", $delimiter, $string);

		return $string;
	}
	
	/**
	  * Make a string singular
	  *
	  * @param	string	$string		
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function singular($string) {
		if (str::ends($string, 'ies')) {
			return substr($string, 0, $string-3) . 'y';
		} elseif (str::ends($string, 's')) {
			return substr($string, 0, $string-1);
		}
		return $string;
	}
	
	/**
	  * See if $haystack starts with $needle
	  * Todo: check if this is utf8 compatible
	  *
	  * @param	string	$haystack			The string to search in
	  * @param	string	$needle				The string to search for
	  * @param	string	$return_if_false	What to return if no match
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function starts($haystack, $needle, $return_if_false=false) {
		if ($needle == $haystack) return true;
		$length = strlen($needle);
		if (strtolower(substr($haystack, 0, $length)) == strtolower($needle)) return substr($haystack, $length);
		return $return_if_false;
	}

	/**
	  * Format a time
	  *
	  * @param	mixed	$timestamp	Either a string or a unix date
	  * @param	string	$format 	Optional format per php date
	  * @param	boolean	$relative 	If today, return time
	  * @return	string				The formatted date string
	  */
	static function time($timestamp, $format=false) {
		if (empty($timestamp)) return null;
		$timestamp = strtotime($timestamp);
		if (!$format) $format = config::get('time.format');
		return strftime($format, $timestamp);
	}

	/**
	  * Unescape a string
	  *
	  * @param	string	$string		The string
	  * @return	string				The string
	  */
	static function unescape($string) {
		if (!is_string($string)) return false;
		return str_replace("''", "'", stripslashes($string));
		return $string;
	}
}