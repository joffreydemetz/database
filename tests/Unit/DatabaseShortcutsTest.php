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

    public function testEIsEscapeShorthand(): void
    {
        $db = $this->makeDb();

        $this->assertSame($db->escape("it's"), $db->e("it's"));
        $this->assertSame($db->escape('100%_', true), $db->e('100%_', true));
    }

    public function testQIsQuoteShorthand(): void
    {
        $db = $this->makeDb();

        $this->assertSame($db->quote("it's"), $db->q("it's"));
        $this->assertSame($db->quote('raw', false), $db->q('raw', false));
    }

    public function testQnIsQuoteNameShorthand(): void
    {
        $db = $this->makeDb();

        $this->assertSame($db->quoteName('tbl.col'), $db->qn('tbl.col'));
        $this->assertSame($db->quoteName('tbl.col', 'alias'), $db->qn('tbl.col', 'alias'));
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
