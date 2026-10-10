<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Pdo\PdoPostgresqlDatabase;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * DatabaseFactory turns a driver name or a DSN into a driver instance and fills
 * in the defaults. Nothing here connects (the drivers connect lazily): what a
 * live MySQL server receives, such as the default charset, is checked in
 * Integration\MysqlConnectionTest.
 */
class DatabaseFactoryTest extends TestCase
{
    // ---------------------------------------------------------------- create()

    public static function driverAliases(): array
    {
        return [
            'mysql is PDO'              => ['mysql', PdoDatabase::class],
            'mysqli'                    => ['mysqli', MysqliDatabase::class],
            'mariadb is MySQLi'         => ['mariadb', MysqliDatabase::class],
            'pgsql'                     => ['pgsql', PdoPostgresqlDatabase::class],
            'postgresql'                => ['postgresql', PdoPostgresqlDatabase::class],
            'postgres'                  => ['postgres', PdoPostgresqlDatabase::class],
            'sqlite'                    => ['sqlite', PdoSqliteDatabase::class],
            'sqlite3'                   => ['sqlite3', PdoSqliteDatabase::class],
            'case-insensitive (MySQLi)' => ['MariaDB', MysqliDatabase::class],
            'case-insensitive (SQLite)' => ['SQLite3', PdoSqliteDatabase::class],
        ];
    }

    #[DataProvider('driverAliases')]
    public function testCreateRoutesEachDriverAlias(string $driver, string $class): void
    {
        $db = DatabaseFactory::create(['driver' => $driver, 'dbname' => 'app']);

        $this->assertSame($class, $db::class);
    }

    public function testCreateDefaultsTheTablePrefixToEmpty(): void
    {
        $this->assertSame('', DatabaseFactory::create(['driver' => 'mysql', 'dbname' => 'app'])->tablePrefix);
        $this->assertSame('app_', DatabaseFactory::create(['driver' => 'mysql', 'dbname' => 'app', 'tblprefix' => 'app_'])->tablePrefix);
    }

    public function testCreateReadsADsnOption(): void
    {
        $db = DatabaseFactory::create(['dsn' => 'sqlite:///var/data/app.db']);

        $this->assertSame(PdoSqliteDatabase::class, $db::class);
        $this->assertSame('/var/data/app.db', $db->getDatabaseName());
        $this->assertSame('', $db->tablePrefix);

        $db = DatabaseFactory::create(['dsn' => 'postgres://app@db.example/app?tblprefix=pg_']);

        $this->assertSame(PdoPostgresqlDatabase::class, $db::class);
        $this->assertSame('pg_', $db->tablePrefix, 'from the DSN query');
    }

    public function testExplicitOptionsOverrideTheDsnOption(): void
    {
        $db = DatabaseFactory::create(['dsn' => 'mysql://root@db.example/app?tblprefix=dsn_', 'tblprefix' => 'opt_']);
        $this->assertSame('opt_', $db->tablePrefix);

        $db = DatabaseFactory::create(['dsn' => 'sqlite:///var/data/app.db', 'dbname' => ':memory:']);
        $this->assertSame(':memory:', $db->getDatabaseName());

        $db = DatabaseFactory::create(['dsn' => 'mysql://root@db.example/app', 'driver' => 'sqlite']);
        $this->assertSame(PdoSqliteDatabase::class, $db::class, 'even the driver');
        $this->assertSame('app', $db->getDatabaseName());
    }

    public function testAnEmptyDsnOptionIsIgnored(): void
    {
        $db = DatabaseFactory::create(['dsn' => '', 'driver' => 'sqlite', 'dbname' => ':memory:']);

        $this->assertSame(':memory:', $db->getDatabaseName());
    }

    public static function invalidOptions(): array
    {
        return [
            'no driver'                 => [['dbname' => 'app'], 'Database driver not specified in options'],
            'empty driver'              => [['driver' => '', 'dbname' => 'app'], 'Database driver not specified in options'],
            'unsupported driver'        => [['driver' => 'Oracle', 'dbname' => 'app'], 'Unsupported database driver: oracle'],
            'unsupported DSN scheme'    => [['dsn' => 'OCI://scott@db.example/app'], 'Unsupported database driver: oci'],
            'invalid DSN option'        => [['dsn' => 'db.example/app'], 'Invalid DSN format'],
            'DSN option without dbname' => [['dsn' => 'mysql://root@db.example'], 'Database name not specified in DSN'],
        ];
    }

    #[DataProvider('invalidOptions')]
    public function testCreateRejectsAnInvalidConfiguration(array $options, string $message): void
    {
        $this->assertThrowsExactly(DatabaseException::class, $message, fn() => DatabaseFactory::create($options));
    }

