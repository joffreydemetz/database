<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Tests\Fixtures\ConfigCapturingDatabase;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Database::createFromDsn(), the named constructor on the driver classes: it
 * parses the DSN like the factory but builds the class it is called on, and
 * fills in the charset and table prefix itself. Nothing here connects.
 */
class DatabaseShortcutsTest extends TestCase
{
    public function testCreateFromDsnBuildsTheClassItIsCalledOn(): void
    {
        // DatabaseFactory would route mysql:// to PdoDatabase
        $this->assertSame(MysqliDatabase::class, MysqliDatabase::createFromDsn('mysql://root@db.example/app')::class);
        $this->assertSame(PdoDatabase::class, PdoDatabase::createFromDsn('mysql://root@db.example/app')::class);
        $this->assertSame(PdoSqliteDatabase::class, PdoSqliteDatabase::createFromDsn('sqlite://:memory:')::class);
    }

    public static function dsnConfigs(): array
    {
        return [
            'mysql defaults to utf8mb4'      => ['mysql://root@db.example/app', [], 'utf8mb4', ''],
            'a mysql alias too'              => ['mysql2://root@db.example/app', [], 'utf8mb4', ''],
            'mysqli defaults to utf8mb4'     => ['mysqli://root@db.example/app', [], 'utf8mb4', ''],
            'mariadb defaults to utf8mb4'    => ['mariadb://root@db.example/app', [], 'utf8mb4', ''],
            'pgsql defaults to utf8'         => ['postgres://app@db.example/app', [], 'utf8', ''],
            'sqlite defaults to utf8'        => ['sqlite://:memory:', [], 'utf8', ''],
            'the DSN query sets the charset' => ['mysql://root@db.example/app?charset=latin1', [], 'latin1', ''],
            'an empty charset is defaulted'  => ['mysql://root@db.example/app?charset=', [], 'utf8mb4', ''],
            'the DSN query sets the prefix'  => ['mysql://root@db.example/app?tblprefix=dsn_', [], 'utf8mb4', 'dsn_'],
            'options override the DSN'       => ['mysql://root@db.example/app?charset=latin1&tblprefix=dsn_', ['charset' => 'ascii', 'tblprefix' => 'opt_'], 'ascii', 'opt_'],
        ];
    }

    #[DataProvider('dsnConfigs')]
    public function testCreateFromDsnFillsTheCharsetAndTablePrefix(string $dsn, array $options, string $charset, string $prefix): void
    {
        $db = ConfigCapturingDatabase::createFromDsn($dsn, $options);

        $this->assertSame($charset, $db->received['charset']);
        $this->assertSame($prefix, $db->tablePrefix);
    }

    public function testCreateFromDsnOptionsOverrideTheParsedValues(): void
    {
        $db = ConfigCapturingDatabase::createFromDsn('sqlite:///var/data/app.db', ['dbname' => ':memory:']);

        $this->assertSame(':memory:', $db->getDatabaseName());
    }
}
