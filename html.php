<?php
/**
 * 
 * HTML
 * 
 * Methods for generating HTML.
 *
 * One of the key params you'll see over and over in this class is the $arguments class.  This can be either a string 
 * or an associative array.  If an array, keys are arguments such as class=>foo, id=>bar, width=>12.  If a string, it's
 * assumed to be a class unless prepended with a #, in which case it's an id.  
 * html::div('#foo') becomes <div id="foo"></div>
 * html::div('bar') becomes <div class="bar"></div>
 * html::div(array('id'=>'foo', 'class'=>'bar)) becomes <div id="foo" class="bar"></div>
 *
 * Order of arguments is a tricky thing, and consistency is balanced against utility.  "Generic containers" are available for
 * all the tags in the $generic_containers array, and are in the format html::h1($content, $arguments) 
 * A notable exception are <div>s, which are reversed so they can be used in constructions where it's helpful to have the arguments
 * at the top, near the tag name eg:
 * html::div('classname',
 * 		html::h1('Here is a content title') . 
 * 		html::p('Here is an intro paragraph', 'intro') . 
 *		html::p('Here is another paragraph')
 * );
 * 
 * @package Foundation
 */

class html {

	private static $generic_containers = array(
		'article', 'aside', 'code', 'em', 'fieldset', 'fig', 'figcaption', 'footer', 
		'h1', 'h2', 'h3', 'h4', 'h5', 'header', 'label', 'li', 
		'option', 'p', 'pre', 'section', 'select', 'small', 'span', 'strong', 
		'tbody', 'td', 'textarea', 'th', 'thead', 'tr');
	
	/**
	  * Generic container catch-all function
	  *
	  * @param	string	$tag		Not called directly, this is the span in self::span()
	  * @param	mixed	$arguments	Any arguments supplied to the container, usually self::p($content, $arguments)
	  * @return	string				The formed container, eg <footer>Some content</footer>
	  */
	static function __callStatic($tag, $arguments) {
		if (!in_array($tag, self::$generic_containers)) trigger_error('html tag ' . $tag . ' was called but not found.');
		if (count($arguments) > 1) {
			$content = $arguments[0];
			$arguments = $arguments[1];
		} elseif (count($arguments) == 1) {
			$content = (is_array($arguments)) ? $arguments[0] : $arguments;
			$arguments = false;
		} else {
			$content = '';
		}
		return self::tag($tag, $arguments, $content);
	}

	/**
	  * Special <a> container
	  * Todo: add auto-email-obscuring
	  *
	  * @param	string	$href		The URL link target
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	string	$content	The content contained inside the link
	  * @return	string				The HTML anchor link
	  */
	static function a($href=false, $content='', $arguments=false) {
		$arguments = self::arguments($arguments);
		if (empty($href)) {
			$arguments['class'] = (empty($arguments['class'])) ? 'empty' : $arguments['class'] . ' empty';
		} else {
			$arguments['href'] = $href;
		}
		if ($email = str::starts($href, 'mailto:')) {
			$encoded = str::encode($email);
			$href = 'mailto:' . $encoded;
			$content = str_replace($email, $encoded, $content);
		}
		return self::tag('a', $arguments, $content);
	}

	/**
	  * Helper function to parse a mixed $arguments variable into an array of tag arguments
	  *
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @return	array	The array of tag arguments	
	  */
	private static function arguments($arguments=false) {
		//convert arguments to array
		if (empty($arguments)) return array();

		//arguments can be string for shorthand, class or prepend with # for id
		if (is_string($arguments)) {
			if ($id = str::starts($arguments, '#')) {
				$arguments = array('id'=>$id);
			} else {
				$arguments = array('class'=>$arguments);
			}
		}
		
		//clean up classes
		if (!empty($arguments['class']) && stristr($arguments['class'], ' ')) {
			$arguments['class'] = implode(' ', array_values(array_filter(array_unique(explode(' ', $arguments['class'])))));
		}
		
		return $arguments;
	}
	
