<?php
/**
 * 
 * A
 * 
 * Methods for working with arrays
 * Todo: figure out if there's any way to use the PHP-reserved word "array" for these
 * 
 * @package Joshlib
 */

class a {

	/**
	  * Tell whether an array is associative
	  *
	  * @param	array	$array			The array to test
	  * @return	boolean					Whether the array is associative
	  */
	static function associative($array) {
		if (!is_array($array)) return false;
		return (bool) count(array_filter(array_keys($array), 'is_string'));
	}
	
	/**
	  * It's like explode, but it cleans up the array a little before returning it
	  *
	  * @param	mixed	$string			Normally this is a string, but it does check if it's an array
	  * @param	string	$separator		The string to separate on
	  * @param	boolean	$preserve_empty	Whether to preserve or strip empty array elements
	  * @return	array					The separated array
	  */
	static function separated($string, $separator=',', $preserve_empty=false) {
		if (is_array($string)) return $string;
		$return = array();
		$string = explode($separator, $string);
		foreach ($string as $part) {
			$part = trim($part);
			if (!empty($string) || $preserve_empty) $return[] = $part;
		}
		return $return;
	}

	/**
	  * Alias of html::dump() -- more Kirby-esque
	  * Returns an HTML formatted display of a variable
	  *
	  * @param	mixed	$var			Normally this is an array
	  * @return	string					The HTML content
	  */
	static function show($var) {
		return html::dump($var);
	}

}