<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\StringQuery;
use JDZ\Database\Tests\Fixtures\UserRow;
use JDZ\Database\Tests\TestCase;

/**
 * The Database base class as the consumers use it, on an in-memory SQLite database
 * (tblprefix "test_"): table prefix, quoting, bound parameters, null dates and the
 * load*() family. Quoted values are run through SQLite, so they are valid SQL, not
 * just the expected strings.
 */
class DatabaseBehaviourTest extends TestCase
{
    private PdoSqliteDatabase $db;

    protected function setUp(): void
    {
        if (!$this->isSqliteAvailable()) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $this->db = new PdoSqliteDatabase($this->getSqliteOptions());
        $this->db->setQuery('CREATE TABLE #__users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT, age INTEGER)');
        $this->db->execute();
        $this->db->setQuery("INSERT INTO #__users (name, email, age) VALUES ('Ann', 'ann@example.com', 30), ('Bob', 'bob@example.com', 25), ('Cid', NULL, 41)");
        $this->db->execute();
    }

    protected function tearDown(): void
    {
        $this->db->disconnect();
    }

    private function scalar(string $sql): mixed
    {
        $this->db->setQuery($sql);

        return $this->db->loadResult();
    }

    // ------------------------------------------------------------ table prefix

    public function testThePrefixIsReplacedInTableNames(): void
    {
        $this->assertSame(3, $this->scalar('SELECT COUNT(*) FROM #__users'));
        $this->assertSame(3, $this->scalar('SELECT COUNT(*) FROM test_users'));
    }

    public function testThePrefixIsKeptInsideQuotedStrings(): void
    {
        $this->assertSame('#__kept', $this->scalar("SELECT '#__kept' FROM #__users LIMIT 1"));
        $this->assertSame('#__kept', $this->scalar('SELECT "#__kept" FROM #__users LIMIT 1'));
        $this->assertSame("it's #__kept", $this->scalar("SELECT 'it''s #__kept' FROM #__users LIMIT 1"));
    }

    // ----------------------------------------------------------------- quoting

    public function testQuoteEscapesAndWraps(): void
    {
        $this->assertSame("'O''Reilly'", $this->db->q("O'Reilly"));
        $this->assertSame("'007'", $this->db->q('007'), 'a leading zero is kept as text');
        $this->assertSame("'it's'", $this->db->q("it's", false), 'escape=false wraps only');
        $this->assertSame("O'Reilly", $this->scalar('SELECT ' . $this->db->q("O'Reilly")));
    }

    public function testQuoteNameQuotesEachPart(): void
    {
        $this->assertSame('"users"', $this->db->qn('users'));
        $this->assertSame('"u"."name" AS "n"', $this->db->qn('u.name', 'n'));
        $this->assertSame('Ann', $this->scalar('SELECT ' . $this->db->qn('u.name') . ' FROM #__users AS u ORDER BY u.id LIMIT 1'));
    }

    public function testEscapeWithExtraEscapesLikeWildcards(): void
    {
        $this->assertSame("a''b", $this->db->e("a'b"));
        $this->assertSame('50\%\_off', $this->db->e('50%_off', true));
        $this->assertSame('50%_off', $this->db->e('50%_off'));
    }

    public function testValuesToStringQuotesAList(): void
    {
        $this->assertSame("'Ann', 'O''Reilly'", $this->db->valuesToString(['Ann', "O'Reilly"]));
        $this->assertSame(1, $this->scalar('SELECT COUNT(*) FROM #__users WHERE name IN (' . $this->db->valuesToString(['Ann', "O'Reilly"]) . ')'));
    }

    public function testConcatenateAndCharLengthAreValidSql(): void
    {
        $this->assertSame('Ann-ann@example.com', $this->scalar('SELECT ' . $this->db->concatenate(['name', 'email'], '-') . ' FROM #__users ORDER BY id LIMIT 1'));
        $this->assertSame(3, $this->scalar('SELECT ' . $this->db->charLength('name') . ' FROM #__users ORDER BY id LIMIT 1'));
    }

    // -------------------------------------------------------- bound parameters