	/**
	  * Make an open <body> tag and add the requested folders, so /example/folder will yield <body class="example folder">
	  *
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @return	string	The open <body> tag
	  */
	static function body_open($arguments=false) {
	
		//add folders to body class
		$arguments = self::arguments($arguments);
		if (!isset($arguments['class'])) {
			$arguments['class'] = '';
		
			if ($folders = http::request('folders')) {
				foreach ($folders as $folder) $arguments['class'] .= ' ' . $folder;
			} else {
				$arguments['class']	.= ' home';
			}
		}
				
		return self::tag('body', $arguments, false, true);
	}
	
	/**
	  * Link to a CSS file
	  * Todo: add filemtime to help browser caching
	  *
	  * @param	string	$href		the URL of the CSS file
	  * @return	string	<link rel="stylesheet" href="/example/stylesheet.css">
	  */
	static function css($href) {
		if (is_array($href)) {
			$return = '';
			foreach ($href as $h) $return .= self::css($h);
			return $return;
		} else {
			//see if shortcut exists
			$shortcuts = config::get('css.shortcuts');
			if (array_key_exists($href, $shortcuts)) $href = $shortcuts[$href];
			return self::tag('link', array('rel'=>'stylesheet', 'href'=>$href));
		}
	}
	
	/**
	  * Special <dl> constructor
	  *
	  * @param	array	$elements	Associative array of Key=>Content elements, will be wrapped in <dd> and <dt> tags
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @return	string	The contructed <dl>
	  */
	static function dl($elements=false, $arguments=false) {
		$content = '';
		foreach ($elements as $key=>$value) $content .= self::tag('dt', false, $key) . self::tag('dd', false, $value);
		return self::tag('dl', $arguments, $content);
	}
	
	/**
	  * Special <div> container.  Arguments are reversed, see description at top for why.  The intent is not to confuse.
	  *
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	string	$content	The content contained inside the div
	  * @return	string	<div class="example">Some content</div>
	  */
	static function div($arguments=false, $content='') {
		return self::tag('div', $arguments, $content);
	}
	
	/**
	  * Special <div> container, open version.  Useful in includes.  As with DIVs above, arguments are reversed.
	  *
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	string	$content	The content contained inside the div
	  * @return	string	<div class="example">Some content
	  */
	static function div_open($arguments=false, $content='') {
		return self::tag('div', $arguments, $content, true);
	}

	/**
	  * Returns an HTML formatted display of a variable
	  *
	  * @param	mixed	$var			Any type of variable
	  * @return	string					The HTML content
	  */
	static function dump($var) {
		if (is_null($var)) {
			return self::em('null');
		} elseif (is_scalar($var)) {
			if (is_string($var)) {
				return $var;
			} elseif (is_numeric($var)) {
				return $var;
			} elseif (is_bool($var)) {
				return ($var) ? 'true' : 'false';
			} else {
				return 'scalar but non string / numeric / bool';
			}
		} else {
			if (is_array($var)) {
				if (a::associative($var)) {
					$return = '<table cellpadding="5" border="1" style="margin:20px;font:12px helvetica;">';
					foreach ($var as $key=>$value) {
						$return .= '<tr><td style="background-color:#eee;font-weight:bold;">' . $key . '</td><td>' . self::dump($value) . '</td></tr>';
					}
					return $return . '</table>';
				} else {
					foreach ($var as &$value) $value = self::dump($value);
					return self::ol($var, array('start'=>0));
				}
			} elseif (is_object($var)) {
				return 'object' . self::dump(a::object($var));
			} elseif (is_resource($var)) {
				return 'resource';
			} else {
				return 'non-scalar but not array, object or resource';
			}
		}
	}

	/**
	  * Favicon
	  *
	  * @param	string	$href		URL
	  * @return	string				The favicon tag
	  */
	static function favicon($href='/assets/img/triangle.png') {
		return self::tag('link', array('rel'=>'shortcut icon', 'href'=>$href));
	}