    // --------------------------------------------------------- createFromDsn()

    public static function dsnSchemes(): array
    {
        return [
            'mysql'     => ['mysql://root@db.example/app', PdoDatabase::class],
            'mysql2'    => ['mysql2://root@db.example/app', PdoDatabase::class],
            'mysqli'    => ['mysqli://root@db.example/app', MysqliDatabase::class],
            'mariadb'   => ['mariadb://root@db.example/app', MysqliDatabase::class],
            'pdo-pgsql' => ['pdo-pgsql://app@db.example/app', PdoPostgresqlDatabase::class],
            'postgres'  => ['postgres://app@db.example/app', PdoPostgresqlDatabase::class],
            'sqlite3'   => ['sqlite3:///var/data/app.db', PdoSqliteDatabase::class],
        ];
    }

    #[DataProvider('dsnSchemes')]
    public function testCreateFromDsnRoutesTheScheme(string $dsn, string $class): void
    {
        $this->assertSame($class, DatabaseFactory::createFromDsn($dsn)::class);
    }

    public function testCreateFromDsnOptionsOverrideTheDsn(): void
    {
        $this->assertSame('dsn_', DatabaseFactory::createFromDsn('mysql://root@db.example/app?tblprefix=dsn_')->tablePrefix);
        $this->assertSame('opt_', DatabaseFactory::createFromDsn('mysql://root@db.example/app?tblprefix=dsn_', ['tblprefix' => 'opt_'])->tablePrefix);
        $this->assertSame(':memory:', DatabaseFactory::createFromDsn('sqlite:///var/data/app.db', ['dbname' => ':memory:'])->getDatabaseName());
    }

    public function testCreateFromDsnRejectsWhatCreateRejects(): void
    {
        $this->assertThrowsExactly(DatabaseException::class, 'Invalid DSN format', fn() => DatabaseFactory::createFromDsn('db.example/app'));
        $this->assertThrowsExactly(DatabaseException::class, 'Database name not specified in DSN', fn() => DatabaseFactory::createFromDsn('mysql://root@db.example'));
        $this->assertThrowsExactly(DatabaseException::class, 'Unsupported database driver: oci', fn() => DatabaseFactory::createFromDsn('oci://scott@db.example/app'));
    }

    // -------------------------------------------------------------- parseDsn()

