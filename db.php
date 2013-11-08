<?php
/**
 * 
 * DB
 * 
 * Methods for working with the database.  Contains a Laravel-esque query builder
 *
 * 
 * @package Foundation
 */

class db {

	//caches & logs
	private static $connection	= false;
	private static $schema		= false;
	private static $queries		= array();

	//query builder vars
	private $table 		= false;
	private $wheres		= array();
	private $joins		= array();
	private $order_by	= array();

	/**
	  * Make an associative array from resultset
	  * Takes the first object var of a resultset and makes it the associative key
	  * Make sure your first element is unique, or you will get interesting results!
	  * 
	  */
	public function associate() {
		$selects = a::arguments(func_get_args());
		if (!$result = $this->select($selects)) return $result;
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
	private static function connect() {
		if (is_object(self::$connection)) return;
		
		try {
			self::$connection = new PDO(
				'mysql:host=' . config::get('db.host') . ';dbname=' . config::get('db.name', true) . ';',
				config::get('db.user'), 
				config::get('db.password'), 
				array(
					PDO::ATTR_ERRMODE				=>PDO::ERRMODE_WARNING,
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
	  * Query builder COUNT(*) function
	  * 
	  */
	public function count() {
		if (empty($this->table)) trigger_error('db::count() must come after a db::table() statement');
		$sql = 'SELECT COUNT(*) FROM ' . $this->table;
		$sql .= self::sql_where($this->wheres);
		return self::query($sql);
	}

	/**
	  * Show all the queries that have been run so far
	  *
	  * @return	string				Returns HTML formatted table of SQL queries
	  */
	public static function debug() {
		$queries = self::$queries;
		foreach ($queries as &$query) $query = html::pre($query);
		return ($queries) ? html::dump($queries) : 'self::$queries is empty';
	}
	
	/**
	  * Drop a table from the query builder
	  *
	  */
	public function delete() {
		if (empty($this->table)) trigger_error('db::delete() must come after a db::table() statement');
		$sql = 'DELETE FROM ' . $this->table;
		$sql .= self::sql_where($this->wheres);
		self::query($sql);
	}

	/**
	  * Drop a table from the query builder
	  *
	  */
	public function drop() {
		if (empty($this->table)) trigger_error('db::drop() must come after a db::table() statement');
		self::query('DROP TABLE IF EXISTS ' . $this->table);
		$this->table = false;
	}

	/**
	  * Escape strings where necessary
	  *
	  * @return	string				Returns escaped string
	  */
	private static function escape($value) {
		if (is_numeric($value) || $value == 'NULL') return $value;
		return '\'' . str::escape($value) . '\'';
	}
	
	/**
	  * Format field name for non-ambiguity
	  *
	  * @return	string
	  */
	private static function field($field, $table) {
		if (!strstr($field, '.')) $field = $table . '.' . $field;
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
		self::refresh($table);
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
		trigger_error('db::field_type not finished yet');
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
	public function find($id) {
		if (empty($this->table)) trigger_error('db::find() must come after a db::table() statement');

		self::where('id', $id);
		return $this;
	}

	/**
	  * Specify which fields to select using query builder
	  *
	  * @param	string	$fields	Any number of field names
	  * @return	mixed				Returns an object-resultset or null
	  */
	public function first() {
		if (!is_object($this)) trigger_error('db::select must be called in object context');
		$fields = a::arguments(func_get_args());
		$result = $this->select($fields);
		if (!count($result)) return false;
		return array_shift($result);
	}

	/**
	  * SQL Insert
	  *
	  * @param	string	$selects	Any amount of field names
	  * @return	mixed				Returns an object-resultset or null
	  */
	public function insert($inserts) {
		if (empty($this->table)) trigger_error('db::insert() must come after a db::table() statement');
		$fields = $values = array();

		//add metadata automatically
		if (!isset($updates['updated']) && self::field_exists($this->table, 'updated')) $inserts['updated'] = self::now();
		if (!isset($updates['updater']) && self::field_exists($this->table, 'updater')) $inserts['updater'] = http::user();
		if (!isset($updates['precedence']) && self::field_exists($this->table, 'precedence')) {
			$obj = self::query('SELECT MAX(precedence) precedence FROM ' . $this->table);
			$inserts['precedence'] = $obj[0]->precedence + 1;
		}
		if (!isset($updates['active'])  && self::field_exists($this->table, 'active'))  $inserts['active']  = 1;

    	foreach ($inserts as $field=>$value) {
    		//if (self::field_exists($this->table))
	   		if (empty($value) && self::field_nullable($this->table, $field)) $value = 'NULL';
    		$fields[] = NEWLINE . TAB . $field;
    		if ($field == 'password') {
	    		$values[] = NEWLINE . TAB . 'PASSWORD(' . self::escape($value) . ')';
    		} else {
	    		$values[] = NEWLINE . TAB . self::escape($value);    			
    		}
    	}

    	$sql = 'INSERT INTO ' . $this->table . ' (' . implode(',', $fields) . NEWLINE . ') VALUES (' . implode(',', $values) . NEWLINE . ')';
		
		return self::query($sql);
	}

	/**
	  * Add a join
	  *
	  * @param	string			The name of the table to join
	  * @param	string			The connection statement, eg table1.id = table2.table1_id
	  * @return	object			Passing the object up the chain
	  */
	public function join($table, $connection) {
		$this->joins[] = 'JOIN ' . $table . ' ON ' . $connection;
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
		$this->joins[] = 'LEFT JOIN ' . $table . ' ON ' . $connection;
		return $this;
	}
	
	/**
	  * Sometimes it's useful to have a simple one-dimensional array of a single column
	  * @param	string		$field	Field name to get
	  * @return	array				One-dimensional array
	  * 
	  */
	public function lists($field) {
		if (!$result = $this->select($field)) return $result;
		$return = array();
		$keys 	= array_keys(get_object_vars($result[0])); //eg 'id', 'title', 'description', 'active'
		$key 	= array_shift($keys); //eg 'id' or 'title'
		foreach ($result as $row) {
			$return[] = $row->{$key};
		}
		return $return;
	}

	/**
	  * Current timestamp, adjusted for timezone
	  * @return	string				SQL-formatted time
	  * 
	  */
	public static function now() {
		$gmtimenow = time() - (int)substr(date('O'), 0, 3) * 60 * 60; 
		return date('Y-m-d H:i:s', $gmtimenow);
	}

	/**
	  * Specify an OR WHERE clause on the query builder
	  *
	  * @param	string	$field		The field to compare
	  * @param	string	$operator	The operator to use
	  * @param	int		$value		The value to compare
	  * @return	object				Pass the query builder object up the chain
	  */
	public function or_where($field, $value=1, $operator='=') {
		return $this->where($field, $value, $operator, 'OR');
	}

	/**
	  * Specify an ORDER BY param for your selects in the query builder
	  *
	  * @param	mixed	$fields		Comma-separated list of fields or array
	  * @return	object				Passing the object up the chain
	  */
	public function order_by($fields) {
		if (empty($this->table)) trigger_error('db::order_by() must come after a db::table() statement');
		if (!is_array($fields)) $fields = a::separated($fields);
		foreach ($fields as &$field) $field = self::field($field, $this->table);
		$this->order_by = array_merge($this->order_by, $fields);
		return $this;
	}

	/**
	  * Run a raw or prepared query.
	  *
	  * @param	string	$sql		Raw SQL to be executed
	  * @param	array	$bindings	Optional value bindings for your query
	  * @return	array				An array of results
	  */
	private static function query($sql, $bindings=null) {
		self::connect();

		$result = self::$connection->prepare($sql);

		if ($result->execute($bindings)) {
			//success
			self::$queries[] = $sql;
			$verb = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if (in_array($verb, array('SELECT', 'SHOW'))) return $result->fetchAll(PDO::FETCH_OBJ);
			if ($verb == 'INSERT') return self::$connection->lastInsertId();
			return true;
		}

		//fail
		$error = self::$connection->errorInfo();
		trigger_error($error[2] . html::pre($sql));
	}
	
	/**
	  * Refresh the schema
	  * @param	string	$table	Optionally only destroy one table from the schema
	  *
	  */
	public static function refresh($table=false) {
		if ($table) {
			self::$schema[$table] = array();
		} else {
			self::$schema = false;
		}
	}
	
	/**
	  * Rename a table in the query builder
	  *
	  * @param	string	$new_name	What to rename the table to
	  * @return	object				Passing the object up the chain
	  */
	public function rename($new_name) {
		if (empty($this->table)) trigger_error('db::rename() must come after a db::table() statement');
		if (self::table_exists($this->table)) self::query('RENAME TABLE ' . $this->table . ' TO ' . $new_name);
		//todo check if new table exists and if so, rename new_name to
		self::table($new_name);
		return $this;
	}

	/**
	  * Specify which fields to select using query builder
	  *
	  * @param	string	$selects	Any number of field names
	  */
	public function select() {
		$fields = a::arguments(func_get_args());
		if (empty($fields)) $fields = array('*');

		if (!isset($this) || !is_object($this)) trigger_error('db::select must be called in object context, eg $result = db::table(\'tablename\')->select()');

		//build query
		foreach ($fields as &$field) $field = TAB . self::field($field, $this->table);
		$sql = 'SELECT ' . NEWLINE . implode(',' . NEWLINE, $fields) . NEWLINE . 'FROM' . NEWLINE . TAB . $this->table;
		if (!empty($this->joins)) $sql .= NEWLINE . implode(NEWLINE, $this->joins);
		$sql .= self::sql_where($this->wheres);
		if (!empty($this->order_by)) $sql .= NEWLINE . 'ORDER BY ' . NEWLINE . TAB . implode(', ' . NEWLINE . TAB, $this->order_by);
		
		return self::query($sql);
	}

	/**
	  * Construct SQL statement for WHEREs (I don't like the name of this function)
	  *
	  * @param	array	$wheres		Must be passed in, because not in object context
	  * @return	string				SQL
	  */
	private static function sql_where($wheres) {
		if (!empty($wheres)) return NEWLINE . 'WHERE ' . implode($wheres);
	}
	
	/**
	  * Start a new query builder by specifying the table
	  *
	  * @param	string	$table		The table in question
	  * @return	object				A query builder object to attach stuff to
	  */
    public static function table($table) {
    	//todo decide on whether to check if exists, what to do if not
		$return = new self;
		$return->table = $table;
		return $return;
    }

	/**
	  * Create an empty table with the given name
	  * Todo make metadata fields optional, maybe have optional $fields parameter for additional fields
	  * @param	string		$table	Name of table to create
	  *
	  */
	public static function table_create($table) {
		if (self::table_exists($table)) trigger_error('Table ' . $table . ' already exists!');
		//todo make programmatically
		self::query('CREATE TABLE `' . $table . '` (
			`id` int NOT NULL AUTO_INCREMENT,
			`updated` datetime NOT NULL,
			`updater` int,
			`precedence` int NOT NULL,
			`active` tinyint NOT NULL,
			PRIMARY KEY (`id`)
		);');
		self::$schema[$table] = array();
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
    public function update($updates, $silently=false) {
		if (empty($this->table)) trigger_error('db::update() must come after a db::table() statement');

    	//add metadata automatically, if fields are present
    	if (!$silently) {
			if (!isset($updates['updated']) && self::field_exists($this->table, 'updated')) $updates['updated'] = self::now();
			if (!isset($updates['updater']) && self::field_exists($this->table, 'updater')) $updates['updater'] = http::user();
    	}

		//die(html::dump(self::fields($this->table)));

		//loop through updates and format
		$fields = array();
    	foreach ($updates as $field=>$value) {
    		if (is_array($value)) continue;

    		if ($field == 'password') {
	   			$fields[] = NEWLINE . TAB . $field . ' = PASSWORD(' . self::escape($value) . ')';
    		} else {
	    		if (empty($value) && self::field_nullable($this->table, $field)) $value = 'NULL';
	   			$fields[] = NEWLINE . TAB . $field . ' = ' . self::escape($value);
    		}
    	}

    	//assemble SQL query
    	$sql = 'UPDATE' . NEWLINE . TAB . $this->table . NEWLINE . 'SET ' . implode(',', $fields);
    	$sql .= self::sql_where($this->wheres);

		//execute and return
		return self::query($sql);
    }

	/**
	  * Specify a WHERE clause on the query builder
	  *
	  * @param	string	$field		The field to compare
	  * @param	string	$operator	The operator to use
	  * @param	int		$value		The value to compare
	  * @return	object				Pass the query builder object up the chain
	  */
	public function where($field, $value=1, $operator='=', $logical='AND') {
		
		//escape or post-process value?
		if ($field == 'password') {
			$value = 'PASSWORD(' . self::escape($value) . ')';
		} elseif (is_array($value)) {
			foreach ($value as &$v) $v = self::escape($v);
			$value = '(' . implode(',', $value) . ')';
		} else {
			$value = self::escape($value);
		}

		$this->wheres[] = NEWLINE . TAB . (count($this->wheres) ? ' ' . $logical . ' ' : '') . self::field($field, $this->table) . ' ' . $operator . ' ' . $value;
		
		return $this;
	}

	public function where_null($field) {
		self::where($field, 'NULL', 'IS');
		return $this;
	}
}