<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Pdo\PdoPostgresqlDatabase;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Tests\TestCase;

class DatabaseFactoryTest extends TestCase
{
    public function testCreateMysqlPdoDriver(): void
    {
        if (!$this->isMysqlAvailable()) {
            $this->markTestSkipped('MySQL PDO driver not available');
        }

        $db = DatabaseFactory::create([
            'driver' => 'mysql',
            'host'   => 'localhost',
            'dbname' => 'test',
            'user'   => 'root',
            'pass'   => ''
        ]);

        $this->assertInstanceOf(PdoDatabase::class, $db);
    }

    public function testCreatePostgresqlDriver(): void
    {
        if (!$this->isPostgresqlAvailable()) {
            $this->markTestSkipped('PostgreSQL PDO driver not available');
        }

        $db = DatabaseFactory::create([
            'driver' => 'pgsql',
            'host'   => 'localhost',
            'dbname' => 'test',
            'user'   => 'postgres',
            'pass'   => ''
        ]);

        $this->assertInstanceOf(PdoPostgresqlDatabase::class, $db);
    }

    public function testCreateSqliteDriver(): void
    {
        if (!$this->isSqliteAvailable()) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $db = DatabaseFactory::create([
            'driver' => 'sqlite',
            'dbname' => ':memory:'
        ]);

        $this->assertInstanceOf(PdoSqliteDatabase::class, $db);
    }

    public function testCreateMysqliDriver(): void
    {
        if (!$this->isMysqliAvailable()) {
            $this->markTestSkipped('MySQLi driver not available');
        }

        $db = DatabaseFactory::create([
            'driver' => 'mysqli',
            'host'   => 'localhost',
            'dbname' => 'test',
            'user'   => 'root',
            'pass'   => ''
        ]);

        $this->assertInstanceOf(MysqliDatabase::class, $db);
    }

    public function testCreateFromDsnMysql(): void
    {
        if (!$this->isMysqlAvailable()) {
            $this->markTestSkipped('MySQL PDO driver not available');
        }

        $db = DatabaseFactory::createFromDsn('mysql://root:password@localhost/testdb');

        $this->assertInstanceOf(PdoDatabase::class, $db);
    }

    public function testCreateFromDsnPostgresql(): void
    {
        if (!$this->isPostgresqlAvailable()) {
            $this->markTestSkipped('PostgreSQL PDO driver not available');
        }

        $db = DatabaseFactory::createFromDsn('pgsql://postgres:password@localhost/testdb');

        $this->assertInstanceOf(PdoPostgresqlDatabase::class, $db);
    }

    public function testCreateFromDsnSqlite(): void
    {
        if (!$this->isSqliteAvailable()) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $db = DatabaseFactory::createFromDsn('sqlite:///tmp/test.db');

        $this->assertInstanceOf(PdoSqliteDatabase::class, $db);
    }

    public function testCreateFromDsnMysqli(): void
    {
        if (!$this->isMysqliAvailable()) {
            $this->markTestSkipped('MySQLi driver not available');
        }

        $db = DatabaseFactory::createFromDsn('mysqli://root:password@localhost/testdb');

        $this->assertInstanceOf(MysqliDatabase::class, $db);
    }

    public function testGetAvailableDrivers(): void
    {
        $drivers = DatabaseFactory::getAvailableDrivers();

        $this->assertIsArray($drivers);
        $this->assertNotEmpty($drivers);
    }

    public function testIsDriverAvailable(): void
    {
        // At least one driver should be available in any PHP installation
        $hasDriver = DatabaseFactory::isDriverAvailable('mysql') ||
            DatabaseFactory::isDriverAvailable('pgsql') ||
            DatabaseFactory::isDriverAvailable('sqlite') ||
            DatabaseFactory::isDriverAvailable('mysqli');

        $this->assertTrue($hasDriver, 'At least one database driver should be available');
    }

    public function testCreateThrowsExceptionForUnsupportedDriver(): void
    {
        $this->expectException(\JDZ\Database\Exception\DatabaseException::class);
        $this->expectExceptionMessage('Unsupported database driver');

        DatabaseFactory::create([
            'driver' => 'oracle',  // Unsupported driver
            'host'   => 'localhost',
            'dbname' => 'test',
            'user'   => 'root',
            'pass'   => ''
        ]);
    }
}
