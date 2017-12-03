<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Query;

/**
 * QueryInterface connector class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface QueryInterface
{
  /**
   * Clear data from the query or a specific clause of the query.
   *
   * @param   string            $clause   Optionally, the name of the clause to clear, or nothing to clear the whole query.
   * @return   QueryInterface    Returns this object to allow chaining.
   */
  public function clear($clause=null);
  
  /**
   * Add a single column, or array of columns to the SELECT clause of the query.
   *
   * @param   mixed           $columns  A string or an array of field names
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function select($columns);
  
  /**
   * Add a table to the FROM clause of the query.
   *
   * @param   mixed           $tables  A string or array of table names.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function from($tables);
  
  /**
   * Add a JOIN clause to the query.
   *
   * @param   string          $type        The type of join. This string is prepended to the JOIN keyword.
   * @param   string          $conditions  A string or array of conditions.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function join($type, $conditions);

  /**
   * Add a LEFT JOIN clause to the query.
   *
   * @param   string          $condition  The join condition.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function leftJoin($condition);

  /**
   * Add a RIGHT JOIN clause to the query.
   *
   * @param   string          $condition  The join condition.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function rightJoin($condition);

  /**
   * Add an INNER JOIN clause to the query.
   *
   * @param   string          $condition  The join condition.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function innerJoin($condition);

  /**
   * Add an OUTER JOIN clause to the query.
   *
   * @param   string          $condition  The join condition.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function outerJoin($condition);
  
  /**
   * Add a single condition, or an array of conditions to the WHERE clause of the query.
   *
   * @param   mixed           $conditions  A string or array of where conditions.
   * @param   string          $glue        The glue by which to join the conditions. Defaults to AND.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function where($conditions, $glue='AND');
  
  /**
   * A conditions to the HAVING clause of the query.
   *
   * @param   mixed           $conditions  A string or array of columns.
   * @param   string          $glue        The glue by which to join the conditions. Defaults to AND.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function having($conditions, $glue='AND');
  
  /**
   * Add a grouping column to the GROUP clause of the query.
   *
   * @param   mixed           $columns  A string or array of ordering columns.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function group($columns);
  
  /**
   * Add a ordering column to the ORDER clause of the query.
   *
   * @param   mixed           $columns  A string or array of ordering columns.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function order($columns);

  /**
   * Add a query to UNION with the current query.
   * Multiple unions each require separate statements and create an array of unions.
   *
   * Usage:
   * $query->union('SELECT name FROM  #__foo')
   * $query->union('SELECT name FROM  #__foo','distinct')
   * $query->union(array('SELECT name FROM  #__foo','SELECT name FROM  #__bar'))
   *
   * @param   mixed    $query     The QueryInterface object or string to union.
   * @param   boolean  $distinct  True to only return distinct rows from the union.
   * @param   string   $glue      The glue by which to join the conditions.
   * @return   mixed    The QueryInterface object on success or boolean false on failure.
   */
  public function union($query, $distinct=false, $glue='');

  /**
   * Add a query to UNION DISTINCT with the current query. Simply a proxy to Union with the Distinct clause.
   *
   * @param   mixed   $query  The QueryInterface object or string to union.
   * @param   string  $glue   The glue by which to join the conditions.
   * @return   mixed   The QueryInterface object on success or boolean false on failure.
   */
  public function unionDistinct($query, $glue='');
  
  /**
   * Add a table name to the INSERT clause of the query.
   *
   * Note that you must not mix insert, update, delete and select method calls when building a query.
   *
   * Usage:
   * $query->insert('#__a')->set('id = 1');
   * $query->insert('#__a)->columns('id, title')->values('1,2')->values->('3,4');
   * $query->insert('#__a)->columns('id, title')->values(array('1,2', '3,4'));
   *
   * @param   mixed           $table           The name of the table to insert data into.
   * @param   boolean         $incrementField  The name of the field to auto increment.
   * @return   QueryInterface  Returns this object to allow chaining.

   */
  public function insert($table, $incrementField=false);
  
  /**
   * Add a table name to the UPDATE clause of the query.
   *
   * @param   string          $table  A table to update.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function update($table);

  /**
   * Add a table name to the DELETE clause of the query.
   *
   * @param   string          $table  The name of the table to delete from.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function delete($table = null);
  
  /**
   * Adds a column, or array of column names that would be used for an INSERT INTO statement.
   *
   * @param   mixed           $columns  A column name, or array of column names.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function columns($columns);

  /**
   * Adds a tuple, or array of tuples that would be used as values for an INSERT INTO statement.
   *
   * @param   string          $values  A single tuple, or array of tuples.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function values($values, $glue=', ');
  
  /**
   * Add a single condition string, or an array of strings to the SET clause of the query.
   *
   * @param   mixed           $conditions  A string or array of string conditions.
   * @param   string          $glue        The glue by which to join the condition strings. Defaults to ,.
   * @return   QueryInterface  Returns this object to allow chaining.
   */
  public function set($conditions, $glue=',');
  
  /**
   * Get the null or zero representation of a timestamp for the database driver.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the nullDate method directly.
   *
   * @param   bool    $quoted   Add quotes
   * @return   string  Null or zero representation of a timestamp.
   */
  public function nullDate($quoted=true);
  
  /**
   * Gets the current date and time.
   * @return   string
   */
  public function currentTimestamp();

  /**
   * Casts a value to a char.
   *
   * Ensure that the value is properly quoted before passing to the method.
   *
   * @param   string  $value  The value to cast as a char.
   * @return   string  Returns the cast value.
   */
  public function castAsChar($value);
  
  /**
   * Gets the number of characters in a string.
   *
   * Note, use 'length' to find the number of bytes in a string.
   *
   * @param   string  $field  A value.
   * @return   string  The required char length call.
   */
  public function charLength($field);

  /**
   * Get the length of a string in bytes.
   *
   * Note, use 'charLength' to find the number of characters in a string.
   *
   * @param   string  $value  The string to measure.
   * @return   int
   */
  public function length($value);

  /**
   * Concatenates an array of column names or values.
   *
   * @param   array   $values     An array of values to concatenate.
   * @param   string  $separator  As separator to place between each value.
   * @return   string  The concatenated values.
   */
  public function concatenate($values, $separator = null);
  
  /**
   * Escape a string for usage in an SQL statement.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the escape method directly.
   *
   * Note that 'e' is an alias for this method as it is in Database.
   *
   * @param   string   $text   The string to be escaped.
   * @param   boolean  $extra  Optional parameter to provide extra escaping.
   * @return   string  The escaped string.
   */
  public function escape($text, $extra=false);
  
  /**
   * Quote and optionally escape a string to database requirements for insertion into the database.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the quote method directly.
   *
   * Note that 'q' is an alias for this method as it is in Database.
   *
   * @param   string   $text    The string to quote.
   * @param   boolean  $escape  True to escape the string, false to leave it unchanged.
   * @return   string  The quoted input string.
   */
  public function quote($text, $escape=true);

  /**
   * Wrap an SQL statement identifier name such as column, table or database names in quotes to prevent injection
   * risks and reserved word conflicts.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the quoteName method directly.
   *
   * Note that 'qn' is an alias for this method as it is in Database.
   *
   * @param   string  $name  The identifier name to wrap in quotes.
   * @return   string  The quote wrapped name.
   */
  public function quoteName($name);
  
  /**
   * Creates a formatted dump of the query for debugging purposes
   * 
   * @return   string
   */
  public function dump();
  
  /**
   * Returns a PHP date() function compliant date format for the database driver.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the getDateFormat method directly.
   * @return   string  The format string.
   */
  public function dateFormat();
}
