<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Query;

use JDZ\Database\DatabaseInterface;

/**
 * Query connector class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Query implements QueryInterface
{
  /**
   * The database connection resource
   * 
   * @var   DatabaseInterface 
   */
  protected $db = null;

  /**
   * The query type
   * 
   * @var   string 
   */
  protected $type = '';

  /**
   * The query element for a generic query (type = null)
   * 
   * @var   Element
   */
  protected $element = null;

  /**
   * The select element
   * 
   * @var   Element
   */
  protected $select = null;

  /**
   * The delete element
   * 
   * @var   Element
   */
  protected $delete = null;

  /**
   * The update element
   * 
   * @var   Element
   */
  protected $update = null;

  /**
   * The insert element
   * 
   * @var   Element
   */
  protected $insert = null;

  /**
   * The from element
   * 
   * @var   Element
   */
  protected $from = null;

  /**
   * The join element
   * 
   * @var   Element
   */
  protected $join = null;

  /**
   * The set element
   * 
   * @var   Element
   */
  protected $set = null;

  /**
   * The where element
   * 
   * @var   Element
   */
  protected $where = null;

  /**
   * The group element
   * 
   * @var   Element
   */
  protected $group = null;

  /**
   * The having element
   * 
   * @var   Element
   */
  protected $having = null;

  /**
   * The column list for an INSERT statement
   * 
   * @var   Element
   */
  protected $columns = null;

  /**
   * The values list for an INSERT statement
   * 
   * @var   Element
   */
  protected $values = null;

  /**
   * The order element
   * 
   * @var   Element
   */
  protected $order = null;

  /**
   * The union element
   * 
   * @var   Element
   */
  protected $union = null;

  /**
   * The auto increment insert field element
   * 
   * @var   Object
   */
  protected $autoIncrementField = null;
  
  /**
   * Constructor
   *
   * @param   DatabaseInterface  $db  The database connector resource.
   */
  public function __construct(DatabaseInterface $db)
  {
    $this->db = $db;
  }
  
  /**
   * Magic method to get the string representation of this object
   * 
   * @return   string
   */
  public function __toString()
  {
    $query = '';

    switch($this->type){
      case 'element':
        $query .= (string) $this->element;
        break;
      
      case 'select':
        $query .= (string) $this->select;
        $query .= (string) $this->from;
        if ( $this->join ){
          // special case for joins
          foreach($this->join as $join){
            $query .= (string) $join;
          }
        }

        if ( $this->where ){
          $query .= (string) $this->where;
        }

        if ( $this->group ){
          $query .= (string) $this->group;
        }

        if ( $this->having ){
          $query .= (string) $this->having;
        }

        if ( $this->order ){
          $query .= (string) $this->order;
        }

        break;

      case 'union':
        $query .= (string) $this->union;
        break;

      case 'delete':
        $query .= (string) $this->delete;
        $query .= (string) $this->from;

        if ( $this->join ){
          // special case for joins
          foreach($this->join as $join){
            $query .= (string) $join;
          }
        }

        if ( $this->where ){
          $query .= (string) $this->where;
        }

        break;
      
      case 'update':
        $query .= (string) $this->update;

        if ( $this->join ){
          // special case for joins
          foreach($this->join as $join){
            $query .= (string) $join;
          }
        }

        $query .= (string) $this->set;

        if ( $this->where ){
          $query .= (string) $this->where;
        }

        break;

      case 'insert':
        $query .= (string) $this->insert;

        // Set method
        if ( $this->set ){
          $query .= (string) $this->set;
        }
        
        // Columns-Values method
        elseif ( $this->values ){
          if ( $this->columns ){
            $query .= (string) $this->columns;
          }
          
          $query .= ' VALUES ';
          $query .= (string) $this->values;
        }

        break;
    }

    return $query;
  }

  /**
   * Magic method to return some protected property values
   *
   * @param   string  $name  The name of the property to return
   * @return   mixed
   */
  public function __get($name)
  {
    return isset($this->$name) ? $this->$name : null;
  }

  /**
   * Magic method to provide method alias support for quote() and qn().
   *
   * @param   string  $method  The called method.
   * @param   array   $args    The array of arguments passed to the method.
   * @return   string  The aliased method's return value or null.

   */
  public function __call($method, $args)
  {
    if ( empty($args) ){
      return;
    }
    
    if ( $method === 'q' ){
      return $this->quote($args[0], isset($args[1]) ? $args[1] : true);
    }
    
    if ( $method === 'qn' ){
      return $this->quoteName($args[0]);
    }
    
    if ( $method === 'e' ){
      return $this->escape($args[0], isset($args[1]) ? $args[1] : false);
    }
  }

 /**
   * Provide deep copy support to nested objects and arrays when cloning.
   *
   * @return   void
   */
  public function __clone()
  {
    foreach($this as $k => $v){
      if ( $k === 'db' ){
        continue;
      }

      if ( is_object($v) || is_array($v) ){
        $this->$k = unserialize(serialize($v));
      }
    }
  }
  
  /**
   * Creates a formatted dump of the query for debugging purposes
   * 
   * @return   string
   */
  public function dump()
  {
    return '<pre class="query">' . str_replace('#__', $this->db->getTablePrefix(), (string)$this) . '</pre>';
  }
  
  /**
   * Clear data from the query or a specific clause of the query.
   *
   * @param   string  $clause  Optionally, the name of the clause to clear, or nothing to clear the whole query.
   * @return   Query  Returns this object to allow chaining.
   */
  public function clear($clause=null)
  {
    switch($clause){
      case 'select':
        $this->select = null;
        $this->type   = null;
        break;

      case 'delete':
        $this->delete = null;
        $this->type   = null;
        break;

      case 'update':
        $this->update = null;
        $this->type   = null;
        break;

      case 'insert':
        $this->insert = null;
        $this->type   = null;
        $this->autoIncrementField = null;
        break;

      case 'from':
        $this->from = null;
        break;

      case 'join':
        $this->join = null;
        break;

      case 'set':
        $this->set = null;
        break;

      case 'where':
        $this->where = null;
        break;

      case 'group':
        $this->group = null;
        break;

      case 'having':
        $this->having = null;
        break;

      case 'order':
        $this->order = null;
        break;

      case 'columns':
        $this->columns = null;
        break;

      case 'values':
        $this->values = null;
        break;

      default:
        $this->type               = null;
        $this->select             = null;
        $this->delete             = null;
        $this->update             = null;
        $this->insert             = null;
        $this->from               = null;
        $this->join               = null;
        $this->set                = null;
        $this->where              = null;
        $this->group              = null;
        $this->having             = null;
        $this->order              = null;
        $this->columns            = null;
        $this->values             = null;
        $this->autoIncrementField = null;
        break;
    }

    return $this;
  }
  
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
  public function escape($text, $extra = false)
  {
    return $this->db->escape($text, $extra);
  }
  
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
  public function quote($text, $escape=true)
  {
    return $this->db->q(($escape ? $this->db->escape($text) : $text));
  }

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
  public function quoteName($name)
  {
    return $this->db->qn($name);
  }
  
  /**
   * Returns a PHP date() function compliant date format for the database driver.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the getDateFormat method directly.
   * @return   string  The format string.
   */
  public function dateFormat()
  {
    return $this->db->getDateFormat();
  }

  /**
   * Get the null or zero representation of a timestamp for the database driver.
   *
   * This method is provided for use where the query object is passed to a function for modification.
   * If you have direct access to the database object, it is recommended you use the nullDate method directly.
   *
   * @param   bool    $quoted   Add quotes
   * @return   string  Null or zero representation of a timestamp.
   */
  public function nullDate($quoted=true)
  {
    if ( $quoted ){
      $result = $this->db->q($this->db->getNullDate());
    }
    return $this->db->getNullDate();
  }

  /**
   * Concatenates an array of column names or values.
   *
   * @param   array   $values     An array of values to concatenate.
   * @param   string  $separator  As separator to place between each value.
   * @return   string  The concatenated values.
   */
  public function concatenate($values, $separator = null)
  {
    if ( $separator ){
      return 'CONCATENATE(' . implode(' || ' . $this->q($separator) . ' || ', $values) . ')';
    }
    else {
      return 'CONCATENATE(' . implode(' || ', $values) . ')';
    }
  }

  /**
   * Gets the current date and time.
   * @return   string
   */
  public function currentTimestamp()
  {
    return 'CURRENT_TIMESTAMP()';
  }

  /**
   * Casts a value to a char.
   *
   * Ensure that the value is properly quoted before passing to the method.
   *
   * @param   string  $value  The value to cast as a char.
   * @return   string  Returns the cast value.
   */
  public function castAsChar($value)
  {
    return $value;
  }

  /**
   * Gets the number of characters in a string.
   *
   * Note, use 'length' to find the number of bytes in a string.
   *
   * @param   string  $field  A value.
   * @return   string  The required char length call.
   */
  public function charLength($field)
  {
    return 'CHAR_LENGTH(' . $field . ')';
  }

  
  /**
   * Adds a column, or array of column names that would be used for an INSERT INTO statement.
   *
   * @param   mixed  $columns  A column name, or array of column names.
   * @return   Query  Returns this object to allow chaining.
   */
  public function columns($columns)
  {
    if ( is_null($this->columns) ){
      $this->columns = new Element('()', $columns);
    }
    else {
      $this->columns->append($columns);
    }

    return $this;
  }

  /**
   * Add a table name to the DELETE clause of the query.
   *
   * @param   string  $table  The name of the table to delete from.
   * @return   Query  Returns this object to allow chaining.
   */
  public function delete($table = null)
  {
    $this->type   = 'delete';
    $this->delete = new Element('DELETE', null);

    if ( !empty($table) ){
      $this->from($table);
    }
    
    return $this;
  }

  /**
   * Add a table to the FROM clause of the query.
   *
   * @param   mixed  $tables  A string or array of table names.
   * @return   Query  Returns this object to allow chaining.
   */
  public function from($tables)
  {
    if ( is_null($this->from) ){
      $this->from = new Element('FROM', $tables);
    }
    else {
      $this->from->append($tables);
    }

    return $this;
  }

  /**
   * Add a grouping column to the GROUP clause of the query.
   *
   * @param   mixed  $columns  A string or array of ordering columns.
   * @return   Query  Returns this object to allow chaining.
   */
  public function group($columns)
  {
    if ( is_null($this->group) ){
      $this->group = new Element('GROUP BY', $columns);
    }
    else {
      $this->group->append($columns);
    }

    return $this;
  }

  /**
   * A conditions to the HAVING clause of the query.
   *
   * @param   mixed   $conditions  A string or array of columns.
   * @param   string  $glue        The glue by which to join the conditions. Defaults to AND.
   * @return   Query  Returns this object to allow chaining.
   */
  public function having($conditions, $glue='AND')
  {
    if ( is_null($this->having) ){
      $glue = strtoupper($glue);
      $this->having = new Element('HAVING', $conditions, " $glue ");
    }
    else {
      $this->having->append($conditions);
    }

    return $this;
  }

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
   * @param   mixed    $table           The name of the table to insert data into.
   * @param   boolean  $incrementField  The name of the field to auto increment.
   * @return   Query  Returns this object to allow chaining.

   */
  public function insert($table, $incrementField=false)
  {
    $this->type = 'insert';
    $this->insert = new Element('INSERT INTO', $table);
    $this->autoIncrementField = $incrementField;

    return $this;
  }

  /**
   * Add a JOIN clause to the query.
   *
   * @param   string  $type        The type of join. This string is prepended to the JOIN keyword.
   * @param   string  $conditions  A string or array of conditions.
   * @return   Query  Returns this object to allow chaining.
   */
  public function join($type, $conditions)
  {
    if ( is_null($this->join) ){
      $this->join = [];
    }
    $this->join[] = new Element(strtoupper($type) . ' JOIN', $conditions);
    return $this;
  }

  /**
   * Add a LEFT JOIN clause to the query.
   *
   * @param   string  $condition  The join condition.
   * @return   Query  Returns this object to allow chaining.
   */
  public function leftJoin($condition)
  {
    $this->join('LEFT', $condition);
    return $this;
  }

  /**
   * Add a RIGHT JOIN clause to the query.
   *
   * @param   string  $condition  The join condition.
   * @return   Query  Returns this object to allow chaining.
   */
  public function rightJoin($condition)
  {
    $this->join('RIGHT', $condition);
    return $this;
  }

  /**
   * Add an INNER JOIN clause to the query.
   *
   * @param   string  $condition  The join condition.
   * @return   Query  Returns this object to allow chaining.
   */
  public function innerJoin($condition)
  {
    $this->join('INNER', $condition);
    return $this;
  }

  /**
   * Add an OUTER JOIN clause to the query.
   *
   * @param   string  $condition  The join condition.
   * @return   Query  Returns this object to allow chaining.
   */
  public function outerJoin($condition)
  {
    $this->join('OUTER', $condition);
    return $this;
  }

  /**
   * Get the length of a string in bytes.
   *
   * Note, use 'charLength' to find the number of characters in a string.
   *
   * @param   string  $value  The string to measure.
   * @return   int
   */
  public function length($value)
  {
    return 'LENGTH('.$value.')';
  }

  /**
   * Add a ordering column to the ORDER clause of the query.
   *
   * @param   mixed  $columns  A string or array of ordering columns.
   * @return   Query  Returns this object to allow chaining.
   */
  public function order($columns)
  {
    if ( is_null($this->order) ){
      $this->order = new Element('ORDER BY', $columns);
    }
    else {
      $this->order->append($columns);
    }
    
    return $this;
  }

  /**
   * Add a single column, or array of columns to the SELECT clause of the query.
   *
   * @param   mixed  $columns  A string or an array of field names
   * @return   Query  Returns this object to allow chaining.
   */
  public function select($columns)
  {
    $this->type = 'select';

    if ( is_null($this->select) ){
      $this->select = new Element('SELECT', $columns);
    }
    else {
      $this->select->append($columns);
    }

    return $this;
  }

  /**
   * Add a single condition string, or an array of strings to the SET clause of the query.
   *
   * @param   mixed   $conditions  A string or array of string conditions.
   * @param   string  $glue        The glue by which to join the condition strings. Defaults to ,.
   * @return   Query  Returns this object to allow chaining.
   */
  public function set($conditions, $glue=',')
  {
    if ( is_null($this->set) ){
      $glue = strtoupper($glue);
      $this->set = new Element('SET', $conditions, "\n\t$glue ");
    }
    else {
      $this->set->append($conditions);
    }
    
    return $this;
  }

  /**
   * Add a table name to the UPDATE clause of the query.
   *
   * @param   string  $table  A table to update.
   * @return   Query  Returns this object to allow chaining.
   */
  public function update($table)
  {
    $this->type = 'update';
    $this->update = new Element('UPDATE', $table);

    return $this;
  }

  /**
   * Adds a tuple, or array of tuples that would be used as values for an INSERT INTO statement.
   *
   * @param   string  $values  A single tuple, or array of tuples.
   * @return   Query  Returns this object to allow chaining.
   */
  public function values($values, $glue=', ')
  {
    if ( is_null($this->values) ){
      $this->values = new Element('()', $values, ')'.$glue.'(');
    }
    else {
      $this->values->append($values);
    }

    return $this;
  }

  /**
   * Add a single condition, or an array of conditions to the WHERE clause of the query.
   *
   * @param   mixed   $conditions  A string or array of where conditions.
   * @param   string  $glue        The glue by which to join the conditions. Defaults to AND.
   * @return   Query  Returns this object to allow chaining.
   */
  public function where($conditions, $glue='AND')
  {
    if ( is_null($this->where) ){
      $glue = strtoupper($glue);
      $this->where = new Element('WHERE', $conditions, " $glue ");
    }
    else {
      $this->where->append($conditions);
    }

    return $this;
  }

  /**
   * Add a query to UNION with the current query.
   * Multiple unions each require separate statements and create an array of unions.
   *
   * Usage:
   * $query->union('SELECT name FROM  #__foo')
   * $query->union('SELECT name FROM  #__foo','distinct')
   * $query->union(array('SELECT name FROM  #__foo','SELECT name FROM  #__bar'))
   *
   * @param   mixed    $query     The Query object or string to union.
   * @param   boolean  $distinct  True to only return distinct rows from the union.
   * @param   string   $glue      The glue by which to join the conditions.
   * @return   mixed    The Query object on success or boolean false on failure.
   */
  public function union($query, $distinct=false, $glue='')
  {
    if ( !is_null($this->order) ){
      $this->clear('order');
    }
    
    if ( $distinct ){
      $name = 'UNION DISTINCT ()';
      $glue = ')' . PHP_EOL . 'UNION DISTINCT (';
    }
    else {
      $glue = ')' . PHP_EOL . 'UNION (';
      $name = 'UNION ()';
    }
    
    if ( is_null($this->union) ){
        $this->union = new Element($name, $query, "$glue");
    }
    else {
      $glue = '';
      $this->union->append($query);
    }
    
    return $this;
  }

  /**
   * Add a query to UNION DISTINCT with the current query. Simply a proxy to Union with the Distinct clause.
   *
   * @param   mixed   $query  The Query object or string to union.
   * @param   string  $glue   The glue by which to join the conditions.
   * @return   mixed   The Query object on success or boolean false on failure.
   */
  public function unionDistinct($query, $glue='')
  {
    $distinct = true;
    return $this->union($query, $distinct, $glue);
  }
}
