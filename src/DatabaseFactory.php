<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\Contract\DatabaseInterface;
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
        // Allow a DSN string as a first-class config parameter.
        // Explicit options override values parsed from the DSN.
        if (!empty($options['dsn'])) {
            $parsed = static::parseDsn($options['dsn']);
            unset($options['dsn']);
            $options = array_merge($parsed, $options);
        }

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
        // Thin wrapper around create(); explicit $options still override the DSN.
        return static::create(array_merge($options, ['dsn' => $dsn]));
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
     * Doctrine-style DSN: driver://user:pass@host:port/dbname?charset=utf8mb4
     * Supported driver schemes (and aliases):
     * - mysql, pdo-mysql, pdo_mysql, mysql2  -> mysql
     * - pgsql, pdo-pgsql, postgresql, postgres -> pgsql
     * - mysqli, mariadb (native driver, kept as-is)
     * - sqlite, sqlite3, pdo-sqlite          -> sqlite
     *
     * Credentials may be percent-encoded (e.g. p%40ss for "p@ss"); they are
     * URL-decoded here, matching Doctrine's behaviour.
     *
     * @param   string  $dsn  DSN string
     * @return  array   Configuration array
     * @throws  DatabaseException  If DSN format is invalid
     */
    public static function parseDsn(string $dsn): array
    {
        // Handle SQLite (file path / :memory:) — parse_url is unreliable for these.
        // Covers sqlite://, sqlite:///, sqlite3://, pdo-sqlite://, pdo_sqlite://
        if (preg_match('#^(?:pdo[-_])?sqlite[0-9]?://(.+)$#i', $dsn, $matches)) {
            $dbname = $matches[1];

            // Normalize the various in-memory spellings.
            if ($dbname === ':memory:' || $dbname === '/:memory:') {
                $dbname = ':memory:';
            }

            return [
                'driver' => 'sqlite',
                'dbname' => $dbname,
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
            'driver' => static::normalizeDriver($parts['scheme']),
            'host'   => $parts['host'] ?? 'localhost',
            'user'   => isset($parts['user']) ? rawurldecode($parts['user']) : '',
            'pass'   => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
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

    /**
     * Normalize a DSN driver scheme to a canonical driver name.
     */
    protected static function normalizeDriver(string $scheme): string
    {
        return match (strtolower($scheme)) {
            'pdo-mysql', 'pdo_mysql', 'mysql2' => 'mysql',
            'pdo-pgsql', 'pdo_pgsql', 'postgresql', 'postgres' => 'pgsql',
            default => strtolower($scheme),
        };
    }
}