    public function testBoundParametersReachTheStatementWithTheirType(): void
    {
        $insert = (new StringQuery('INSERT INTO #__users (name, email, age) VALUES (:name, :email, :age)'))
            ->bindValue(':name', 'Zoé')
            ->bindValue(':email', null, 'null')
            ->bindValue(':age', 42, 'int');
        $this->db->setQuery($insert);
        $this->db->execute();

        $select = (new SelectQuery())->select(['name', 'email', 'age'])->from('#__users')->where('id = :id')->bindValue(':id', $this->db->insertid(), 'int');
        $this->db->setQuery($select);

        $this->assertSame(['name' => 'Zoé', 'email' => null, 'age' => 42], $this->db->loadAssoc());
    }

    // -------------------------------------------------------------- null dates

    public function testNullDates(): void
    {
        $this->assertSame('1970-01-01 00:00:00', $this->db->getNullDate());
        $this->assertSame('1970-01-01', $this->db->getNullDate(false));
        $this->assertTrue($this->db->isNullDate(''));
        $this->assertTrue($this->db->isNullDate('1970-01-01 12:30:00'), 'the date part decides');
        $this->assertFalse($this->db->isNullDate('2024-01-01 00:00:00'));
    }

    // ------------------------------------------------------------- load*()

    public function testLoadAssocAndLoadRowReturnTheFirstRowOrNull(): void
    {
        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame(['id' => 1, 'name' => 'Ann'], $this->db->loadAssoc());

        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame([1, 'Ann'], $this->db->loadRow());

        $this->db->setQuery('SELECT id FROM #__users WHERE id = 99');
        $this->assertNull($this->db->loadAssoc());
    }

    public function testLoadColumnByIndexOrName(): void
    {
        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame([1, 2, 3], $this->db->loadColumn());

        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame(['Ann', 'Bob', 'Cid'], $this->db->loadColumn('name'));
    }

    public function testLoadAssocListKeysAndColumns(): void
    {
        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame([['id' => 1, 'name' => 'Ann'], ['id' => 2, 'name' => 'Bob'], ['id' => 3, 'name' => 'Cid']], $this->db->loadAssocList());

        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame([1 => 'Ann', 2 => 'Bob', 3 => 'Cid'], $this->db->loadAssocList('id', 'name'));

        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame(['Ann', 'Bob', 'Cid'], $this->db->loadAssocList('', 'name'));
    }

    public function testLoadRowListIsNumeric(): void
    {
        $this->db->setQuery('SELECT id, name FROM #__users ORDER BY id');
        $this->assertSame([[1, 'Ann'], [2, 'Bob'], [3, 'Cid']], $this->db->loadRowList());
    }

    public function testLoadObjectAndLoadObjectList(): void
    {
        $this->db->setQuery('SELECT id, name, email, age FROM #__users ORDER BY id');
        $row = $this->db->loadObject();
        $this->assertInstanceOf(\stdClass::class, $row);
        $this->assertSame('Ann', $row->name);

        $this->db->setQuery('SELECT id, name, email, age FROM #__users ORDER BY id');
        $row = $this->db->loadObject(UserRow::class);
        $this->assertSame('Ann <ann@example.com>', $row->label());

        $this->db->setQuery('SELECT id, name, email, age FROM #__users ORDER BY id');
        $list = $this->db->loadObjectList('name', UserRow::class);
        $this->assertSame(['Ann', 'Bob', 'Cid'], array_keys($list));
        $this->assertSame('Bob <bob@example.com>', $list['Bob']->label());
    }

    public function testLoadResultIsTheFirstColumnOfTheFirstRow(): void
    {
        $this->assertSame('Ann', $this->scalar('SELECT name, email FROM #__users ORDER BY id'));
        $this->assertNull($this->scalar('SELECT name FROM #__users WHERE id = 99'));
    }

    // ------------------------------------------------- rows and existence

    public function testAffectedRowsAfterAnUpdate(): void
    {
        $this->db->setQuery('UPDATE #__users SET age = age + 1 WHERE age < 35');
        $this->db->execute();

        $this->assertSame(2, $this->db->getAffectedRows());
    }

    public function testRecordExists(): void
    {
        $this->assertTrue($this->db->recordExists('#__users', ['name' => 'Ann']));
        $this->assertTrue($this->db->recordExists('#__users', ['name' => 'Bob', 'age' => '25']));
        $this->assertFalse($this->db->recordExists('#__users', ['name' => "O'Reilly"]));
        $this->assertFalse($this->db->recordExists('#__users', []));
    }
}
