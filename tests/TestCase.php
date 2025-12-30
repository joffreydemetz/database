<?php

namespace JDZ\Database\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get MySQL connection options for testing
     */
    protected function getMysqlOptions(): array
    {
        return [
            'driver'    => 'mysql',
            'host'      => getenv('JDZ_MYSQL_HOST') ?? 'localhost',
            'port'      => (int)(getenv('JDZ_MYSQL_PORT') ?? 3306),
            'dbname'    => getenv('JDZ_MYSQL_DATABASE') ?? 'test_jdz_database',
            'user'      => getenv('JDZ_MYSQL_USERNAME') ?? 'root',
            'pass'      => getenv('JDZ_MYSQL_PASSWORD') ?? 'password',
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
            'host'      => getenv('JDZ_PGSQL_HOST') ?? 'localhost',
            'port'      => (int)(getenv('JDZ_PGSQL_PORT') ?? 5432),
            'dbname'    => getenv('JDZ_PGSQL_DATABASE') ?? 'test_jdz_database',
            'user'      => getenv('JDZ_PGSQL_USERNAME') ?? 'postgres',
            'pass'      => getenv('JDZ_PGSQL_PASSWORD') ?? 'password',
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
            'dbname'    => getenv('JDZ_SQLITE_PATH') ?? ':memory:',
            'tblprefix' => 'test_'
        ];
    }

    /**
     * Get MySQLi connection options for testing
     */
    protected function getMysqliOptions(): array
    {
        return [
            'host'      => getenv('JDZ_MYSQL_HOST') ?? 'localhost',
            'port'      => (int)(getenv('JDZ_MYSQL_PORT') ?? 3306),
            'dbname'    => getenv('JDZ_MYSQL_DATABASE') ?? 'test_jdz_database',
            'user'      => getenv('JDZ_MYSQL_USERNAME') ?? 'root',
            'pass'      => getenv('JDZ_MYSQL_PASSWORD') ?? 'password',
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