    public static function dsns(): array
    {
        $mysql  = ['driver' => 'mysql', 'host' => 'db.example', 'user' => 'root', 'pass' => 'secret', 'dbname' => 'app', 'port' => 3307];
        $pgsql  = ['driver' => 'pgsql'] + $mysql;
        $bare   = ['driver' => 'mysql', 'host' => 'db.example', 'user' => 'root', 'pass' => '', 'dbname' => 'app'];
        $sqlite = static fn(string $path): array => ['driver' => 'sqlite', 'dbname' => $path, 'host' => '', 'user' => '', 'pass' => ''];

        return [
            // schemes
            'mysql'                       => ['mysql://root:secret@db.example:3307/app', $mysql],
            'pdo-mysql'                   => ['pdo-mysql://root:secret@db.example:3307/app', $mysql],
            'pdo_mysql'                   => ['pdo_mysql://root:secret@db.example:3307/app', $mysql],
            'mysql2'                    => ['mysql2://root:secret@db.example:3307/app', $mysql],
            'scheme is case-insensitive'  => ['MySQL://root:secret@db.example:3307/app', $mysql],
            'pgsql'                       => ['pgsql://root:secret@db.example:3307/app', $pgsql],
            'pdo-pgsql'                   => ['pdo-pgsql://root:secret@db.example:3307/app', $pgsql],
            'pdo_pgsql'                   => ['pdo_pgsql://root:secret@db.example:3307/app', $pgsql],
            'postgresql'                  => ['postgresql://root:secret@db.example:3307/app', $pgsql],
            'postgres'                    => ['postgres://root:secret@db.example:3307/app', $pgsql],
            'mysqli is kept'              => ['mysqli://root:secret@db.example:3307/app', ['driver' => 'mysqli'] + $mysql],
            'mariadb is kept'             => ['mariadb://root:secret@db.example:3307/app', ['driver' => 'mariadb'] + $mysql],
            // credentials and defaults
            'no credentials, no port'     => ['mysql://db.example/app', ['user' => ''] + $bare],
            'user without password'       => ['mysql://root@db.example/app', $bare],
            'credentials are url-decoded' => ['mysql://us%3Aer:p%40ss%2Fw0rd@db.example/app', ['user' => 'us:er', 'pass' => 'p@ss/w0rd'] + $bare],
            'a plus sign is not a space'  => ['mysql://root:p+ss@db.example/app', ['pass' => 'p+ss'] + $bare],
            // query string
            'query options are added'     => ['mysql://root@db.example/app?charset=latin1&tblprefix=app_', $bare + ['charset' => 'latin1', 'tblprefix' => 'app_']],
            'the query overrides the URL' => ['mysql://root@db.example/app?host=replica.example&dbname=reports', ['host' => 'replica.example', 'dbname' => 'reports'] + $bare],
            'the dbname from the query'   => ['mysql://root@db.example/?dbname=app', $bare],
            // SQLite: a path, not a URL
            'sqlite absolute path'        => ['sqlite:///var/data/app.db', $sqlite('/var/data/app.db')],
            'sqlite relative path'        => ['sqlite://data/app.db', $sqlite('data/app.db')],
            'sqlite Windows path'         => ['sqlite://C:/data/app.db', $sqlite('C:/data/app.db')],
            'sqlite3'                     => ['sqlite3:///var/data/app.db', $sqlite('/var/data/app.db')],
            'pdo-sqlite'                  => ['pdo-sqlite:///var/data/app.db', $sqlite('/var/data/app.db')],
            'pdo_sqlite'                  => ['pdo_sqlite:///var/data/app.db', $sqlite('/var/data/app.db')],
            'sqlite scheme case'          => ['SQLite:///var/data/app.db', $sqlite('/var/data/app.db')],
            'sqlite://:memory:'           => ['sqlite://:memory:', $sqlite(':memory:')],
            'sqlite:///:memory:'          => ['sqlite:///:memory:', $sqlite(':memory:')],
            'pdo_sqlite:///:memory:'      => ['pdo_sqlite:///:memory:', $sqlite(':memory:')],
            'sqlite query options'        => ['sqlite:///var/data/app.db?tblprefix=app_', $sqlite('/var/data/app.db') + ['tblprefix' => 'app_']],
            'sqlite :memory: with a query' => ['sqlite://:memory:?tblprefix=app_', $sqlite(':memory:') + ['tblprefix' => 'app_']],
        ];
    }

    #[DataProvider('dsns')]
    public function testParseDsn(string $dsn, array $expected): void
    {
        $config = DatabaseFactory::parseDsn($dsn);

        ksort($expected);
        ksort($config);

        $this->assertSame($expected, $config);
    }

    public static function invalidDsns(): array
    {
        return [
            'empty'                   => ['', 'Invalid DSN format'],
            'no scheme'               => ['db.example/app', 'Invalid DSN format'],
            'no host before the port' => ['mysql://:3306/app', 'Invalid DSN format'],
            'non-numeric port'        => ['mysql://root@db.example:port/app', 'Invalid DSN format'],
            'sqlite without a path'   => ['sqlite://', 'Invalid DSN format'],
            'no database'             => ['mysql://root@db.example', 'Database name not specified in DSN'],
            'empty database'          => ['mysql://root@db.example/', 'Database name not specified in DSN'],
        ];
    }

    #[DataProvider('invalidDsns')]
    public function testParseDsnRejectsAnInvalidDsn(string $dsn, string $message): void
    {
        $this->assertThrowsExactly(DatabaseException::class, $message, fn() => DatabaseFactory::parseDsn($dsn));
    }

    // ------------------------------------------------------- available drivers

    public static function driverExtensions(): array
    {
        return [
            'mysqli'     => ['mysqli', ['mysqli', 'mariadb']],
            'pdo_mysql'  => ['pdo_mysql', ['mysql']],
            'pdo_pgsql'  => ['pdo_pgsql', ['pgsql', 'postgresql']],
            'pdo_sqlite' => ['pdo_sqlite', ['sqlite']],
        ];
    }

    #[DataProvider('driverExtensions')]
    public function testALoadedExtensionMakesItsDriverNamesAvailable(string $extension, array $names): void
    {
        if (!extension_loaded($extension)) {
            $this->markTestSkipped("{$extension} is not loaded");
        }

        foreach ($names as $name) {
            $this->assertContains($name, DatabaseFactory::getAvailableDrivers());
            $this->assertTrue(DatabaseFactory::isDriverAvailable(strtoupper($name)), "{$name}, case-insensitively");
        }
    }

    public function testAnUnknownDriverIsNotAvailable(): void
    {
        $this->assertFalse(DatabaseFactory::isDriverAvailable('oracle'));
        $this->assertFalse(DatabaseFactory::isDriverAvailable(''));
    }
}
