<?php
/**
 * 
 * STR
 * 
 * Methods for string manipulation
 * Todo: figure out if there's any way to use the PHP-reserved word "string" for these
 * 
 * @package Joshlib
 */

class str {

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
	  * @param	string	$haystack	The string to search in
	  * @param	string	$needle		The string to search for
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function ends($haystack, $needle) {
		if ($needle == $haystack) return true;
		$length = strlen($needle);
		if (strtolower(substr($haystack, (0 - $length))) == strtolower($needle)) return substr($haystack, 0, strlen($haystack) - $length);
		return false;
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
	  * @param	string	$haystack	The string to search in
	  * @param	string	$needle		The string to search for
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function sanitize($string, $replace_spaces_with='_') {
		$string = trim(str_replace(' ', $replace_spaces_with, $string));
		
		//make multi-spaces into singles
		$double = $replace_spaces_with . $replace_spaces_with;
		while (stristr($string, $double)) str_replace($double, $replace_spaces_with, $string);

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
	  * @param	string	$haystack	The string to search in
	  * @param	string	$needle		The string to search for
	  * @return	mixed				If match found, returns remainder of string.  Otherwise false
	  */
	static function starts($haystack, $needle) {
		if ($needle == $haystack) return true;
		$length = strlen($needle);
		if (strtolower(substr($haystack, 0, $length)) == strtolower($needle)) return substr($haystack, $length);
		return false;
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