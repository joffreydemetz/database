<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Tests\TestCase;

class DatabaseShortcutsTest extends TestCase
{
    private function makeDb(): PdoSqliteDatabase
    {
        return new PdoSqliteDatabase([
            'dbname'    => ':memory:',
            'tblprefix' => '',
        ]);
    }

    public function testCreateFromDsnReturnsStatic(): void
    {
        $db = PdoSqliteDatabase::createFromDsn('sqlite://:memory:');

        $this->assertInstanceOf(PdoSqliteDatabase::class, $db);
        $this->assertSame('', $db->tablePrefix);
    }

    public function testCreateFromDsnOptionsOverrideDsn(): void
    {
        $db = PdoDatabase::createFromDsn('mysql://root:p%40ss@localhost:3306/mydb', [
            'tblprefix' => 'abc_',
        ]);

        $this->assertInstanceOf(PdoDatabase::class, $db);
        $this->assertSame('abc_', $db->tablePrefix);
    }

    public function testCreateFromDsnDefaultsCharset(): void
    {
        $defaulted = PdoDatabase::createFromDsn('mysql://root@localhost/mydb');
        $explicit  = PdoDatabase::createFromDsn('mysql://root@localhost/mydb?charset=utf8');

        $this->assertSame('utf8mb4', $this->getOptions($defaulted)['charset']);
        $this->assertSame('utf8', $this->getOptions($explicit)['charset']);
    }

    private function getOptions(PdoDatabase $db): array
    {
        $prop = new \ReflectionProperty($db, 'options');
        return $prop->getValue($db);
    }
}
