<?php

class db {

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
	  * Get the charset
	  * MySQL needs a slightly different format than HTML
	  * Don't expect I will try it with charsets other than utf-8
	  * 
	  */
	private static function charset() {
		return str_replace('-', '', config::get('charset'));
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
					PDO::MYSQL_ATTR_INIT_COMMAND	=>'SET NAMES ' . self::charset(),
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
	  * Drop a table from the query builder
	  *
	  */
	public function delete() {
		if (empty(self::$table)) trigger_error('db::delete() must come after a db::table() statement');
		$sql = 'DELETE FROM ' . self::$table;
		if (!empty(self::$wheres)) $sql .= NEWLINE . 'WHERE' . NEWLINE . TAB . implode(' AND ' . NEWLINE . TAB, self::$wheres);
		self::query($sql);
	}

	/**
	  * Drop a table from the query builder
	  *
	  */
	public function drop() {
		if (empty(self::$table)) trigger_error('db::drop() must come after a db::table() statement');
		self::query('DROP TABLE IF EXISTS ' . self::$table);
		self::$table = false;
	}

	/**
	  * Escape strings where necessary
	  *
	  * @return	string				Returns escaped string
	  */
	static function escape($value) {
		if (is_numeric($value) || $value == 'NULL' || (strstr($value, '(') && strstr($value, ')'))) {
			return $value;
		}
		return '\'' . str::escape($value) . '\'';
	}
	
	/**
	  * Format field name for non-ambiguity
	  *
	  * @return	string				Returns escaped string
	  */
	static function field($field) {
		if (!strstr($field, '.')) $field = self::$table . '.' . $field;
		return $field;
	}

	/**
	  * Tell whether a given $field in a $table exists or not
	  * @param	string		$table	Table name to check
	  * @param	string		$field	Field name to check
	  * @return	bool				True or false if exists
	  *
	  */
	public static function field_exists($table, $field) {
		if (!self::table_exists($table)) return false;
		return in_array($field, self::fields($table));
	}

	/**
	  * Tell whether a given $field can be set to NULL
	  * @param	string		$table	Table name to check
	  * @param	string		$field	Field name to check
	  * @return	bool				True or false if nullable
	  *
	  */
	public static function field_nullable($table, $field) {
		if (!self::field_exists($table, $field)) trigger_error('db::field_nullable looking for a non-existent field (' . $field . ') in table ' . $table . '.');
		return self::$schema[$table][$field]['null'];
	}

	/**
	  * Rename a field
	  * @param	string		$table	Table name to check
	  * @return	bool				True or false if exists
	  *
	  */
	public static function field_rename($table, $old_field, $new_field) {
		if (!self::field_exists($table, $old_field)) trigger_error('old field doesn\'t exist');
		if  (self::field_exists($table, $new_field)) trigger_error('new field already exists');
		self::$schema[$table] = array(); //force a schema update
		trigger_error('this function not finished yet');
		return self::query('ALTER  ' . $table . ' CHANGE ' . $old_field . ' ' . $new_field . ' ' . self::field_type($old_field));
	}

	/**
	  * Return SQL type for given field
	  * @param	string		$table	Table name to check
	  * @param	string		$table	Field name to check
	  * @return	bool				True or false if exists
	  *
	  */
	public static function field_type($table, $field) {
		trigger_error('this function not finished yet');
	}

	/**
	  * Return a list of fields for a $table
	  * @param	string		$table	Table name to check
	  * @return	bool				True or false if exists
	  *
	  */
	public static function fields($table) {

		//force cache to exist and try to use cache
		if (!self::table_exists($table)) trigger_error('trying to get fields on non-existent table.');
		if (!empty(self::$schema[$table])) return array_keys(self::$schema[$table]);

		//otherwise get fields
		$result = self::query('SHOW COLUMNS FROM ' . $table);
		foreach ($result as $field) {
			self::$schema[$table][$field->Field] = array(
				'type'=>$field->Type, //todo parse parens to new length element, eg int(11)
				'null'=>($field->Null == 'YES') ? true : false,
				'key'=>$field->Key,
				'default'=>$field->Default,
			);
		}
		return array_keys(self::$schema[$table]);
	}

	/**
	  * Find (where id = ) shortcut
	  *
	  * @param	int		$id	ID
	  * @return	object			Passing the object up the chain
	  */
	function find($id) {
		if (empty(self::$table)) trigger_error('db::find() must come after a db::table() statement');

		self::where('id', $id);
		return $this;
	}

	/**
	  * Specify which fields to select using query builder
	  *
	  * @param	string	$fields	Any number of field names
	  * @return	mixed				Returns an object-resultset or null
	  */
	function first() {
		$fields = a::arguments(func_get_args());
		$result = self::select($fields);
		if (!count($result)) return false;
		return array_shift($result);
	}

	/**
	  * SQL Insert
	  *
	  * @param	string	$selects	Any amount of field names
	  * @return	mixed				Returns an object-resultset or null
	  */
	function insert($inserts) {
		if (empty(self::$table)) trigger_error('db::insert() must come after a db::table() statement');
		$fields = $values = array();
    	foreach ($inserts as $field=>$value) {
    		//if (self::field_exists(self::$table))
	   		if (empty($value) && self::field_nullable(self::$table, $field)) $value = 'NULL';
    		$fields[] = NEWLINE . TAB . $field;
    		$values[] = NEWLINE . TAB . self::escape($value);
    	}
    	$sql = 'INSERT INTO ' . self::$table . ' (' . implode(',', $fields) . NEWLINE . ') VALUES (' . implode(',', $values) . NEWLINE . ')';
		if (self::query($sql)) return self::$connection->lastInsertId();
	}

	/**
	  * Add a join
	  *
	  * @param	string			The name of the table to join
	  * @param	string			The connection statement, eg table1.id = table2.table1_id
	  * @return	object			Passing the object up the chain
	  */
	function join($table, $connection) {
		self::$joins[] = 'JOIN ' . $table . ' ON ' . $connection;
		return $this;
	}
	
	/**
	  * Add a left join
	  *
	  * @param	string			The name of the table to join
	  * @param	string			The connection statement, eg table1.id = table2.table1_id
	  * @return	object			Passing the object up the chain
	  */
	function left_join($table, $connection) {
		self::$joins[] = 'LEFT JOIN ' . $table . ' ON ' . $connection;
		return $this;
	}
	
	/**
	  * Sometimes it's useful to have a simple one-dimensional array of a single column
	  * @param	string		$field	Field name to get
	  * @return	array				One-dimensional array
	  * 
	  */
	static function lists($field) {
		if (!$result = self::select($field)) return $result;
		$return = array();
		$keys 	= array_keys(get_object_vars($result[0])); //eg 'id', 'title', 'description', 'active'
		$key 	= array_shift($keys); //eg 'id' or 'title'
		foreach ($result as $row) {
			$return[] = $row->{$key};
		}
		return $return;
	}

	/**
	  * Specify an ORDER BY param for your selects in the query builder
	  *
	  * @param	mixed	$fields		Comma-separated list of fields or array
	  * @return	object				Passing the object up the chain
	  */
	public function order_by($fields) {
		if (empty(self::$table)) trigger_error('db::order_by() must come after a db::table() statement');
		if (!is_array($fields)) $fields = a::separated($fields);
		foreach ($fields as &$field) $field = self::field($field);
		self::$order_by = array_merge(self::$order_by, $fields);
		return $this;
	}

	/**
	  * Run a raw query.  Private for security (temporarily public)
	  *
	  * @param	string	$sql		Raw SQL to be executed
	  * @return	array				An array of results
	  */
	public static function query($sql) {
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
	  * Refresh the schema
	  *
	  */
	public static function refresh() {
		self::$schema = false;
	}
	
	/**
	  * Rename a table in the query builder
	  *
	  * @param	string	$new_name	What to rename the table to
	  * @return	object				Passing the object up the chain
	  */
	public function rename($new_name) {
		if (empty(self::$table)) trigger_error('db::rename() must come after a db::table() statement');
		if (self::table_exists(self::$table)) self::query('RENAME TABLE ' . self::$table . ' TO ' . $new_name);
		//todo check if new table exists and if so, rename new_name to
		self::table($new_name);
		return $this;
	}

	/**
	  * Specify which fields to select using query builder
	  *
	  * @param	string	$selects	Any number of field names
	  */
	public static function select() {
		$fields = a::arguments(func_get_args());
		if (empty($fields)) $fields = array('*');

		//build query
		foreach ($fields as &$field) $field = TAB . self::field($field);
		$sql = 'SELECT ' . NEWLINE . implode(',' . NEWLINE, $fields) . NEWLINE . 'FROM ' . self::$table;
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
    	//todo decide on whether to check if exists, what to do if not
		self::$wheres = self::$joins  = self::$order_by = array(); //clear out old queries
		self::$table = $table;
		return new self;
    }

	/**
	  * Tell whether a given $table exists or not
	  * @param	string		$table	Table name to check
	  * @return	bool				True or false if exists
	  *
	  */
	public static function table_exists($table) {
		return in_array($table, self::tables());
	}

	/**
	  * List of tables
	  * @return	array				Resultset of tables
	  *
	  */
	public static function tables() {
		//try using cached array
		if (!empty(self::$schema)) return array_keys(self::$schema);

		//build and cache array
		$tables = self::query('SHOW TABLES');
		if (count($tables)) {
			$keys = array_keys(get_object_vars($tables[0]));
			foreach ($tables as &$table) {
				$table = $table->{$keys[0]};
				self::$schema[$table] = array();
			}
		}
		return $tables;
	}

	/**
	  * Run a SQL update
	  *
	  * @param	array	$fields		Associative array of fields to update
	  * @return	int					Number of records affected
	  */
    public static function update($updates) {
		if (empty(self::$table)) trigger_error('db::update() must come after a db::table() statement');

    	//add metadata automatically, if fields are present
		if (!isset($updates['updated']) && self::field_exists(self::$table, 'updated')) $updates['updated'] = 'NOW()';
		if (!isset($updates['updater']) && self::field_exists(self::$table, 'updater')) $updates['updater'] = http::user();

		//die(html::dump(self::fields(self::$table)));

		//loop through updates and format
		$fields = array();
    	foreach ($updates as $field=>$value) {
    		if (is_array($value)) continue;
    		if (empty($value) && self::field_nullable(self::$table, $field)) $value = 'NULL';
   			$fields[] = NEWLINE . TAB . $field . ' = ' . self::escape($value);
    	}

    	//assemble SQL query
    	$sql = 'UPDATE ' . self::$table . ' SET ' . implode(',', $fields);
		if (!empty(self::$wheres)) $sql .= NEWLINE . 'WHERE' . NEWLINE . TAB . implode(' AND ' . NEWLINE . TAB, self::$wheres);

		//execute and return
		if (self::query($sql)) return self::$connection->rowCount();
    }

	/**
	  * Specify a where clause on the query builder
	  *
	  * @param	string	$field		The field to compare
	  * @param	string	$operator	The operator to use
	  * @param	int		$value		The value to compare
	  * @return	object				Pass the query builder object up the chain
	  */
	public function where($field, $value=1, $operator='=') {
		self::$wheres[] = self::field($field) . ' ' . $operator . ' ' . self::escape($value);
		return $this;
	}
}