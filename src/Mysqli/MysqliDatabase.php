<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Mysqli;

use JDZ\Database\Database;
use JDZ\Database\Mysqli\MysqliStatement;

/**
 * Database
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MysqliDatabase extends Database
{
  public string $nullDate = '1000-01-01 00:00:00';
  public string $nameQuote = '`';
  public bool $mariadb = false;

  /**
   * @var    \mysqli|null
   */
  protected mixed $sqlConn;

  public function __construct(array $options)
  {
    if (empty($options['sqlModes'])) {
      $options['sqlModes'] = [
        // 'ONLY_FULL_GROUP_BY',
        'STRICT_TRANS_TABLES',
        // 'NO_ZERO_IN_DATE',
        // 'NO_ZERO_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
      ];
    }

    parent::__construct($options);
  }

  public function connect()
  {
    if (!$this->sqlConn) {
      $this->connection = new MysqliConnection($this->options['host'], $this->options['dbname'], $this->options['user'], $this->options['pass']);
      $this->connection->charset = $this->options['charset'];

      if ($this->options['port']) {
        $this->connection->port = $this->options['port'];
      }

      if ($this->options['socket']) {
        $this->connection->socket = $this->options['socket'];
      }

      $this->sqlConn = $this->connection->connect($this->options['driverOptions']);

      /* if ( !empty($this->options['sqlModes']) ){
        $this->setQuery('SET @@SESSION.sql_mode = \'' . implode(',', $this->options['sqlModes']) . '\';');
        $this->execute();
        
        $this->options['sqlModes'] = explode(',', $this->setQuery('SELECT @@SESSION.sql_mode;')->loadResult());
        // \mysqli_query($this->sqlConn, 'SET @@SESSION.sql_mode = \'' . implode(',', $this->options['sqlModes']) . '\';');
      } */

      // $this->mariadb = stripos($this->connection->server_info, 'mariadb') !== false;
      // $this->utf8mb4 = $this->serverClaimsUtf8mb4Support();
    }
  }

  public function connected(): bool
  {
    if ($this->sqlConn) {
      return $this->sqlConn->ping();
    }

    return false;
  }

  public function disconnect()
  {
    $this->sqlConn->close();

    parent::disconnect();
  }

  public function insertid(): int
  {
    $this->connect();

    return $this->sqlConn->insert_id;
  }

  public function escape(string $text, bool $extra = false): string
  {
    if (\is_int($text)) {
      return $text;
    }

    if (\is_float($text)) {
      return str_replace(',', '.', (string)$text);
    }

    $this->connect();

    $result = $this->sqlConn->real_escape_string((string)$text);

    if (true === $extra) {
      $result = \addcslashes($result, '%_');
    }

    return $result;
  }

  public function prepareStatement(string $query): MysqliStatement
  {
    return new MysqliStatement($this->sqlConn, $query);
  }

  private function serverClaimsUtf8mb4Support()
  {
    $client_version = \mysqli_get_client_info();

    if (true === $this->mariadb) {
      // MariaDB: Strip off any leading '5.5.5-', if present
      $server_version = preg_replace('/^5\.5\.5-/', '', $this->sqlConn->server_info);
    } else {
      $server_version = $this->sqlConn->server_info;
    }

    if (\version_compare($server_version, '5.5.3', '<')) {
      return false;
    }

    if (true === $this->mariadb && \version_compare($server_version, '10.0.0', '<')) {
      return false;
    }

    if (strpos($client_version, 'mysqlnd') !== false) {
      $client_version = preg_replace('/^\D+([\d.]+).*/', '$1', $client_version);
      return \version_compare($client_version, '5.0.9', '>=');
    }

    return \version_compare($client_version, '5.5.3', '>=');
  }
}
