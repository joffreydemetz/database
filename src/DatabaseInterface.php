<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database;

use JDZ\Database\Exception\QueryException;
use JDZ\Database\Query\QueryInterface;

/**
 * Database interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface DatabaseInterface 
{
  /**
   * Test to see if the connector is available
   *
   * @return  boolean  True on success, false otherwise.
   */
  public static function isSupported();
  
  /**
   * A unique id to identifiy activity (optionnal)
   * 
   * @param   int   $Uid  Activity Uid (ex: logged user ID)
   * @return   void
   */
  public function setUid($Uid);
  
  /**
   * Inserts a row into a table based on an object's properties.
   *
   * @param   string    $table    The name of the database table to insert into.
   * @param   object    &$object  A reference to an object whose public properties match the table fields.
   * @param   string    $key      The name of the primary key. If provided the object property is updated.
   * @return   boolean   True on success.
   * @throws   QueryException
   */
  public function insertObject($table, &$object, $key=null);
  
  /**
   * Updates a row in a table based on an object's properties.
   *
   * @param   string   $table    The name of the database table to update.
   * @param   object   &$object  A reference to an object whose public properties match the table fields.
   * @param   string   $key      The name of the primary key.
   * @param   boolean  $nulls    True to update null fields or false to ignore them.
   * @return   boolean  True on success.
   * @throws   QueryException
   */
  public function updateObject($table, &$object, $key, $nulls = false);
  
  /**
   * Sets the SQL statement string for later execution.
   *
   * @param   QueryInterface|string   $query   The SQL statement to set either as a Query object or a string.
   * @param   int                     $offset  The affected row offset to set.
   * @param   int                     $limit   The maximum affected rows to set.
   * @return   Database  This object to support method chaining.
   */
  public function setQuery($query, $offset=0, $limit=0);

  /**
   * Get the first row of the result set from the database query as an associative array
   * of ['field_name' => 'row_value'].
   * @return   mixed  The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadAssoc();
  
  /**
   * Get an array of the result set rows from the database query where each row is an associative array
   * of ['field_name' => 'row_value'].  The array of rows can optionally be keyed by a field name, but defaults to
   * a sequential numeric array.
   *
   * NOTE: Chosing to key the result array by a non-unique field name can result in unwanted
   * behavior and should be avoided.
   *
   * @param   string  $key     The name of a field on which to key the result array.
   * @param   string  $column  An optional column name. Instead of the whole row, only this column value will be in
   * the result array.
   * @return   mixed   The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadAssocList($key = null, $column = null);

  /**
   * Get the first row of the result set from the database query as an array.  Columns are indexed
   * numerically so the first column in the result set would be accessible via <var>$row[0]</var>, etc.
   * @return   mixed  The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadRow();

  /**
   * Get an array of the result set rows from the database query where each row is an array.  The array
   * of objects can optionally be keyed by a field offset, but defaults to a sequential numeric array.
   *
   * NOTE: Choosing to key the result array by a non-unique field can result in unwanted
   * behavior and should be avoided.
   *
   * @param   string  $key  The name of a field on which to key the result array.
   * @return   mixed   The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadRowList($key=null);

  /**
   * Get the first row of the result set from the database query as an object.
   *
   * @param   string  $class  The class name to use for the returned row object.
   * @return   mixed   The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadObject($class='\stdClass');

  /**
   * Get an array of the result set rows from the database query where each row is an object.  The array
   * of objects can optionally be keyed by a field name, but defaults to a sequential numeric array.
   *
   * NOTE: Choosing to key the result array by a non-unique field name can result in unwanted
   * behavior and should be avoided.
   *
   * @param   string  $key    The name of a field on which to key the result array.
   * @param   string  $class  The class name to use for the returned row objects.
   * @return   mixed   The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadObjectList($key='', $class='\stdClass');

  /**
   * Get an array of values from the <var>$offset</var> field in each row of the result set from
   * the database query.
   *
   * @param   int  $offset  The row offset to use to build the result array.
   * @return   mixed    The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadColumn($offset = 0);

  /**
   * Get the first field of the first row of the result set from the database query.
   * @return   mixed  The return value or null if the query failed.
   * @throws   QueryException
   */
  public function loadResult();
  
  /**
   * Get the next row in the result set from the database query as an object.
   *
   * @param   string  $class  The class name to use for the returned row object.
   * @return   mixed   The result of the query as an array, false if there are no more rows.
   * @throws   QueryException
   */
  public function loadNextObject($class='\stdClass');

  /**
   * Get the next row in the result set from the database query as an array.
   * @return   mixed  The result of the query as an array, false if there are no more rows.
   * @throws   QueryException
   */
  public function loadNextRow();

  /**
   * Proxy to the execute() method
   * @see   execute()
   */
  public function query();
  
  /**
   * Execute the SQL statement.
   * @return   mixed  A database cursor resource on success, boolean false on failure.
   * @throws   QueryException
   */
  public function execute();

  /**
   * Quote and optionally escape a string to database requirements for insertion into the database.
   *
   * @param   string   $text    The string to quote.
   * @param   boolean  $escape  True (default) to escape the string, false to leave it unchanged.
   * @return   string  The quoted input string.
   */
  public function quote($text, $escape = true);

  /**
   * Wrap an SQL statement identifier name such as column, table or database names in quotes to prevent injection
   * risks and reserved word conflicts.
   *
   * @param   mixed  $name  The identifier name to wrap in quotes, or an array of identifier names to wrap in quotes.
   *               Each type supports dot-notation name.
   * @param   mixed  $as    The AS query part associated to $name. It can be string or array, in latter case it has to be
   *               same length of $name; if is null there will not be any AS part for string or array element.
   * @return   mixed  The quote wrapped name, same type of $name.
   */
  public function quoteName($name, $as = null);
  
  /**
   * Determines if the connection to the server is active.
   * @return   boolean  True if connected to the database engine.

   */
  public function connected();

  /**
   * Escape a string for usage in an SQL statement.
   *
   * @param   string   $text   The string to be escaped.
   * @param   boolean  $extra  Optional parameter to provide extra escaping.
   * @return   string   The escaped string.
   */
  public function escape($text, $extra=false);

  /**
   * Get the number of affected rows for the previous executed SQL statement.
   * @return   int  The number of affected rows.
   */
  public function getAffectedRows();

  /**
   * Get the number of returned rows for the previous executed SQL statement.
   *
   * @param   resource  $cursor  An optional database cursor resource to extract the row count from.
   * @return   int   The number of returned rows.
   */
  public function getNumRows($cursor = null);

  /**
   * Get the current query object or a new Query object.
   *
   * @param   boolean         $new  False to return the current query object, True to return a new Query object.
   * @return   QueryInterface  The current query object or a new object extending the Query class.
   * @throws   QueryException  If the query object is not available
   */
  public function getQuery($new = false);

  /**
   * Get the auto-incremented value from the last INSERT statement.
   * @return   int  The value of the auto-increment field from the last inserted row.
   */
  public function insertid();

  /**
   * Select a database for use.
   *
   * @param   string  $database  The name of the database to select for use.
   * @return   boolean  True if the database was successfully selected.
   * @throws   QueryException
   */
  public function select($database);

  /**
   * Set the connection to use UTF-8 character encoding.
   * @return   boolean  True on success.
   */
  public function setUTF();
  
  /**
   * Truncate a table.
   *
   * @param   string  $table  The table to truncate
   * @return   void
   * @throws   QueryException
   */
  public function truncateTable($table);
  
  /**
   * Check whether the installed database version is supported by the database driver
   * @return   boolean  True if the database version is supported
   *
   * @since   12.1
   */
  public function isMinimumVersion();
  
  /**
   * Shows the table CREATE statement that creates the given tables.
   *
   * @param   mixed  $tables  A table name or a list of table names.
   * @return   array  A list of the create SQL for the tables.
   * @throws   QueryException
   */
  public function getTableCreate($tables);

  /**
   * Retrieves field information about the given tables.
   *
   * @param   string   $table     The name of the database table.
   * @param   boolean  $typeOnly  True (default) to only return field types.
   * @return   array  An array of fields by table.
   * @throws   QueryException
   */
  public function getTableColumns($table, $typeOnly=true);

  /**
   * Retrieves field information about the given tables.
   *
   * @param   mixed  $tables  A table name or a list of table names.
   * @return   array  An array of keys for the table(s).
   */
  public function getTableKeys($tables);

  /**
   * Get an array of all tables in the database.
   * @return   array  An array of all the tables in the database.
   * @throws   QueryException
   */
  public function getTableList();

  /**
   * Get the database collation in use by sampling a text field of a table in the database.
   * @return   mixed  The collation in use by the database or boolean false if not supported.
   */
  public function getCollation();

  /**
   * Get the version of the database connector
   * @return   string  The database connector version.
   * @throws   QueryException
   */
  public function getVersion();

  /**
   * Commit a transaction.
   * @return   void
   * @throws   QueryException
   */
  public function transactionCommit();

  /**
   * Roll back a transaction.
   * @return   void
   * @throws   QueryException
   */
  public function transactionRollback();

  /**
   * Initialize a transaction.
   * @return   void
   * @throws   QueryException
   */
  public function transactionStart();

  /**
   * Drops a table from the database.
   *
   * @param   string   $table     The name of the database table to drop.
   * @param   boolean  $ifExists  Optionally specify that the table must exist before it is dropped.
   * @return   Database  Returns this object to support chaining.
   * @throws   QueryException
   */
  public function dropTable($table, $ifExists = true);

  /**
   * Renames a table in the database.
   *
   * @param   string  $oldTable  The name of the table to be renamed
   * @param   string  $newTable  The new name for the table.
   * @param   string  $backup    Table prefix
   * @param   string  $prefix    For the table - used to rename constraints in non-mysql databases
   * @return   Database  Returns this object to support chaining.
   * @throws   QueryException
   */
  public function renameTable($oldTable, $newTable, $backup = null, $prefix = null);

  /**
   * Locks a table in the database.
   *
   * @param   string  $tableName  The name of the table to unlock.
   * @return   Database  Returns this object to support chaining.
   * @throws   QueryException
   */
  public function lockTable($tableName);

  /**
   * Unlocks tables in the database.
   * @return   void
   * @throws   QueryException
   */
  public function unlockTables();
}
