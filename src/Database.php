<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\QueryException;
use Monolog\Logger as MonologLogger;
use RuntimeException;

/**
 * Database abstract class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Database implements DatabaseInterface 
{
  /**
   * The name of the database driver
   * 
   * @var    string
   */
  protected $name;
  
  /**
   * Connection options
   *
   * @var   array
   */
  protected $options;

  /**
   * True to log all queries (DEV mode)
   * 
   * @var    bool  
   */
  protected $logall;
  
  /**
   * The name of the database
   * 
   * @var    string
   */
  protected $database;
  
  /**
   * The common database table prefix
   * 
   * @var    string  
   */
  protected $tablePrefix;

  /**
   * The null or zero representation of a timestamp for the database driver.  This should be
   * defined in child classes to hold the appropriate value for the engine.
   * 
   * @var    string  
   */
  protected $nullDate;

  /**
   * Database monolog loggers
   * 
   * @var    MonologLogger[]
   */
  protected $loggers = [];
  
  /**
   * The database connection resource
   * 
   * @var    resource 
   */
  protected $connection;

  /**
   * The database connection cursor from the last query
   * 
   * @var    resource 
   */
  protected $cursor;

  /**
   * The affected row limit for the current SQL statement
   * 
   * @var    int 
   */
  protected $limit = 0;

  /**
   * The character(s) used to quote SQL statement names such as table names or field names,
   * etc.  The child classes should define this as necessary.  If a single character string the
   * same character is used for both sides of the quoted name, else the first character will be
   * used for the opening quote and the second for the closing quote.
   * 
   * @var    string  
   */
  protected $nameQuote;

  /**
   * The affected row offset to apply for the current SQL statement
   * 
   * @var    int  
   */
  protected $offset = 0;

  /**
   * The current SQL statement to execute
   * 
   * @var    mixed  
   */
  protected $sql;

  /**
   * The database error number
   * 
   * @var    int  
   */
  protected $errorNum = 0;
  
  /**
   * The database error message
   * 
   * @var    string  
   */
  protected $errorMsg;
  
  /**
   * A unique id to identifiy activity (optionnal)
   * 
   * @var    int  
   */
  protected $Uid = 0;
  
  /**
   * PHP date() function compliant date format for the database driver
   * 
   * @var    string  
   */
  protected $dateFormat = 'Y-m-d H:i:s';
  
  /**
   * Profiling queries
   * 
   * @var    bool
   */
  protected $profiling = false;

  /**
   * The minimum supported database version
   * 
   * @var    string  
   */
  protected static $dbMinimum;
  
  /**
   * Database instances
   * 
   * @var    Database[]
   */
  protected static $instances;
  
  /**
   * Test to see if the connector is available
   * 
   * @return boolean  True on success, false otherwise.
   */
  public static function isSupported()
  {
    return false;
  }
  
  /**
   * Return a Database instance based on the given options. There are three global options and then
   * the rest are specific to the database driver. The 'driver' option defines which Connector class is
   * used for the connection -- the default is 'mysqli'. The 'database' option determines which database is to
   * be used for the connection.
   *
   * Instances are unique by name.
   * 
   * @param  string    $name     The unique name for the connection
   * @param  array     $options  Key/Value pairs
   * @return Database  A database object
   */
  public static function getInstance($name, array $options=[])
  {
    if ( !isset(self::$instances) ){
      self::$instances = [];
    }
    
    $options['driver']   = preg_replace('/[^A-Z0-9_\.-]/i', '', $options['driver']);
    $options['database'] = isset($options['database']) ? $options['database'] : null;
    
    if ( empty(self::$instances[$name]) ){
      $Class = __NAMESPACE__ . '\\Connector\\'.ucfirst($options['driver']).'\\'.ucfirst($options['driver']).'Database';
      
      if ( !class_exists($Class) ){
        throw new DatabaseException('Unrecognized database driver');
      }
      
      self::$instances[$name] = new $Class($options);
    }
    
    return self::$instances[$name];
  }

  /**
   * Constructor
   * @param  array  $options  List of options used to configure the connection
   */
  public function __construct(array $options)
  {
    $options['tblprefix'] = isset($options['tblprefix']) ? $options['tblprefix'] : 'cal_';
    $options['database']  = isset($options['database'])   ? $options['database'] : null;
    $options['select']    = isset($options['select'])     ? $options['select']   : true;
    
    $this->database    = $options['database'];
    $this->tablePrefix = $options['tblprefix'];
    $this->logall      = $options['logall'];
    
    unset(
      $options['database'],
      $options['tblprefix'],
      $options['logall']
    );
    
    $this->options = $options;
  }
  
  /**
   * Destructor
   * Close the SQL connection
   */
  public function __destruct()
  {
    $this->disconnect();
  }
  
  /**
   * Magic method to provide method alias support for quote() and quoteName().
   *
   * @param  string  $method  The called method.
   * @param  array   $args    The array of arguments passed to the method.
   * @return string  The aliased method's return value or null.
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
      return $this->quoteName($args[0], isset($args[1]) ? $args[1] : null);
    }
    
    if ( $method === 'e' ){
      return $this->escape($args[0], isset($args[1]) ? $args[1] : false);
    }
  }
  
  public function getTblPrefix()
  {
    return $this->tablePrefix;
  }
  
  public function setQueriesLogger($logger)
  {
    $this->loggers['queries'] = $logger;
    return $this;
  }
  
  public function setFailsLogger($logger)
  {
    $this->loggers['fails'] = $logger;
    return $this;
  }
  
  public function log($logger, $message, $type='info')
  {
    if ( isset($this->loggers[$logger]) ){
      $this->loggers[$logger]->add($type, $message);
    }
    return $this;
  }
  
  /**
   * {@inheritDoc}
   */
  public function setQuery($query, $offset=0, $limit=0)
  {
    $this->sql    = $query;
    $this->limit  = (int) max(0, $limit);
    $this->offset = (int) max(0, $offset);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function setUid($Uid)
  {
    $this->Uid = (int)$Uid;
    return $this;
  }
  
  public function getConnection()
  {
    return $this->connection;
  }
  
  public function getName()
  {
    return $this->name;
  }
  
  public function getTablePrefix()
  {
    return $this->tablePrefix;
  }
  
  public function getDateFormat()
  {
    return $this->dateFormat;
  }
  
  public function getNullDate($dateTime=true)
  {
    if ( $dateTime ){
      return $this->nullDate;
    }
    
    return substr($this->nullDate, 0, 10);
  }
  
  public function getUid()
  {
    return $this->Uid;
  }

  /**
   * Get the minimum supported database version.
   *
   * @return string  The minimum version number for the database driver.
   */
  public function getDbMinimum()
  {
    return static::$dbMinimum;
  }
  
  /**
   * Connects to the database if needed.
   *
   * @return void  Returns void if the database connected successfully.
   *
   * @throws  \RuntimeException
   */
  abstract public function connect();

  /**
   * Determines if the connection to the server is active.
   *
   * @return boolean  True if connected to the database engine.
   */
  abstract public function connected();

  /**
   * Disconnects the database.
   *
   * @return void
   */
  abstract public function disconnect();

  /**
   * Start profiling queries
   *
   * @return void
   */
  abstract public function startProfiling();
  
  /**
   * Show profiled queries
   *
   * @return string
   */
  abstract public function showProfiles();
  
  public function insertObject($table, $row, $key=null)
  {
    $fields = [];
    $values = [];
    
    $statement = 'INSERT INTO ' . $this->quoteName($table) . ' (%s) VALUES (%s)';

    foreach($row->all() as $k => $v){
      if ( is_array($v) || is_object($v) || $v === null ){
        continue;
      }
      
      $fields[] = $this->quoteName($k);
      $values[] = $this->quote($v);
    }
    
    $this->setQuery(sprintf($statement, implode(', ', $fields), implode(', ', $values)));
    if ( !$this->execute() ){
      return false;
    }
    
    $id = $this->insertid();
    if ( $key && $id ){
      $row->set($key, $id);
    }
    
    return true;
  }

  public function updateObject($table, $row, $key, $nulls=false)
  {
    $fields = [];
    $where = '';

    $statement = 'UPDATE ' . $this->quoteName($table) . ' SET %s WHERE %s';

    foreach($row->all() as $k => $v){
      if ( $k == $key ){
        $where = $this->quoteName($k) . '=' . $this->quote($v);
        continue;
      }

      if ( $v === null ){
        if ( !$nulls ){
          continue;
        }
        $val = 'NULL';
      }
      else {
        $val = $this->quote($v);
      }

      $fields[] = $this->quoteName($k) . '=' . $val;
    }

    if ( empty($fields) ){
      return true;
    }
    
    $this->setQuery(sprintf($statement, implode(', ', $fields), $where));
    
    return $this->execute();
  }
  
  public function loadAssoc()
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      if ( $array = $this->fetchAssoc($cursor) ){
        $result = $array;
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }
  
  public function loadAssocList($key=null, $column=null)
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      $result = [];
      
      while($row=$this->fetchAssoc($cursor)){
        $value = ($column) ? (isset($row[$column]) ? $row[$column] : $row) : $row;
        if ( $key ){
          $result[$row[$key]] = $value;
        }
        else {
          $result[] = $value;
        }
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }

  public function loadRow()
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      if ( $row = $this->fetchArray($cursor) ){
        $result = $row;
      }

      $this->freeResult($cursor);
    }

    return $result;
  }

  public function loadRowList($key=null)
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      $result = [];

      while($row = $this->fetchArray($cursor)){
        if ( $key !== null ){
          $result[$row[$key]] = $row;
        }
        else {
          $result[] = $row;
        }
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }

  public function loadObject($class='\\stdClass')
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      if ( $object = $this->fetchObject($cursor, $class) ){
        $result = $object;
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }

  public function loadObjectList($key='', $class='\\stdClass')
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      $result = [];

      while($row=$this->fetchObject($cursor, $class)){
        if ( $key ){
          $result[$row->$key] = $row;
        }
        else {
          $result[] = $row;
        }
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }

  public function loadColumn($offset = 0)
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      $result = [];
      
      while($row = $this->fetchArray($cursor)){
        $result[] = $row[$offset];
      }
      
      $this->freeResult($cursor);
    }
    
    return $result;
  }

  public function loadResult()
  {
    $result = null;

    if ( $cursor = $this->execute() ){
      if ( $row = $this->fetchArray($cursor) ){
        $result = $row[0];
      }

      $this->freeResult($cursor);
    }

    return $result;
  }

  public function loadNextObject($class='\\stdClass')
  {
    static $cursor;
    
    if ( $cursor = $this->execute() ){
      if ( $row = $this->fetchObject($cursor, $class) ){
        return $row;
      }
      
      $this->freeResult($cursor);
      
      $cursor = null;
      
      return false;
    }
    
    return $this->errorNum ? null : false;
  }

  public function loadNextRow()
  {
    static $cursor;

    if ( $cursor = $this->execute() ){
      if ( $row = $this->fetchArray($cursor) ){
        return $row;
      }
      
      $this->freeResult($cursor);
      
      $cursor = null;
      
      return false;
    }
    
    return $this->errorNum ? null : false;
  }

  public function query()
  {
    return $this->execute();
  }
  
  public function quote($text, $escape=true)
  {
    if ( is_int($text) || ctype_digit($text) ){
      if ( !preg_match("/^[0]+/", $text) ){
        return $text;
      }
    }
    
    return '\''.($escape ? $this->escape($text) : $text).'\'';
  }

  public function quoteName($name, $as=null)
  {
    if ( is_string($name) ){
      $quotedName = $this->quoteNameStr(explode('.', $name));

      $quotedAs = '';
      if ( !is_null($as) ){
        settype($as, 'array');
        $quotedAs .= ' AS ' . $this->quoteNameStr($as);
      }

      return $quotedName . $quotedAs;
    }

    $fin = [];

    if ( is_null($as) ){
      foreach($name as $str){
        $fin[] = $this->quoteName($str);
      }
    }
    elseif ( is_array($name) && (count($name) == count($as)) ){
      for($i = 0; $i < count($name); $i++){
        $fin[] = $this->quoteName($name[$i], $as[$i]);
      }
    }

    return $fin;
  }
  
  public function isMinimumVersion()
  {
    return version_compare($this->getVersion(), static::$dbMinimum) >= 0;
  }
  
  protected function quoteNameStr($strArr)
  {
    $q = $this->nameQuote;

    $parts = [];
    foreach($strArr as $part){
      if ( is_null($part) ){
        continue;
      }
      
      if ( strlen($q) == 1 ){
        $parts[] = $q . $part . $q;
      }
      else {
        $parts[] = $q{0} . $part . $q{1};
      }
    }

    return implode('.', $parts);
  }
}
