<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Mysqli;

use JDZ\Database\Connection;
use JDZ\Database\Exception\DatabaseException;

/**
 * Driver
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
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

    // \mysqli_report(\MYSQLI_REPORT_ERROR|\MYSQLI_REPORT_STRICT);

    $this->connection = \mysqli_init();

    if (!$this->connection) {
      $this->connection = null;

      throw new DatabaseException('mysqli_init() error');
    }

    $this->connection->options(\MYSQLI_SET_CHARSET_NAME, $this->charset);

    if (!$this->connection->real_connect($this->host, $this->user, $this->pass, null, $this->port, $this->socket)) {
      throw new DatabaseException('Could not connect to MySQL: ' . $this->connection->connect_error);
    }

    $this->connection->select_db($this->dbname);

    // $this->connection->set_charset($this->charset);

    return $this->connection;
  }

  public function checkIfDriverAvailable()
  {
    if (!function_exists('mysqli_connect')) {
      throw new DatabaseException('MySQLi connection is not available');
    }
  }
}
