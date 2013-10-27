<?php

//turn on error reporting
ini_set('display_errors', 1);

//include other library files
$directory = dirname(__file__) . '/';
$libraries = array('a', 'config', 'db', 'error', 'file', 'html', 'http', 'str');
foreach ($libraries as $library) require_once($directory . $library . '.php');

//set custom error handlers
set_error_handler(array('error', 'handle'));

//set configuration in two ways: $config variable and config file
if (!empty($config)) config::set($config);
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . config::get('config.file'))) require_once($_SERVER['DOCUMENT_ROOT'] . '/' . config::get('config.file'));

//have to set this per PHP
date_default_timezone_set(config::get('time.zone'));

//convenience constants
if (!defined('TAB'))		define('TAB', 		"\t");
if (!defined('NEWLINE'))	define('NEWLINE',	"\n");
if (!defined('BR'))			define('BR', 		'<br>');

//clean up
unset($directory, $libraries, $library, $config);