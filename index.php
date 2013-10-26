<?php
//configurator in function to limit code footprint

init($config);
function init($config=false) {

	//turn on error reporting
	ini_set('display_errors', 1);

	//include other library files
	$directory = dirname(__file__) . '/';
	$libraries = array('a', 'config', 'db', 'error', 'file', 'html', 'http', 'str');
	foreach ($libraries as $library) require_once($directory . $library . '.php');

	//set custom error handlers
	set_error_handler(array('error', 'handle'));

	//set configuration in one of two ways: file or variable
	if (file_exists(config::get('config.file'))) require_once(config::get('config.file'));
	if ($config) foreach ($config as $key=>$value) config::set($key, $value);

	//have to set this per PHP
	date_default_timezone_set(config::get('timezone'));

	//convenience constants
	if (!defined('TAB'))		define('TAB', 		"\t");
	if (!defined('NEWLINE'))	define('NEWLINE',	"\n");
	if (!defined('BR'))			define('BR', 		'<br>');

}