<?php

class db {

	//system columns config
	private static $system_cols = array(
		'prepend'=>array(
			'id'		=>array('type'=>'int', 'auto'=>true),
		),
		'append'=>array(
			'active'	=>array('type'=>'tinyint', 'default'=>1, 'required'=>1),
			'published'	=>array('type'=>'tinyint', 'default'=>1, 'required'=>1),
		)
	);
	
	//caches & logs
	private static $connection	= false;
	private static $schema		= false;
	private static $queries		= array();

	//query builder vars, reset with every call to table()
	private static $table 		= false;
	private static $wheres		= array();
	private static $joins		= array();
	private static $order_by	= array();

	/**
	  * Make an associative array from resultset
	  * Takes the first object var of a resultset and makes it the associative key
	  * Make sure your first element is unique, or you will get interesting results!
	  * 
	  */
	static function associate() {
		$selects = a::arguments(func_get_args());
		if (!$result = self::select($selects)) return $result;
		$return = array();
		$keys 	= array_keys(get_object_vars($result[0])); //eg 'id', 'title', 'description', 'active'
		$key 	= array_shift($keys); //eg 'id' or 'title'
		$count 	= count($keys); //remaining keys
		foreach ($result as $row) {
			if ($count == 1) {
				//if there's only one element, return simple scalar value eg [$id]=>$name
				$return[$row->{$key}] = $row->{$keys[0]};
			} else {
				$return[$row->{$key}] = $row;
			}
		}
		return $return;
	}

	/**
	  * Connect to the database
	  * Sets the local cache variable $connection to a database resource handle or throws and error
	  * 
	  */
	static function connect() {
		if (is_object(self::$connection)) return;
		
		try {
			self::$connection = new PDO(
				'mysql:host=' . config::get('db.host') . ';dbname=' . config::get('db.name', true) . ';',
				config::get('db.user'), 
				config::get('db.password'), 
				array(
					PDO::ATTR_ERRMODE				=>PDO::ERRMODE_SILENT,
					PDO::MYSQL_ATTR_INIT_COMMAND	=>'SET NAMES utf8',
					PDO::ATTR_PERSISTENT			=>true,
				)
			);			
		} catch (PDOException $e) {
		}
		if (!is_object(self::$connection)) {
			trigger_error('db::connect() failed to connect to the database. ' . html::code($e->getMessage()));
			exit;
		}
	}

	/**
	  * Show all the queries that have been run so far
	  *
	  * @return	string				Returns HTML formatted table of SQL queries
	  */
	static function debug() {
		return html::dump(self::$queries);
	}
	
	/**
	  * Specify which columns to select using query builder
	  *
	  * @param	string	$selects	Any amount of column names
	  * @return	mixed				Returns an object-resultset or null
	  */
	function first() {
		$selects = a::arguments(func_get_args());
		$result = self::select($selects);
		if (count($result)) return array_shift($result);
		return $result;
	}

	/**
	  * Add a join
	  *
	  * @param	string			The name of the table to join
	  * @param	string			The connection statement, eg table1.id = table2.table1_id
	  * @return	object				Passing the object up the chain
	  */
	function join($table, $connection) {
		self::$joins[] = 'JOIN ' . $table . ' ON ' . $connection;
		return $this;
	}
	
	/**
	  * Specify an ORDER BY param for your selects in the query builder
	  *
	  * @param	string	$columns	Comma-separated list of columns or array
	  * @return	object				Passing the object up the chain
	  */
	public function order_by($columns) {
		if (empty(self::$table)) trigger_error('db::order_by() must come after a db::table() statement');
		$columns = a::separated($columns);
		self::$order_by = $columns;
		return $this;
	}

	/**
	  * Run a raw query.  Private for security
	  *
	  * @param	string	$sql		Raw SQL to be executed
	  * @return	array				An array of results
	  */
	private static function query($sql) {
		self::connect();
		if ($result = self::$connection->query($sql, PDO::FETCH_OBJ)) {
			//successful query
			self::$queries[] = $sql;
			return $result->fetchAll();
		}
		$error = self::$connection->errorInfo();
		trigger_error($error[2] . html::pre($sql));
	}
	
	/**
	  * Specify which columns to select using query builder
	  *
	  * @param	string	$selects	Any amount of column names
	  */
	public static function select() {
		$selects = a::arguments(func_get_args());
		
		//build query
		foreach ($selects as &$select) $select = TAB . $select;
		$sql = 'SELECT ' . NEWLINE . implode(',' . NEWLINE, $selects) . NEWLINE . 'FROM ' . self::$table;
		if (!empty(self::$joins)) $sql .= NEWLINE . implode(NEWLINE, self::$joins);
		if (!empty(self::$wheres)) $sql .= NEWLINE . 'WHERE' . NEWLINE . TAB . implode(' AND ' . NEWLINE . TAB, self::$wheres);
		if (!empty(self::$order_by)) $sql .= NEWLINE . 'ORDER BY ' . NEWLINE . TAB . implode(', ' . NEWLINE . TAB, self::$order_by);
		
		return self::query($sql);
	}
	
	/**
	  * Start a new query builder by specifying the table
	  *
	  * @param	string	$table		The table in question
	  * @return	object				A query builder object to attach stuff to
	  */
    public static function table($table) {
		self::$wheres = self::$joins  = self::$order_by = array(); //clear out old queries
		self::$table = $table;
		return new self;
    }

	/**
	  * Run a SQL update
	  *
	  * @param	array	$fields		Associative array of fields to update
	  * @return	int					Number of records affected
	  */
    public static function update($updates) {
		if (empty(self::$table)) trigger_error('db::update() must come after a db::table() statement');
		$fields = array();
    	foreach ($updates as $field=>$value) {
    		if (is_numeric($value) || $value == 'NOW()' || $value == 'NULL') {
    			$fields[] = $field . ' = ' . $value;
    		} else {
    			$fields[] = $field . ' = \'' . str::escape($value) . '\'';
    		}
    	}
    	$sql = 'UPDATE ' . self::$table . ' SET ' . implode(',', $fields);
		if (!empty(self::$wheres)) $sql .= NEWLINE . 'WHERE' . NEWLINE . TAB . implode(' AND ' . NEWLINE . TAB, self::$wheres);
		if (self::query($sql)) return self::$connection->rowCount();
    }

	/**
	  * Specify a where clause on the query builder
	  *
	  * @param	string	$column		The column to compare
	  * @param	string	$operator	The operator to use
	  * @param	int		$value		The value to compare
	  * @return	object				Pass the query builder object up the chain
	  */
	public function where($column, $value=1, $operator='=') {
		if (!strstr($column, '.')) $column = self::$table . '.' . $column;
		if (is_string($value)) $value = '\'' . str::escape($value) . '\'';
		self::$wheres[] = $column . ' ' . $operator . ' ' . $value;
		return $this;
	}

}