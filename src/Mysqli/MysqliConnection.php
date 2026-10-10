<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Mysqli;

use JDZ\Database\Connection;
use JDZ\Database\Exception\DatabaseException;

class MysqliConnection extends Connection
{
  /**
   * @var    \mysqli|null
   */
  protected mixed $connection = null;

  public function connect(array $attrs = [])
  {
    if ($this->connection) {
      return $this->connection;
    }

    $connection = \mysqli_init();

    if (!$connection) {
      throw new DatabaseException('mysqli_init() error');
    }

    // mysqli throws by default since PHP 8.1, and returns false when reporting is off:
    // both end as a DatabaseException, and the handle is kept only once connected
    try {
      $connection->options(\MYSQLI_SET_CHARSET_NAME, $this->charset);

      if (!@$connection->real_connect($this->host, $this->user, $this->pass, $this->dbname, $this->port, $this->socket)) {
        throw new DatabaseException('Could not connect to MySQL: ' . $connection->connect_error, $connection->connect_errno);
      }
    } catch (\mysqli_sql_exception $e) {
      throw new DatabaseException('Could not connect to MySQL: ' . $e->getMessage(), $e->getCode(), $e);
    }

    return $this->connection = $connection;
  }

  public function checkIfDriverAvailable()
  {
    if (!function_exists('mysqli_connect')) {
      throw new DatabaseException('MySQLi connection is not available');
    }
  }
}
