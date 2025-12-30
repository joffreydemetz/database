<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Connection;
use JDZ\Database\Exception\DatabaseException;

/**
 * SQLite PDO Connection
 * 
 * SQLite uses file-based databases, so connection is different from MySQL/PostgreSQL
 */
class PdoSqliteConnection extends Connection
{
    /**
     * @var    \PDO|null
     */
    protected mixed $connection = null;

    public string $driver = 'sqlite';

    /**
     * Constructor for SQLite connection
     * 
     * @param   string  $dbPath  Path to SQLite database file (or ':memory:' for in-memory)
     * @param   string  $user    Not used for SQLite (kept for interface compatibility)
     * @param   string  $pass    Not used for SQLite (kept for interface compatibility)
     */
    public function __construct(string $dbPath, string $user = '', string $pass = '')
    {
        $this->checkIfDriverAvailable();

        $this->dbname = $dbPath;
        $this->user = $user;
        $this->pass = $pass;
        $this->host = ''; // Not used for SQLite

        if ('' === $this->dbname) {
            throw new DatabaseException('Missing database file path');
        }

        // Check if file exists for file-based databases (not :memory:)
        if ($this->dbname !== ':memory:' && !file_exists($this->dbname)) {
            // Create directory if it doesn't exist
            $dir = dirname($this->dbname);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new DatabaseException("Cannot create directory: {$dir}");
                }
            }
        }
    }

    public function connect(array $attrs = [])
    {
        if ($this->connection) {
            return $this->connection;
        }

        $dsn = 'sqlite:' . $this->dbname;

        try {
            $this->connection = new \PDO($dsn, null, null, $attrs);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new DatabaseException('Could not connect to SQLite: ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection;
    }

    public function checkIfDriverAvailable()
    {
        if (!\defined('\\PDO::ATTR_DRIVER_NAME')) {
            throw new DatabaseException('PDO connection is not available');
        }

        $drivers = \PDO::getAvailableDrivers();
        if (!in_array('sqlite', $drivers)) {
            throw new DatabaseException('PDO SQLite driver is not available');
        }
    }
}
