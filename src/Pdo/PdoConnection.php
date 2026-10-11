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

  /**
   * The PDO DSN this connection opens. A PostgreSQL socket is libpq's
   * host=/path (2.3.0, D24 — unix_socket= is MySQL's keyword, pgsql ignored it).
   */
  public function dsn(): string
  {
    if ($this->driver === 'sqlite') {
      return 'sqlite:' . $this->dbname;
    }

    $host = ($this->socket && $this->driver === 'pgsql') ? $this->socket : $this->host;
    $dsn = $this->driver . ':dbname=' . $this->dbname . ';host=' . $host;

    if ($this->port) {
      $dsn .= ';port=' . $this->port;
    }

    if ($this->socket && $this->driver !== 'pgsql') {
      $dsn .= ';unix_socket=' . $this->socket;
    }

    if ($this->charset && $this->driver === 'mysql') {
      $dsn .= ';charset=' . $this->charset;
    }

    return $dsn;
  }

  public function connect(array $attrs = [])
  {
    if ($this->connection) {
      return $this->connection;
    }

    $dsn = $this->dsn();

    if ($this->driver === 'sqlite') {
      $user = null;
      $pass = null;
    } else {
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
