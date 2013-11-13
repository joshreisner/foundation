# Foundation

This is a dead-simple, lightweight yet full-featured framework for PHP.  The design emphasis is on consistency, flexibility and minimalism.  There are no third-party dependencies.  

### Why use Foundation?
If you like Laravel and Kirby Toolkit, but think that Laravel is too big and Kirby Toolkit is too small, then perhaps Foundation is just right for you.

### Setup
You can just add it to your project with a `require_once('../foundation/index.php')`.  Go ahead and keep everything in your website root right where it was.  You shouldn't need to make any changes to the Foundation folder.  It's recommended to put your Foundation folder below your document root, but not necessary.

However, if you want to get fancy, you can have your site look for Foundation dynamically.  This can be useful if you're dealing with several environments with different places for stuff.

```php
	<?php
	
	foundation();
	
	function foundation($config=false) {
		$count = substr_count($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'], '/');
		for ($i = 0; $i < $count; $i++) {
			$file = str_repeat('../', $i) . 'foundation/index.php';
			if (file_exists($file)) return include($file);
		}
		die('Could not find Foundation.');
	}
```

### Config
There are two basic ways of configuring Foundation.  The first, easiest, most common way is to create a file in your document root called config.php and add some config statements to it.  Foundation will automatically scan for a file at /config.php and include it if it's there.  (Just remember to exclude it from version control!)

```php
	<?php
	
	config::set('mail.from',		'info@example.com');
	config::set('time.zone', 		'America/Los_Angeles');
	
	if (config::host('joshs-dev-laptop')) {
		config::set('error.display', true);
		config::set('db.name',		'mynewproj');
	} else {
		config::set('db.host',		'live.database.example.com');
		config::set('db.name',		'LiveDB');
		config::set('db.user',		'LiveDBUser');
		config::set('db.password',	'Sup3rSm@rtP@$$w0rd!!');
	}
```
	
If you want to move this file, or if you're just simply opposed to configuration files, then you can also configure Foundation merely by having a $config variable present at the time it's initialized.  In the case of the setup function above, you could call it like this:

```php
	<?php
	
	foundation(array(
		'db.host'=>'live.database.example.com',
		'db.name'=>'LiveDB',
		'db.user'=>'LiveDBUser',
		'db.password'=>'Sup3rSm@rtP@$$w0rd!!',
	));
```

### Databases
Foundation comes with a database query builder with Laravel-esque syntax:

```php
	<?php
	$pages = db::table('pages')
		->where('active')
		->order_by('precedence')
		->join('sections', 'section_id = sections.id')
		->select('title', 'content', 'sections.title section');
		
	foreach ($pages as $page) {
		echo $page->title . ' (' . $page->section . ')';
	}
```

### HTML
Some HTML-builder functions, will have a form example here soon.

### Mail
A SwiftMailer-esque email builder.  Example coming soon.