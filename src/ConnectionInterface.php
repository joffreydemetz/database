<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;

/**
 * Database Connection Interface
 * 
 * Defines the contract for database connection implementations
 * Supports: PDO (MySQL, MariaDB, PostgreSQL, SQLite) and MySQLi (MySQL, MariaDB)
 */
interface ConnectionInterface
{
  /**
   * Establish a connection to the database
   * 
   * @param   array  $attrs  Driver-specific connection attributes
   * @return  mixed  The underlying connection resource (PDO or mysqli)
   * @throws  DatabaseException  If connection fails
   */
  public function connect(array $attrs = []);

  /**
   * Check if the database driver is available
   * 
   * @throws  DatabaseException  If driver is not available
   */
  public function checkIfDriverAvailable();
}
