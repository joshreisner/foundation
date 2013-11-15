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
	  * Query builder COUNT(*) function
	  * 
	  */
	public function count() {
		$sql = 'SELECT COUNT(*) AS `count` FROM ' . $this->table;
		$sql .= self::sql_where($this->wheres);
		$count = self::query($sql);
		return $count[0]->count;
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
		$sql = 'DELETE FROM ' . $this->table;
		$sql .= self::sql_where($this->wheres);
		self::query($sql);
	}

	/**
	  * Drop a table or field (column) using the query builder
	  *
	  */
	public function drop() {
		if (empty($this->field)) {
			self::query('DROP TABLE IF EXISTS ' . $this->table);
			unset($this->table);
			self::refresh();
		} else {
			self::query('ALTER TABLE ' . $this->table . ' DROP COLUMN ' . $this->field);
			unset($this->field);
			self::refresh($this->table);
		}
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
	  * Add a field to the query builder, create if does not exist
	  * 
	  * @param  string 		$field 		This should be a valid field name.  As a shortcut, it can be table.field
	  * @param  array 		$properties Array of properties if field should be created
	  * @return	string
	  */
	private function field($field, $properties=false) {

		if (strstr($field, '.')) {
			list($table, $field) = explode('.', $field);
			$return = self::table($table);
			return $return->field($field, $properties);
		}

		if (self::field_exists($this->table, $field)) {
			if (!$properties) {
				trigger_error('db::field() looked for ' . $this->table . '.' . $field . ' but it does not exist and properties are not set.');
			} else {
				self::query('ALTER TABLE `' . $this->table . '` ADD COLUMN `' . $field . '` ' . self::field_properties($properties));
				self::refresh($this->table);
				self::fields_reorder($this->table);
			}
		}

		$this->field = $field;

		return $this;
	}

	/**
	  * Silently check whether a given $field in a $table exists or not
	  * @param	string		$table	Table name
	  * @param	string		$field	Field name
	  * @return	bool				True or false if exists
	  *
	  */
	public static function field_exists($table, $field) {
		if (!self::table_exists($table)) return false;
		return in_array($field, self::fields($table));
	}

	/**
	  * Format field name in dot-notation for specificity
	  * @param	string		$field	Field name (this can be table.name)
	  * @param	string		$table	Table name (table default for prepending)
	  *
	  * @return	string
	  */
	private static function field_name($field, $table) {
		if (!strstr($field, '.')) $field = $table . '.' . $field;
		return $field;
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
		return !self::$schema[$table][$field]['not_null'];
	}

	/**
	  * Return SQL-formatted string of field properties
	  * @param	array		$properties	Associative array: type, length, not_null, default, key
	  * @return	string					SQL-formatted string of field properties
	  *
	  */
	private static function field_properties($properties) {
		//ALTER TABLE contacts ADD email VARCHAR(60);
		//ALTER TABLE contacts ADD COLUMN complete DECIMAL(2,1) NULL
		if (empty($properties['type'])) trigger_error('db::field_properties needs a field type to be set');

		$return = strtoupper($properties['type']);

		switch ($return) {
			case 'DATE':
			case 'DATETIME':
			case 'TEXT':
			case 'TIME':
			$return = ' ' . $return;
			break;

			case 'VARCHAR':
			$properties['length'] = (empty($properties['length'])) ? 255 : $properties['length'];
			$return = ' ' . $return . '(' . $properties['length'] . ')';
			break;

			default:
			trigger_error('db::field_properties not yet programmed for ' . $properties['type']);
		}

		if (isset($properties['not_null']) && $properties['not_null']) {
			$return .= ' NOT NULL';
		}

		return $return;
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
			//this array structure matches the field_properties array structure
			$field->Length = false;
			if ($pos = strpos($field->Type, '(')) {
				$field->Length = substr($field->Type, $pos + 1, -1);
				$field->Type = substr($field->Type, 0, $pos);
			}
			self::$schema[$table][$field->Field] = array(
				'type'=>$field->Type,
				'length'=>$field->Length,
				'not_null'=>$field->Null == 'YES' ? false : true,
				'key'=>$field->Key,
				'default'=>$field->Default,
				'auto'=>$field->Extra == 'auto_increment' ? true : false,
			);
		}
		return array_keys(self::$schema[$table]);
	}

	/**
	  * Reorder a table's fields so the system fields are in the right places.
	  * Todo: make system fields configurable
	  * @param	string		$table			Table name to reorder
	  * @param	array		$other_fields	Array of field names
	  *
	  */
	public static function fields_reorder($table, $other_fields=false) {
		//determine what the last non-system column was
		$fields = self::fields($table);
		$last = false;
		foreach ($fields as $field) {
			if (!in_array($field, array('id', 'updated', 'updater', 'precedence', 'active'))) {
				$last = $field;
			}
		}

		//if there are non-system columns, reorder
		if ($last) {
			self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT FIRST');
			self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN updated DATETIME AFTER ' . $last);
			self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN updater INT AFTER updated');
			self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN precedence INT AFTER updater');
			self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN active TINYINT AFTER precedence');
		}

		$last = 'id';
		if ($other_fields) {
			foreach ($other_fields as $field) {
				self::query('ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $field . self::field_properties(self::$schema[$table][$field]) . ' AFTER ' . $last);
				$last = $field;
			}
		}

		self::refresh($table);
	}

	/**
	  * Find (where id = ) shortcut
	  *
	  * @param	int		$id	ID
	  * @return	object			Passing the object up the chain
	  */
	public function find($id) {
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
		if (!is_array($fields)) $fields = a::separated($fields);
		foreach ($fields as &$field) $field = self::field_name($field, $this->table);
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
		$error = $result->errorInfo();
		trigger_error($error[2] . html::pre($sql));
	}
	
	/**
	  * Refresh the schema
	  * @param	string	$table	Optionally only destroy one table from the schema
	  *
	  */
	private static function refresh($table=false) {
		if ($table) {
			self::$schema[$table] = array();
		} else {
			self::$schema = false;
		}
	}

	/**
	  * Rename a table or field/column using the query builder
	  *
	  * @param	string	$name		What to rename the table or field/column to
	  * @return	object				Passing the object up the chain
	  */
	public function rename($name) {
		if (empty($this->field)) {
			//rename table, update schema & query builder
			self::query('RENAME TABLE ' . $this->table . ' TO ' . $name);
			self::refresh();
			self::table($name);
		} else {
			//rename field, update schema & query builder
			self::query('ALTER  ' . $this->table . ' CHANGE ' . $this->field . ' ' . $name . ' ' . self::field_properties($this->field));
			self::refresh($this->table);
			self::field($name);
		}

		return $this;
	}

	/**
	  * Specify which fields to select using query builder
	  *
	  * @param	string	$selects	Any number of field names
	  * @param	array				db::query resultset
	  */
	public function select() {
		$fields = a::arguments(func_get_args());
		if (empty($fields)) $fields = array('*');

		if (!isset($this) || !is_object($this)) trigger_error('db::select must be called in object context, eg $result = db::table(\'tablename\')->select()');

		//build query
		foreach ($fields as &$field) $field = TAB . self::field_name($field, $this->table);
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
		return '';
	}
	
	/**
	  * Start a new query builder by specifying the table, create if it does not exist
	  *
	  * @param	string	$table		The table in question
	  * @return	object				A query builder object to attach stuff to
	  */
    public static function table($table) {
    	if (!self::table_exists($table)) {
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
		$return = new self;
		$return->table = $table;
		return $return;
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

    	//add metadata automatically, if fields are present
    	if (!$silently) {
			if (!isset($updates['updated']) && self::field_exists($this->table, 'updated')) $updates['updated'] = self::now();
			if (!isset($updates['updater']) && self::field_exists($this->table, 'updater')) $updates['updater'] = http::user();
    	}

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

		$this->wheres[] = NEWLINE . TAB . (count($this->wheres) ? ' ' . $logical . ' ' : '') . self::field_name($field, $this->table) . ' ' . $operator . ' ' . $value;
		
		return $this;
	}

	public function where_null($field) {
		self::where($field, 'NULL', 'IS');
		return $this;
	}
}