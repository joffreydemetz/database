<?php

namespace JDZ\Database\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The integration tests' connection settings come from the environment
     * (JDZ_MYSQL_*, JDZ_PGSQL_*, JDZ_SQLITE_PATH); the defaults below match a
     * local MySQL with a test_jdz_database schema and root/root.
     *
     * getenv() returns false, not null, for an unset variable, so `?? default`
     * never falls back. An intentionally empty value (no password) stays empty.
     */
    protected function env(string $name, string $default): string
    {
        $value = getenv($name);

        return false === $value ? $default : $value;
    }

    /**
     * Runs $fn and returns what it threw. Fails unless that is exactly $class
     * (not a subclass, unlike expectException()) with exactly $message (not a
     * substring, unlike expectExceptionMessage()).
     */
    protected function assertThrowsExactly(string $class, string $message, callable $fn): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->assertSame($class, $e::class, 'exception class');
            $this->assertSame($message, $e->getMessage(), 'exception message');

            return $e;
        }

        $this->fail("Nothing was thrown, expected {$class}: {$message}");
    }

    /**
     * Get MySQL connection options for testing
     */
    protected function getMysqlOptions(): array
    {
        return [
            'driver'    => 'mysql',
            'host'      => $this->env('JDZ_MYSQL_HOST', '127.0.0.1'),
            'port'      => (int)$this->env('JDZ_MYSQL_PORT', '3306'),
            'dbname'    => $this->env('JDZ_MYSQL_DATABASE', 'test_jdz_database'),
            'user'      => $this->env('JDZ_MYSQL_USERNAME', 'root'),
            'pass'      => $this->env('JDZ_MYSQL_PASSWORD', 'root'),
            'charset'   => 'utf8mb4',
            'tblprefix' => 'test_'
        ];
    }

    /**
     * Get PostgreSQL connection options for testing
     */
    protected function getPostgresqlOptions(): array
    {
        return [
            'driver'    => 'pgsql',
            'host'      => $this->env('JDZ_PGSQL_HOST', '127.0.0.1'),
            'port'      => (int)$this->env('JDZ_PGSQL_PORT', '5432'),
            'dbname'    => $this->env('JDZ_PGSQL_DATABASE', 'test_jdz_database'),
            'user'      => $this->env('JDZ_PGSQL_USERNAME', 'postgres'),
            'pass'      => $this->env('JDZ_PGSQL_PASSWORD', 'password'),
            'tblprefix' => 'test_'
        ];
    }

    /**
     * Get SQLite connection options for testing
     */
    protected function getSqliteOptions(): array
    {
        return [
            'driver'    => 'sqlite',
            'dbname'    => $this->env('JDZ_SQLITE_PATH', ':memory:'),
            'tblprefix' => 'test_'
        ];
    }

    /**
     * Get MySQLi connection options for testing
     */
    protected function getMysqliOptions(): array
    {
        return [
            'host'      => $this->env('JDZ_MYSQL_HOST', '127.0.0.1'),
            'port'      => (int)$this->env('JDZ_MYSQL_PORT', '3306'),
            'dbname'    => $this->env('JDZ_MYSQL_DATABASE', 'test_jdz_database'),
            'user'      => $this->env('JDZ_MYSQL_USERNAME', 'root'),
            'pass'      => $this->env('JDZ_MYSQL_PASSWORD', 'root'),
            'charset'   => 'utf8mb4',
            'tblprefix' => 'test_'
        ];
    }

    /**
     * Check if MySQL PDO driver is available
     */
    protected function isMysqlAvailable(): bool
    {
        return extension_loaded('pdo_mysql');
    }

    /**
     * Check if PostgreSQL PDO driver is available
     */
    protected function isPostgresqlAvailable(): bool
    {
        return extension_loaded('pdo_pgsql');
    }

    /**
     * Check if SQLite PDO driver is available
     */
    protected function isSqliteAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    /**
     * Check if MySQLi driver is available
     */
    protected function isMysqliAvailable(): bool
    {
        return extension_loaded('mysqli');
    }

    /**
     * Create a test table SQL for MySQL/MariaDB
     */
    protected function getMysqlTestTableSql(string $tableName = 'test_users'): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `age` INT DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
    }

    /**
     * Create a test table SQL for PostgreSQL
     */
    protected function getPostgresqlTestTableSql(string $tableName = 'test_users'): string
    {
        return "
            CREATE TABLE IF NOT EXISTS \"{$tableName}\" (
                \"id\" SERIAL PRIMARY KEY,
                \"name\" VARCHAR(255) NOT NULL,
                \"email\" VARCHAR(255) NOT NULL,
                \"age\" INT DEFAULT NULL,
                \"created_at\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
    }

    /**
     * Create a test table SQL for SQLite
     */
    protected function getSqliteTestTableSql(string $tableName = 'test_users'): string
    {
        return "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `name` TEXT NOT NULL,
                `email` TEXT NOT NULL,
                `age` INTEGER DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
    }

    /**
     * Drop test table SQL
     */
    protected function getDropTableSql(string $tableName = 'test_users'): string
    {
        return "DROP TABLE IF EXISTS `{$tableName}`";
    }
}
