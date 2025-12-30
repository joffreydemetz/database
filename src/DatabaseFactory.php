<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\DatabaseInterface;
use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Pdo\PdoPostgresqlDatabase;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\UnionQuery;
use JDZ\Database\Query\InsertQuery;
use JDZ\Database\Query\UpdateQuery;
use JDZ\Database\Query\DeleteQuery;

/**
 * Database Factory
 * 
 * Factory class for creating database driver instances based on configuration
 */
class DatabaseFactory
{
    /**
     * Create a database driver instance
     * 
     * @param   array   $options  Database configuration options
     * @return  DatabaseInterface  Database driver instance
     * @throws  DatabaseException  If driver is invalid or not supported
     * 
     * Configuration options:
     * - driver: 'mysql', 'mysqli', 'mariadb', 'pgsql', 'postgresql', 'sqlite'
     * - host: Database host (not used for SQLite)
     * - dbname: Database name (or file path for SQLite)
     * - user: Database user
     * - pass: Database password
     * - charset: Character set (default: utf8mb4 for MySQL, utf8 for others)
     * - port: Database port (optional)
     * - socket: Unix socket (optional)
     * - tblprefix: Table prefix (default: '')
     * - driverOptions: Driver-specific options (optional)
     * - sqlModes: SQL modes for MySQL/MariaDB (optional)
     */
    public static function create(array $options): DatabaseInterface
    {
        if (empty($options['driver'])) {
            throw new DatabaseException('Database driver not specified in options');
        }

        $driver = strtolower($options['driver']);

        // Set default charset if not specified
        if (empty($options['charset'])) {
            $options['charset'] = in_array($driver, ['mysql', 'mysqli', 'mariadb']) ? 'utf8mb4' : 'utf8';
        }

        // Set default table prefix
        if (!isset($options['tblprefix'])) {
            $options['tblprefix'] = '';
        }

        // Route to appropriate driver
        return match ($driver) {
            'mysqli', 'mariadb' => new MysqliDatabase($options),
            'mysql'             => new PdoDatabase($options),
            'pgsql', 'postgresql', 'postgres' => new PdoPostgresqlDatabase($options),
            'sqlite', 'sqlite3' => new PdoSqliteDatabase($options),
            default => throw new DatabaseException("Unsupported database driver: {$driver}")
        };
    }

    /**
     * Create a database driver from a DSN string
     * 
     * @param   string  $dsn      DSN string (e.g., 'mysql://user:pass@host/dbname')
     * @param   array   $options  Additional options (table prefix, etc.)
     * @return  DatabaseInterface  Database driver instance
     * @throws  DatabaseException  If DSN is invalid
     * 
     * DSN format: driver://user:pass@host:port/dbname
     * Examples:
     * - mysql://root:password@localhost/mydb
     * - mysqli://root:password@localhost:3306/mydb
     * - pgsql://user:pass@localhost/mydb
     * - sqlite:///path/to/database.db
     * - sqlite://:memory:
     */
    public static function createFromDsn(string $dsn, array $options = []): DatabaseInterface
    {
        $parsed = static::parseDsn($dsn);

        // Merge parsed DSN with additional options
        $config = array_merge($parsed, $options);

        return static::create($config);
    }

    /**
     * Get a list of available database drivers
     * 
     * @return  array  List of available driver names
     */
    public static function getAvailableDrivers(): array
    {
        $drivers = [];

        // Check for MySQLi
        if (function_exists('mysqli_connect')) {
            $drivers[] = 'mysqli';
            $drivers[] = 'mariadb';
        }

        // Check for PDO drivers
        if (class_exists('PDO')) {
            $pdoDrivers = \PDO::getAvailableDrivers();

            if (in_array('mysql', $pdoDrivers)) {
                $drivers[] = 'mysql';
            }

            if (in_array('pgsql', $pdoDrivers)) {
                $drivers[] = 'pgsql';
                $drivers[] = 'postgresql';
            }

            if (in_array('sqlite', $pdoDrivers)) {
                $drivers[] = 'sqlite';
            }
        }

        return array_unique($drivers);
    }

    /**
     * Check if a specific driver is available
     * 
     * @param   string  $driver  Driver name
     * @return  bool    True if driver is available
     */
    public static function isDriverAvailable(string $driver): bool
    {
        return in_array(strtolower($driver), static::getAvailableDrivers());
    }

    /**
     * Create a new SELECT query builder
     */
    public static function createSelectQuery(): SelectQuery
    {
        return new SelectQuery();
    }

    /**
     * Create a new UNION query builder
     */
    public static function createUnionQuery(): UnionQuery
    {
        return new UnionQuery();
    }

    /**
     * Create a new INSERT query builder
     */
    public static function createInsertQuery(): InsertQuery
    {
        return new InsertQuery();
    }

    /**
     * Create a new UPDATE query builder
     */
    public static function createUpdateQuery(): UpdateQuery
    {
        return new UpdateQuery();
    }

    /**
     * Create a new DELETE query builder
     */
    public static function createDeleteQuery(): DeleteQuery
    {
        return new DeleteQuery();
    }

    /**
     * Parse a DSN string into configuration array
     * 
     * @param   string  $dsn  DSN string
     * @return  array   Configuration array
     * @throws  DatabaseException  If DSN format is invalid
     */
    protected static function parseDsn(string $dsn): array
    {
        // Handle SQLite special cases
        if (preg_match('#^sqlite://(.+)$#', $dsn, $matches)) {
            return [
                'driver' => 'sqlite',
                'dbname' => $matches[1],
                'host'   => '',
                'user'   => '',
                'pass'   => '',
            ];
        }

        $parts = parse_url($dsn);

        if ($parts === false || empty($parts['scheme'])) {
            throw new DatabaseException('Invalid DSN format');
        }

        $config = [
            'driver' => $parts['scheme'],
            'host'   => $parts['host'] ?? 'localhost',
            'user'   => $parts['user'] ?? '',
            'pass'   => $parts['pass'] ?? '',
            'dbname' => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
        ];

        if (isset($parts['port'])) {
            $config['port'] = (int)$parts['port'];
        }

        // Parse query string for additional options
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $config = array_merge($config, $queryParams);
        }

        if (empty($config['dbname'])) {
            throw new DatabaseException('Database name not specified in DSN');
        }

        return $config;
    }
}
