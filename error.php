<?php
/**
 * 
 * ERROR
 * 
 * Methods for dealing with errors
 *
 * 
 * @package Foundation
 */

class error {

	/**
	  * Return an HTML-formatted error message for on-screen display or email.
	  * Uses no joshlib to avoid recursive errors.  Uses tables for layout because email
	  *
	  * @param	string	$type		The type of error, as determined by error::handle()
	  * @param	string	$message	The content of the error message
	  * @param	string	$file		The name of the file where the error originated
	  * @param	string	$line		The line number of the source of the error
	  * @return	string				HTML-formatted error message
	  */
	static function display($type, $message, $file=false, $line=false) {
	
		//message formatting todo replace link colors
		$color = config::get('error.color');
						
		if ($file && $line) {
			if (defined('DIRECTORY_ROOT'))		$file = str_replace(DIRECTORY_ROOT, '', $file);
			if (defined('DIRECTORY_JOSHLIB'))	$file = str_replace(DIRECTORY_JOSHLIB, '/joshlib/', $file);
			$message .= '<div style="border-top:1px dashed #999;padding-top:7px;position:absolute;bottom:5px;">' . $file . ':' . $line . '</div>';
		}

		//simple format for ajax requests
		if (http::ajax()) return 'Error ' . $type . ': ' . strip_tags($message);
		
		return html::start() . 
			html::head(html::title($type)) . 
			html::body_open(array('style'=>'height:100%;margin:0;')) . 
				html::style('html{height:100%;}') . 
				html::table(
					html::tr(
						html::td(
							html::div(array('style'=>'width:400px;overflow:auto'),
								html::div(array('style'=>'background-color:' . $color . ';color:#fff;height:36px;line-height:36px;font-size:22px;padding:4px 20px 2px 20px;float:left'), $type)
							) .
							html::div(array('style'=>'background-color:#fff;text-align:left;padding:20px 20px 20px 20px;width:360px;min-height:230px;position:relative'),
								$message
							)
						, array('align'=>'center'))
					),
					array('cellpadding'=>30,'cellspacing'=>0,'border'=>0,'style'=>'height:100%;width:100%;background-color:#ddd; font-family:helvetica,arial,sans-serif;font-size:15px;line-height:20px;color:#444;')
				) . 
			'</body></html>';
	}
	
	/**
	  * Handle all the errors
	  * One of the most critical functions in joshlib.  Registered by set_error_handler in index.php
	  * Todo: Determine if a plain text response would be better than an HTML one
	  *
	  * @param	int		$number		PHP error constant
	  * @param	string	$message	The content of the error message
	  * @param	string	$file		The name of the file where the error originated
	  * @param	string	$line		The line number of the source of the error
	  * @return	boolean				Returns true to prevent PHP native error handling
	  */
	static function handle($number, $message, $file=false, $line=false) {

		//without this, it reports even errors that are suppressed with an @
		$number = $number & error_reporting();
		if ($number == 0) return;
		
		//get appropriate title
		$title = 'Unknown PHP Error #' . $number;
		switch ($number) {
			case E_USER_ERROR:			$title = 'User Error';				break;
			case E_USER_WARNING:		$title = 'User Warning';			break;
			case E_USER_NOTICE:			$title = 'User Notice';				break;
			case E_USER_DEPRECATED:		$title = 'Deprecated';				break;
			case E_ERROR:				$title = 'Error';					break;
			case E_WARNING:				$title = 'Warning';					break;
			case E_PARSE:				$title = 'Parse Error';				break;
			case E_NOTICE:				$title = 'PHP Notice';				break;
			case E_CORE_ERROR:			$title = 'Core Error';				break;
			case E_CORE_WARNING:		$title = 'Core Warning';			break;
			case E_COMPILE_ERROR:		$title = 'Compile Error';			break;
			case E_COMPILE_WARNING:		$title = 'Compile Warning';			break;
			case E_USER_ERROR:			$title = 'User Error';				break;
			case E_USER_WARNING:		$title = 'User Warning';			break;
			case E_USER_NOTICE:			$title = 'User Notice';				break;
			case E_STRICT:				$title = 'Strict Notice';			break;
			case E_RECOVERABLE_ERROR:	$title = 'Recoverable Error';		break;
		}
		
		//make file / line adjustments
		if ($number == E_USER_DEPRECATED) {
			$backtrace	= debug_backtrace();
			$file		= $backtrace[2]['file'];
			$line		= $backtrace[2]['line'];
		} elseif (!$file && !$line) {
			$backtrace = array_reverse(debug_backtrace());
			foreach ($backtrace as $b) {
				if (isset($b['file']) && isset($b['line'])) {
					$file = $b['file'];
					$line = $b['line'];
					break;
				}
			}
		}
		
		//output error to screen if allowed (or if it's a joshlib startup error)
		if (!class_exists('config') || config::get('error.display')) {
			echo self::display($title, $message, $file, $line);
			exit;
		}

		//send email to address if specified, includes legacy email handling, todo move this to a legacy() function
		if (config::get('error.email')) {
			email(config::get('error.email'), self::display($title, $message, $file, $line), '[Joshlib] ' . $title);
		}
			
		//todo api push
		if (config::get('error.api')) {
		}

		//todo write to file
		if (config::get('error.log')) {
		}

		//don't execute PHP internal error handler, todo test if necessary
		return true;
	}
	
}