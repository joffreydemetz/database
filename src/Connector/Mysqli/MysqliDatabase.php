<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Connector\Mysqli;

use JDZ\Database\Database;
use JDZ\Database\DatabaseHelper;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\QueryException;

/**
 * Mysqli format adapter for the Database Class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MysqliDatabase extends Database
{
  /**
   * {@inheritDoc}
   */
  public $name = 'mysqli';

  /**
   * {@inheritDoc}
   */
  protected $nameQuote = '`';

  /**
   * {@inheritDoc}
   */
  protected $nullDate = '0000-00-00 00:00:00';

  /**
   * {@inheritDoc}
   */
  protected static $dbMinimum = '5.0.4';
  
  /**
   * {@inheritDoc}
   */
  public static function isSupported()
  {
    return ( function_exists('mysqli_connect') );
  }

  /**
   * {@inheritDoc}
   */
  protected function __construct(array $options=[])
  {
    $options['host']     = isset($options['host'])     ? $options['host']           : 'localhost';
    $options['port']     = isset($options['port'])     ? (int) $options['port']     : null;
    $options['socket']   = isset($options['socket'])   ? $options['socket']         : null;
    $options['database'] = isset($options['database']) ? $options['database']       : null;
    $options['select']   = isset($options['select'])   ? (bool) $options['select']  : true;
    $options['logpath']  = isset($options['logpath'])  ? $options['logpath']        : '';
    $options['logall']   = isset($options['logall'])   ? $options['logall']         : false;
    $options['prefix']   = isset($options['prefix'])   ? $options['prefix']         : 'sto_';
    $options['charset']  = isset($options['charset'])  ? $options['charset']        : 'utf8';
    
    parent::__construct($options);
  }
  
  /**
   * {@inheritDoc}
   */
  public function connect()
  {
    if ( $this->connection ){
      return;
    }

    if ( !static::isSupported() ){
      throw new DatabaseException('Driver is not available');
    }
    
    $this->connection = mysqli_init();
    
    $connected = $this->connection->real_connect(
      $this->options['host'], $this->options['user'], $this->options['password'], null, $this->options['port'], $this->options['socket']
    );

    if ( !$connected ){
      throw new DatabaseException('Could not connect to MySQL: ' . $this->connection->connect_error);
    }

    // If auto-select is enabled select the given database.
    if ( $this->options['select'] && $this->options['database'] ){
      $this->select($this->options['database']);
    }
    
    // Set charactersets (needed for MySQL 4.1.2+).
    $this->utf = $this->setUtf();

    // Set sql_mode to non_strict mode
    // mysqli_query($this->connection, "SET @@SESSION.sql_mode = '';");
    
    // If auto-select is enabled select the given database.
    // if ( $options['select'] && !empty($options['database']) ){
      // $this->select($options['database']);
    // }
  }

  /**
   * Determines if the connection to the server is active.
   *
   * @return  boolean  True if connected to the database engine.
   */
  public function connected()
  {
    if ( is_object($this->connection) ){
      return $this->connection->ping();
      // return mysqli_ping($this->connection);
    }
    
    return false;
  }

  /**
   * Disconnects the database.
   *
   * @return  void
   */
  public function disconnect()
  {
    // Close the connection.
    if ( is_callable($this->connection, 'close') ){
      $this->connection->close();
      // return mysqli_close($this->connection);
    }
    
    $this->connection = null;
  }
  
  /**
   * {@inheritDoc}
   */
  public function getQuery($new=false)
  {
    if ( $new ){
      return new MysqliQuery($this);
    }
    
    return $this->sql;
  }
  
  /**
   * {@inheritDoc}
   */
  public function execute()
  {
    $sql = DatabaseHelper::replacePrefix((string) $this->sql, $this->tablePrefix, '#__');
    
    if ( $this->limit > 0 || $this->offset > 0 ){
      $sql .= ' LIMIT '.$this->offset.', '.$this->limit;
    }
    
    if ( $this->logall === true ){
      $this->loggers['dbqueries']->add('info', $sql);
    }
    
    $this->errorNum = 0;
    $this->errorMsg = '';
    
    $this->cursor = mysqli_query($this->connection, $sql);

    if ( !$this->cursor ){
      $this->errorNum = (int) mysqli_errno($this->connection);
      $this->errorMsg = (string) mysqli_error($this->connection).' SQL='.$sql;
      
      $this->loggers['dbfails']->add('error', '['.$this->errorNum.'] '.$this->errorMsg);
      
      throw new QueryException($this->errorMsg, $this->errorNum);
    }
    
    return $this->cursor;
  }

  /**
   * {@inheritDoc}
   */
  public function select($database)
  {
    if ( !$database ){
      return false;
    }

    if ( !mysqli_select_db($this->connection, $database) ){
      throw new DatabaseException('Couldn\'t reach the selected database ('.$database.') ['.get_class($this).']');
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
   */
  public function insertid()
  {
    return $this->connection->insert_id;
    // return mysqli_insert_id($this->connection);
  }

  /**
   * {@inheritDoc}
   */
  public function escape($text, $extra=false)
  {
    $result = $this->connection->real_escape_string($text);

    if ( $extra ){
      $result = addcslashes($result, '%_');
    }

    return $result;
    // $result = mysqli_real_escape_string($this->connection, $text);

    // if ( $extra ){
      // $result = addcslashes($result, '%_');
    // }

    // return $result;
  }
  
  /**
   * {@inheritDoc}
   */
  public function getNumRows($cursor = null)
  {
    return mysqli_num_rows($cursor ?: $this->cursor);
  }

  /**
   * {@inheritDoc}
   */
  public function getAffectedRows()
  {
    return $this->connection->affected_rows;
  }

  /**
   * {@inheritDoc}
   */
  public function getTableCreate($tables)
  {
    $result = [];

    $tables = (array)$tables;
    
    foreach($tables as $table){
      $this->setQuery('SHOW CREATE table '.$this->qn($this->escape($table)));
      $row = $this->loadRow();
      $result[$table] = $row[1];
    }
    
    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getTableColumns($table, $typeOnly=true)
  {
    $result = [];

    $this->setQuery('SHOW FULL COLUMNS FROM '.$this->qn($this->escape($table)));
    $fields = $this->loadObjectList();
    
    if ( $typeOnly ){
      foreach ($fields as $field){
        $result[$field->Field] = preg_replace("/[(0-9)]/", '', $field->Type);
      }
    }
    else {
      foreach ($fields as $field){
        $result[$field->Field] = $field;
      }
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function getTableKeys($table)
  {
    $this->setQuery('SHOW KEYS FROM '.$this->qn($table));
    $keys = $this->loadObjectList();
    return $keys;
  }

  /**
   * {@inheritDoc}
   */
  public function getTableList()
  {
    $this->setQuery('SHOW TABLES');
    $tables = $this->loadColumn();
    return $tables;
  }

  /**
   * {@inheritDoc}
   */
  public function getCollation()
  {
    return $this->setQuery('SELECT @@collation_database;')->loadResult();
    // $result = null;
    
    // $this->setQuery('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '.$this->q($this->database).' LIMIT 1;');
    // $result = $this->loadResult();
    
    // return $result;
  }
  
  /**
   * {@inheritDoc}
   */
  public function getVersion()
  {
    return $this->connection->server_info;
    // return mysqli_get_server_info($this->connection);
  }

  /**
   * {@inheritDoc}
   */
  public function setUTF()
  {
    $this->connection->set_charset($this->options['charset']);
    // mysqli_query($this->connection, "SET NAMES '".$this->options['charset']."'");
  }

  /**
   * {@inheritDoc}
   */
  public function dropTable($tableName, $ifExists=true)
  {
    $this->setQuery('DROP TABLE '.($ifExists ? 'IF EXISTS ' : '').$this->quoteName($tableName));
    $this->execute();
  }
  
  /**
   * {@inheritDoc}
   */
  public function renameTable($oldTable, $newTable, $backup=null, $prefix=null)
  {
    $this->setQuery('RENAME TABLE ' . $oldTable . ' TO ' . $newTable);
    $this->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function truncateTable($table)
  {
    $this->setQuery('TRUNCATE TABLE '.$this->quoteName($table));
    $this->execute();
  }
  
  /**
   * {@inheritDoc}
   */
  public function lockTable($table)
  {
    $this->setQuery('LOCK TABLES ' . $this->qn($table) . ' WRITE');
    $this>execute();
  }

  /**
   * {@inheritDoc}
   */
  public function unlockTables()
  {
    $this->setQuery('UNLOCK TABLES');
    $this->execute();
  }
  
  /**
   * {@inheritDoc}
   */
  public function transactionCommit()
  {
    $this->setQuery('COMMIT');
    $this->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function transactionRollback()
  {
    $this->setQuery('ROLLBACK');
    $this->execute();
  }

  /**
   * {@inheritDoc}
   */
  public function transactionStart()
  {
    $this->setQuery('START TRANSACTION');
    $this->execute();
  }

  /**
   * {@inheritDoc}
   */
  protected function fetchArray($cursor=null)
  {
    return mysqli_fetch_row($cursor ?: $this->cursor);
  }

  /**
   * {@inheritDoc}
   */
  protected function fetchAssoc($cursor=null)
  {
    return mysqli_fetch_assoc($cursor ?: $this->cursor);
  }

  /**
   * {@inheritDoc}
   */
  protected function fetchObject($cursor=null, $class='\\stdClass')
  {
    return mysqli_fetch_object($cursor ?: $this->cursor, $class);
  }

  /**
   * {@inheritDoc}
   */
  protected function freeResult($cursor=null)
  {
    mysqli_free_result($cursor ?: $this->cursor);
  }
}