	/**
	  * Special <form> container tag.  
	  *
	  * @param	mixed	$content	Content may be an array
	  * @param	mixed	$values		Associative array or resultset object with key=>value values
	  * @return	string				The <FORM> tag
	  */
	static function form($content='', $values=false, $arguments=false) {
		$arguments = self::arguments($arguments);

		//set defaults
		if (empty($arguments['action']))	$arguments['action']	= http::request();
		if (empty($arguments['enctype']))	$arguments['enctype']	= 'multipart/form-data';
		if (empty($arguments['method']))	$arguments['method']	= 'POST';
		if (empty($arguments['role']))		$arguments['role']		= 'form';

		if (is_array($content)) {
			//no empty forms
			if (!count($content)) return null;

			//populate values
			if ($values) {
				if (is_object($values)) $values = a::object($values);
				foreach ($values as $key=>$value) {
					if (isset($content[$key]) && empty($content[$key]['value'])) {
						$content[$key]['value'] = $value;
					}
				}
			}

			//parse fields
			$fields = array();
			foreach ($content as $name=>$field) {
				//default field keys
				if (empty($field['value']))		$field['value'] = false;
				if (empty($field['options']))	$field['options'] = array();
				if (empty($field['required']))	$field['required'] = false;
				$field['class'] = (empty($field['class'])) ? 'form-control' : 'form-control ' . $field['class'];
				$return = '';

				if ($field['type'] == 'checkbox') {
					//bootrap packages single checkbox fields differently
					$checked = ($field['value']) ? 'checked' : false;
					$return = self::div('col-lg-offset-2 col-lg-10', 
						self::div('checkbox',
							self::label(
								self::input('checkbox', $name, array('checked'=>$checked)) . 
								$field['label']
							)
						)
					);
				} else {
					switch ($field['type']) {
						case 'checkboxes':
						if (!$field['value']) $field['value'] = array();
						foreach ($field['options'] as $key=>$option) {
							$checked = in_array($key, $field['value']) ? 'checked' : false;
							$return .= self::div('checkbox', self::label(
								html::input('checkbox', $name, array('value'=>$key, 'checked'=>$checked))
							    . $option
							));
						}
						break;

						case 'date':
						$field_args = array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>'mm/dd/yyyy');
						$return = self::input('date', $name, $field_args);
						break;

						case 'datetime':
						$field_args = array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>'mm/dd/yyyy');
						$return = self::input('datetime', $name, $field_args);
						break;

						case 'email':
						$field_args = array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>strip_tags($field['label']));
						if (!empty($field['autocomplete'])) $field_args['autocomplete'] = $field['autocomplete'];
						$return = self::input('email', $name, $field_args);
						break;

						case 'file':
						$return = self::input('file', $name);
						break;

						case 'password':
						$field_args = array('class'=>$field['class'], 'placeholder'=>$field['label']);
						if (!empty($field['autocomplete'])) $field_args['autocomplete'] = $field['autocomplete'];
						$return = self::input('password', $name, $field_args);
						break;

						case 'radio':
						foreach ($field['options'] as $key=>$value) {
							$checked = ($key == $field['value']) ? 'checked' : false;
							$return .= self::div('radio', self::label(
								self::input('radio', $name, array('value'=>$key, 'checked'=>$checked)) . $value
							));
						}
						break;

						case 'select':
						if (!$field['required']) $return .= self::option('', array('selected'=>(($field['value'] == false) ? 'selected' : '')));
						foreach ($field['options'] as $key=>$value) {
							$selected = ($key == $field['value']) ? 'selected' : false;
							$return .= self::option($value, array('value'=>$key, 'selected'=>$selected));
						}
						$return = self::select($return, array('name'=>$name, 'class'=>$field['class']));
						break;

						case 'text':
						$return = self::input('text', $name, array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>strip_tags($field['label'])));
						break;

						case 'textarea':
						$return = self::textarea($field['value'], array('name'=>$name, 'class'=>$field['class'], 'placeholder'=>strip_tags($field['label'])));
						break;

						case 'time':
						$return = self::input('time', $name, array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>'--:-- --'));
						break;

