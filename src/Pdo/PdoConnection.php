<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Connection;
use JDZ\Database\Exception\DatabaseException;

class PdoConnection extends Connection
{
  /**
   * @var    \PDO|null
   */
  protected mixed $connection = null;

  public string $driver = 'mysql';

  public function connect(array $attrs = [])
  {
    if ($this->connection) {
      return $this->connection;
    }

    if ($this->driver === 'sqlite') {
      $dsn = 'sqlite:' . $this->dbname;
      $user = null;
      $pass = null;
    } else {
      $dsn = $this->driver . ':dbname=' . $this->dbname . ';host=' . $this->host;

      if ($this->port) {
        $dsn .= ';port=' . $this->port;
      }

      if ($this->socket) {
        $dsn .= ';unix_socket=' . $this->socket;
      }

      if ($this->charset && $this->driver === 'mysql') {
        $dsn .= ';charset=' . $this->charset;
      }

      $user = $this->user;
      $pass = $this->pass;
    }

    try {
      $this->connection = new \PDO($dsn, $user, $pass, $attrs);
      $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

      if ($this->driver === 'sqlite') {
        $this->connection->exec('PRAGMA foreign_keys = ON');
      }
    } catch (\PDOException $e) {
      throw new DatabaseException('Could not connect to PDO: ' . $e->getMessage(), $e->getCode(), $e);
    }

    return $this->connection;
  }

  public function checkIfDriverAvailable()
  {
    if (!\defined('\\PDO::ATTR_DRIVER_NAME')) {
      throw new DatabaseException('PDO connection is not available');
    }
  }
}
