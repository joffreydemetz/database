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
  public $name = 'mysqli';
  protected $nameQuote = '`';
  protected $nullDate = '1000-01-01 00:00:00';
  protected static $dbMinimum = '5.7.1';
  
  public static function isSupported()
  {
    return ( function_exists('mysqli_connect') );
  }

  public function __construct(array $options=[])
  {
    $options['host']      = isset($options['host'])      ? $options['host']           : 'localhost';
    $options['port']      = isset($options['port'])      ? (int) $options['port']     : null;
    $options['socket']    = isset($options['socket'])    ? $options['socket']         : null;
    $options['logall']    = isset($options['logall'])    ? $options['logall']         : false;
    $options['tblprefix'] = isset($options['tblprefix']) ? $options['tblprefix']      : 'cal_';
    $options['charset']   = isset($options['charset'])   ? $options['charset']        : 'utf8';
    $options['database']  = isset($options['database'])  ? $options['database']       : null;
    $options['select']    = isset($options['select'])    ? (bool) $options['select']  : true;
    
    parent::__construct($options);
  }
  
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
    if ( $this->database ){
      $this->select($this->database);
    }
    
    // Set charactersets (needed for MySQL 4.1.2+).
    $this->utf = $this->setUtf();
  }

  public function connected()
  {
    if ( is_object($this->connection) ){
      return $this->connection->ping();
      // return mysqli_ping($this->connection);
    }
    
    return false;
  }

  public function disconnect()
  {
    // Close the connection.
    if ( is_callable($this->connection, 'close') ){
      $this->connection->close();
    }
    
    $this->connection = null;
  }
  
  public function getQuery($new=false)
  {
    if ( $new ){
      return new MysqliQuery($this);
    }
    
    return $this->sql;
  }
  
  public function execute()
  {
    $sql = DatabaseHelper::replacePrefix((string) $this->sql, $this->tablePrefix, '#__');
    
    if ( $this->limit > 0 || $this->offset > 0 ){
      $sql .= ' LIMIT '.$this->offset.', '.$this->limit;
    }
    
    if ( true === $this->profiling ){
      $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
      $f = isset($dbt[2]['function']) ? $dbt[2]['function'] : 'F';
      $c = isset($dbt[2]['class']) ? $dbt[2]['class'] : 'C';
      
      if ( preg_match("/^SELECT .+/", $sql) ){
        debugQuery(preg_replace("/\s+/", " ", $sql), $f.'::'.$c);
      }
    }
    
    if ( $this->logall === true ){
      $this->log('queries', $sql, 'info');
    }
    
    $this->errorNum = 0;
    $this->errorMsg = '';
    
    $this->cursor = mysqli_query($this->connection, $sql);

    if ( !$this->cursor ){
      $this->errorNum = (int) mysqli_errno($this->connection);
      $this->errorMsg = (string) mysqli_error($this->connection).' SQL='.$sql;
      
      $this->log('fails', '['.$this->errorNum.'] '.$this->errorMsg, 'error');
      
      throw new QueryException($this->errorMsg, $this->errorNum);
    }
    
    return $this->cursor;
  }

  public function select($database)
  {
    if ( !$database ){
      return false;
    }

    if ( !mysqli_select_db($this->connection, $database) ){
      $error = 'Couldn\'t reach the selected database ('.$database.') ['.get_class($this).']';
      $this->log('fails', $error, 'error');
      throw new DatabaseException($error);
    }
    
    return true;
  }
  
  public function insertid()
  {
    return $this->connection->insert_id;
    // return mysqli_insert_id($this->connection);
  }

  public function escape($text, $extra=false)
  {
    if ( !$this->connection ){
      die('No connection');
    }
    $result = $this->connection->real_escape_string($text);
    
    if ( $extra ){
      $result = addcslashes($result, '%_');
    }

    return $result;
  }
  
  public function getNumRows($cursor = null)
  {
    return mysqli_num_rows($cursor ?: $this->cursor);
  }

  public function getAffectedRows()
  {
    return $this->connection->affected_rows;
  }

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

  public function getTableColumns($table, $full=false)
  {
    static $columns;
    
    if ( !isset($columns) ){
      $columns = [];
    }
    
    $table = str_replace('#__', $this->getTablePrefix(), $table);
    
    if ( !isset($columns[$table]) ){
      $columns[$table] = [];
      
      $this->setQuery('SHOW '.($full?'FULL ':'').'COLUMNS FROM '.$table);
      $fields = $this->loadObjectList();
      foreach($fields as $field){
        $field->Type = preg_replace("/^([^\(]+).*$/", "$1", $field->Type);
        $columns[$table][$field->Field] = $field;
      }
    }
    
    return $columns[$table];
  }
  
  public function getTableKeys($table)
  {
    $table = str_replace('#__', $this->getTablePrefix(), $table);
    
    $this->setQuery('SHOW KEYS FROM '.$$table);
    $keys = $this->loadObjectList();
    return $keys;
  }

  public function getTableList()
  {
    static $tables;
    
    if ( !isset($tables) ){
      $this->setQuery('SHOW TABLES');
      $tables = $this->loadColumn();
    }
    
    return $tables;
  }

  public function tableExists($table)
  {
    $table = str_replace('#__', $this->getTablePrefix(), $table);
    
    $tables = $this->getTableList();
    return in_array($table, $tables);
  }

  public function getCollation()
  {
    return $this->setQuery('SELECT @@collation_database;')->loadResult();
  }
  
  public function getVersion()
  {
    return $this->connection->server_info;
  }

  public function setUTF()
  {
    $this->connection->set_charset($this->options['charset']);
  }

  public function dropTable($tableName, $ifExists=true)
  {
    $this->setQuery('DROP TABLE '.($ifExists ? 'IF EXISTS ' : '').$this->quoteName($tableName));
    $this->execute();
  }
  
  public function renameTable($oldTable, $newTable, $backup=null, $prefix=null)
  {
    $this->setQuery('RENAME TABLE ' . $oldTable . ' TO ' . $newTable);
    $this->execute();
  }

  public function startProfiling()
  {
    $this->profiling = true;
    
    $this->setQuery('SET profiling = 1;');
    if ( !$this->execute() ){
      die('error startProfiling');
    }
  }

  public function showProfiles()
  {
    $profiles = '';
    
    if ( !$this->profiling ){
      return false;
    }
    
    $this->setQuery('SHOW PROFILES;');
    $rows = $this->loadAssocList();
    
    foreach($rows as &$row){
      $this->setQuery('SHOW PROFILE FOR QUERY '.$row['Query_ID'].';');
      $row['infos'] = $this->loadAssocList();
    }
    
    if ( !$rows ){
      return false;
    }
    
    return $rows;
  }
  
  public function truncateTable($table)
  {
    $this->setQuery('TRUNCATE TABLE '.$this->quoteName($table));
    $this->execute();
  }
  
  public function lockTable($table)
  {
    $this->setQuery('LOCK TABLES ' . $this->qn($table) . ' WRITE');
    $this>execute();
  }

  public function unlockTables()
  {
    $this->setQuery('UNLOCK TABLES');
    $this->execute();
  }
  
  public function transactionCommit()
  {
    $this->setQuery('COMMIT');
    $this->execute();
  }

  public function transactionRollback()
  {
    $this->setQuery('ROLLBACK');
    $this->execute();
  }

  public function transactionStart()
  {
    $this->setQuery('START TRANSACTION');
    $this->execute();
  }

  protected function fetchArray($cursor=null)
  {
    return mysqli_fetch_row($cursor ?: $this->cursor);
  }

  protected function fetchAssoc($cursor=null)
  {
    return mysqli_fetch_assoc($cursor ?: $this->cursor);
  }

  protected function fetchObject($cursor=null, $class='\\stdClass')
  {
    return mysqli_fetch_object($cursor ?: $this->cursor, $class);
  }

  protected function freeResult($cursor=null)
  {
    mysqli_free_result($cursor ?: $this->cursor);
  }
}