						case 'url':
						case 'url-local':
						$return = self::input('text', $name, array('value'=>$field['value'], 'class'=>$field['class'], 'placeholder'=>strip_tags($field['label'])));
						break;

						default:
						trigger_error('self::form doesn\'t yet support ' . $field['type']);
					}

					$return = self::label($field['label'], array('for'=>$name, 'class'=>'col-lg-2 control-label')) . 
						self::div('col-lg-10', $return);

				}
				
				$fields[] = self::div('form-group', $return);
			}

			$content = implode($fields);

			$content .= self::div('form-group', 
				self::div('col-lg-offset-2 col-lg-10', 
					self::input('submit', false, array('class'=>'btn btn-primary', 'value'=>config::get('form.save')))
				)
			);
		}

		return self::tag('form', $arguments, $content);
	}
	
	/**
	  * Special <head> container tag.  Prepends meta charset
	  *
	  * @param	string	$content	The content contained inside the h1
	  * @return	string				The <HEAD> tag
	  */
	static function head($content='') {
		$content = self::meta('charset') . $content; //auto-prepend charset because it's always needed
		return self::tag('head', false, $content);
	}

	/**
	  * Make an icon; currently uses Bootstrap 3's Glyphicons
	  *
	  * @param	string	$icon	The icon keyword
	  * @return	string			The icon <I> tag
	  */
	static function icon($icon) {
		return self::tag('i', 'glyphicon glyphicon-' . $icon);
	}

	/**
	  * Special <img> function.  Tries to get height & width if not specified
	  *
	  * @param	string	$filename	The filename of the image
	  * @return	string				The IMG tag
	  */
	static function img($filename, $arguments=false) {
		$arguments = self::arguments($arguments);
		
	}

	/**
	  * Make an input
	  *
	  * @param	string	$type	Input type = text / password / date / time / etc
	  * @param	string	$name	The name / id of the input
	  * @return	string			The icon <I> tag
	  */
	static function input($type, $name, $arguments=false) {
		$arguments = self::arguments($arguments);
		$arguments['type'] = $type;
		if ($type == 'checkbox') {
			$arguments['name'] = $name . '[]';
		} elseif ($type == 'radio') {
			$arguments['name'] = $name;
		} else {
			$arguments['name'] = $arguments['id'] = $name;
		}
		return self::tag('input', $arguments);
	}
	
	/**
	  * Link to a javascript file
	  * Todo: add filemtime (on local files) to help with browser caching
	  *
	  * @param	mixed	$src	The URL of the JS file, or an array of several
	  * @return	string	<script src="/example/javascript.js"></script>
	  */
	static function js($src=false) {
		if (is_array($src)) {
			$return = '';
			foreach ($src as $s) $return .= self::js($s);
			return $return;
		} else {
			//see if shortcut exists
			$shortcuts = config::get('js.shortcuts');
			if (array_key_exists($src, $shortcuts)) $src = $shortcuts[$src];
			return self::tag('script', array('src'=>$src));
		}
	}
	
	/**
	  * Special <meta> tag.
	  *
	  * @param	string	$key		accepts charset, description, keywords, viewport
	  * @param	string	$value		The value for the meta tag.  Does not apply to charset, to change the charset, do it with config::set()
	  * @return	string	<li>Some content</li>
	  */
	static function meta($key, $value=false) {
		if ($value !== false) $value = strip_tags($value); //can't have tags inside a meta tag
		switch ($key) {
			case 'charset';
			return self::tag('meta', array('charset'=>config::get('charset')));

			case 'description';
			if ($value === false) return '';
			return self::tag('meta', array('name'=>'description', 'content'=>$value));

			case 'keywords';
			if ($value === false) return '';
			return self::tag('meta', array('name'=>'keywords', 'content'=>$value));

			case 'viewport':
			if ($value !== false) config::set('viewport', $value); //save new viewport
			return self::tag('meta', array('name'=>'viewport', 'content'=>config::get('viewport')));
		}
		return false; //$key was not supported
	}
	
	/**
	  * Special <nav> container.  
	  *
	  * @param	array	$elements	Associative array of URL=>Content nav elements, will be wrapped in <a> tags
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	string	$separator	Optional string to sandwich between the <a>s
	  * @param	array	$classes	One-dimensional array of class names for each <a>
	  * @return	string				HTML <nav> with <a> child elements
	  */
	static function nav($elements=false, $arguments=false, $separator='', $classes=false) {
		$anchors = array();
		if ($elements) {
			foreach ($elements as $key=>$value) {
				$class = ($key == http::request('path_query')) ? 'active' : '';
				$anchors[] = self::a($key, $value, @array_shift($classes) . $class);
			}
		}
		return self::tag('nav', $arguments, implode($separator, $anchors));
	}
	
	/**
	  * Special navigation HTML.  Creates a set of nested ULs with links.  Adds an active class on links to the current page.
	  *
	  * @param	$array	$pages		Associative array, $pages should have keys for title and URL, optionally children with another array
	  * @param	int		$depth		Don't use this.  It's a recursive function and needs to know when it gets back to the top.
	  * @return	string				Nested ULs, LIs and As
	  */
	static function navigation($pages, $depth=1) {
		$active = false;
		$elements = $classes = array();
		foreach ($pages as $page) {
			$class = '';
			
			//get selected
			if (http::request('path_query') == $page['url']) {
				$class = 'active';
				$active = true;
			}
			
			$return = self::a($page['url'], $page['title']);
			
			if (isset($page['children']) && count($page['children'])) {
				list($content, $descendant_active) = self::navigation($page['children'], $depth + 1);
				$return .= $content;
				if ($descendant_active) {
					$class = 'descendant-active';
					$active = true;
				}
			}
			
			$classes[] = $class;
			$elements[] = $return;
		}
			
		$return = self::ul($elements, false, $classes);
		if ($depth == 1) return $return;
		return array($return, $active); //have to pass the fact that there was a selected item up the chain
	}
	
	/**
	  * Special <ol> container.  
	  *
	  * @param	array	$elements	Each of these will be wrapped in a LI
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	array	$classes	One dimensional array of class names for each LI
	  * @return	string				HTML ol with li child elements
	  */
	static function ol($elements=false, $arguments=false, $classes=false) {
		if ($elements) {
			foreach ($elements as &$element) {
				$element = self::li($element, @array_shift($classes));
			}
		}
		return self::tag('ol', $arguments, implode($elements));
	}
	
	/**
	  *	Special HTML block to take care of charset headers, doctype and opening HTML tag
	  *
	  * @param	bool	$modernizr	Whether to use the modernizr construction
	  * @param	string	$manifest	The URL for an offline manifest
	  * @return	string				Block of HTML
	  */
	static function start($modernizr=false, $manifest=false) {
	
		//send headers
		if (!headers_sent()) header('Content-Type: text/html; charset=' . config::get('charset'));

		$lang = config::get('language');
		
		$return = '<!DOCTYPE html>';
		
		if ($modernizr) {
			$return .= '
			<!--[if IEMobile 7 ]>' . self::tag('html', array('class'=>'no-js ie iem7', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<![endif]-->
			<!--[if lt IE 7 ]>' . self::tag('html', array('class'=>'no-js ie ie6', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<![endif]-->
			<!--[if IE 7 ]>' . self::tag('html', array('class'=>'no-js ie ie7', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<![endif]-->
			<!--[if IE 8 ]>' . self::tag('html', array('class'=>'no-js ie ie8', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<![endif]-->
			<!--[if IE 9 ]>' . self::tag('html', array('class'=>'no-js ie iem9', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<![endif]-->
			<!--[if (gt IE 9)|(gt IEMobile 7)|!(IEMobile)|!(IE)]><!-->' . self::tag('html', array('class'=>'no-js', 'lang'=>$lang, 'manifest'=>$manifest), false, true) . '<!--<![endif]-->
			';
		} else {
			$return .= self::tag('html', array('lang'=>$lang, 'manifest'=>$manifest), false, true);
		}
		
		return $return;
	}

	/**
	  * Special <STYLE> tag
	  *
	  */
	static function style($css) {
		return self::tag('style', array('type'=>'text/css'), $css);
	}
	
	/**
	  * Special <TABLE> tag -- content can be an array of associative arrays
	  *
	  */
	static function table($content, $arguments=false) {
		$last_group = '';

		if (is_array($content)) {
			//no empty tables
			if (!count($content)) return null;

			//build header & cache slugs
			$header = $columns = array();
			$keys = array_keys($content[0]);
			foreach ($keys as $key) {
				if (!str::starts($key, '_')) {
					$columns[$key] = str::sanitize($key);
					$header[] = self::th($key, $columns[$key]);
				}
			}
			$colspan = count($header);

			//format rows
			foreach ($content as &$row) {
				$cells = array();
				foreach ($columns as $key=>$slug) $cells[] = self::td($row[$key], $slug);
				
				$row_args = array();
				if (!empty($row['_class'])) $row_args['class']	= $row['_class'];
				if (!empty($row['_id']))	$row_args['id'] 	= $row['_id'];
				if (!empty($row['_group']) && ($row['_group'] != $last_group)) {
					$last_group = $row['_group'];
					$row['_group'] = self::tr(self::td($row['_group'], array('colspan'=>$colspan)), 'group');
				} else {
					$row['_group'] = '';
				}

				$row = $row['_group'] . self::tr(implode($cells), $row_args);
			}

			//assemble output
			$content = self::thead(self::tr(implode($header))) . self::tbody(implode($content));
		}

		return self::tag('table', $arguments, $content);
	}
	
	/**
	  * Helper function to draw tags.
	  *
	  * @param	string	$tag		The name of the tag, eg p, ul, etc.
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	string	$content	The content contained inside the tag
	  * @param	boolean	$open		Whether the tag should be left open or not
	  * @return	string				The HTML tag
	  */
	private static function tag($tag, $arguments=false, $content='', $open=false) {
		$tag = strtolower(trim($tag));
		
		//start tag
		$return = '<' . $tag;

		//format arguments
		$arguments = self::arguments($arguments);
		foreach ($arguments as $key=>$value) {
			if ($value !== false) $return .= ' ' . strtolower(trim($key)) . '="' . htmlentities(trim($value)) . '"';
		}
		
		//close tag
		$return .= '>';
		if (!in_array($tag, array('br', 'hr', 'img', 'meta'))) {
			//is a container tag
			$return .= $content;
			if ($open === false) $return .= '</' . $tag . '>';
		}
		
		return $return;
	}

	/**
	  * Special <title> container.  Accepts no $arguments, and strips any tags out of the $content
	  *
	  * @param	string	$content	The content contained inside the title
	  * @return	string	<title>Example Page</title>
	  */
	static function title($content='') {
		return self::tag('title', false, strip_tags($content));
	}
	
	/**
	  * Special <ul> container.  
	  *
	  * @param	array	$elements	Each of these will be wrapped in a LI
	  * @param	mixed	$arguments	String for class, string prepended with # for id, or array for multiple arguments
	  * @param	array	$classes	One dimensional array of class names for each LI
	  * @return	string				HTML ul with li child elements
	  */
	static function ul($elements=false, $arguments=false, $classes=false) {
		if ($elements) {
			foreach ($elements as &$element) {
				$element = self::li($element, @array_shift($classes));
			}
		}
		return self::tag('ul', $arguments, implode($elements));
	}
}