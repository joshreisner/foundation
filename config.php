<?php
/**
 * 
 * CONFIG
 * 
 * Methods for getting and setting config values
 * Don't edit this file!  Use config::set() in your local site config file to override these values
 *
 * @package Joshlib
 */

class config {

	private static $defaults = array(
		'charset'			=> 'utf-8',
		'config.file'		=> 'config.php',
		'css.shortcuts'		=> array('bootstrap'=>'//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css'),
		'date.format'		=> '%b %d, %Y',
		'db.host'			=> 'localhost',
		'db.meta_table'		=> 'meta',
		'db.mysql_engine'	=> 'MyISAM',
		'db.password'		=> '',
		'db.user'			=> 'root',
		'error.api'			=> false,
		'error.color'		=> '#c55',
		'error.display'		=> false,
		'error.email'		=> false,
		'error.log'			=> false,
		'form.save'			=> 'Save Changes',
		'js.shortcuts'		=> array('jquery'=>'//code.jquery.com/jquery-latest.js', 'bootstrap'=>'//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js'),
		'language'			=> 'en',
		'mail.from'			=> false,
		'mail.to'			=> false,
		'session.user_id'	=> 'user_id',
		'time.format'		=> '%l:%M %p',
		'time.zone'			=> 'America/New_York',
		'viewport'			=> 'width=device-width, initial-scale=1.0',
	);
	
	/**
	  * Get a config variable
	  *
	  * @param	string	$key		The config variable's key
	  * @return	mixed				The value of the config variable
	  */
	static function get($key, $required=false) {
		if (!isset(self::$defaults[$key]) && $required) trigger_error('config::get() missing a required value for ' . html::strong($key) . '.  Please add this to your config file.');
		return @self::$defaults[$key];
	}
	
	/**
	  * Compare or return machine name.
	  * Note: Not sure why, but depending on network connection, the value may vary.  
	  * Eg: Joshs-Laptop.local, Joshs and joshs-laptop
	  *
	  * @param	string	$compare	If specified, return boolean if matches
	  * @return	mixed				Either boolean or the processed host name
	  */
	static function host($compare=false) {
		$host = strtolower(php_uname('n'));
		if ($pos = strpos($host, '.')) $host = substr($host, 0, $pos);
		return ($compare) ? ($compare == $host) : $host;
	}

	/**
	  * Set a config variable
	  *
	  * @param	string	$key		The config variable's key
	  * @param	string	$value		The config variable's value
	  */
	static function set($key, $value) {
		if (is_array($key)) {
			self::$defaults = array_merge($key, self::$defaults);
		} else {
			self::$defaults[$key] = $value;
		}
	}
	
}