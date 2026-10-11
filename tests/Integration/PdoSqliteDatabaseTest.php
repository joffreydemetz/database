<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\Exception\PrepareStatementFailureException;
use JDZ\Database\Pdo\PdoSqliteDatabase;
use JDZ\Database\Tests\TestCase;

class PdoSqliteDatabaseTest extends TestCase
{
    private ?PdoSqliteDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isSqliteAvailable()) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $this->db = new PdoSqliteDatabase($this->getSqliteOptions());
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->disconnect();
        }
        $this->db = null;
    }

    public function testConnection(): void
    {
        $this->assertFalse($this->db->connected());

        $this->db->connect();

        $this->assertTrue($this->db->connected());
    }

    public function testGetVersion(): void
    {
        $version = $this->db->getVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/\d+\.\d+/', $version);
    }

    public function testCreateAndQueryTable(): void
    {
        // Create table
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        // Insert data
        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) 
            VALUES ('Alice', 'alice@example.com', 25)
        ");
        $this->db->execute();

        // Query data
        $this->db->setQuery("SELECT * FROM test_users WHERE name = 'Alice'");
        $row = $this->db->loadObject();

        $this->assertIsObject($row);
        $this->assertEquals('Alice', $row->name);
        $this->assertEquals('alice@example.com', $row->email);
        $this->assertEquals(25, $row->age);
    }

    public function testTableExists(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));

        // Cleanup
        $this->db->dropTable('test_users');

        $this->assertFalse($this->db->tableExists('test_users'));
    }

    public function testGetTableList(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $tables = $this->db->getTableList();

        $this->assertIsArray($tables);
        $this->assertContains('test_users', $tables);

        // Cleanup
        $this->db->dropTable('test_users');
    }

    public function testGetTableColumns(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $columns = $this->db->getTableColumns('test_users');

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
    }

    public function testTruncateTable(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Bob', 'bob@test.com')");
        $this->db->execute();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();
        $this->assertEquals(1, $count);

        $this->db->truncateTable('test_users');

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();
        $this->assertEquals(0, $count);

        // Cleanup
        $this->db->dropTable('test_users');
    }

    public function testTransactions(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('TX', 'tx@test.com')");
        $this->db->execute();

        $this->db->transactionCommit();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users WHERE name = 'TX'");
        $count = $this->db->loadResult();
        $this->assertEquals(1, $count);
    }

    public function testQuoteName(): void
    {
        $quoted = $this->db->quoteName('table_name');

        $this->assertEquals('"table_name"', $quoted);
    }

    public function testEscape(): void
    {
        $escaped = $this->db->escape("Test's value");

        $this->assertIsString($escaped);
        $this->assertStringContainsString("''", $escaped);
    }

    private function rowCount(string $table = 'test_users'): int
    {
        $this->db->setQuery('SELECT COUNT(*) FROM ' . $table);

        return $this->db->loadResult();
    }

    public function testTransactionRollback(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Rollback', 'rb@test.com')");
        $this->db->execute();
        $this->db->transactionRollback();

        $this->assertSame(0, $this->rowCount());
    }

    public function testInsertidIsTheLastGeneratedId(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('A', 'a@test.com')");
        $this->db->execute();
        $this->assertSame(1, $this->db->insertid());

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('B', 'b@test.com')");
        $this->db->execute();
        $this->assertSame(2, $this->db->insertid());
    }

    public function testGetTableKeys(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery('CREATE UNIQUE INDEX idx_users_email ON test_users (email)');
        $this->db->execute();
        $this->db->setQuery('CREATE INDEX idx_users_name_age ON test_users (name, age)');
        $this->db->execute();

        $keys = $this->db->getTableKeys('#__users');

        $this->assertSame(['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0], (array)$keys[0], 'the primary key comes first');

        // the order of the other indexes is SQLite's; their columns keep the index order
        $byIndex = [];
        foreach ($keys as $key) {
            $byIndex[$key->Key_name][] = [$key->Column_name, $key->Non_unique];
        }
        ksort($byIndex);

        $this->assertSame([
            'PRIMARY'            => [['id', 0]],
            'idx_users_email'    => [['email', 0]],
            'idx_users_name_age' => [['name', 1], ['age', 1]],
        ], $byIndex);
    }

    public function testGetTableColumnsMapsThePragmaToTheMysqlShape(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();

        $columns = $this->db->getTableColumns('#__users', true);

        $this->assertSame(['id', 'name', 'email', 'age', 'created_at'], array_keys($columns));
        $this->assertSame(['Field' => 'name', 'Type' => 'TEXT', 'Null' => 'NO', 'Default' => null, 'Key' => '', 'Extra' => ''], (array)$columns['name']);
        $this->assertSame(['PRI', 'auto_increment'], [$columns['id']->Key, $columns['id']->Extra], 'an INTEGER primary key');
        $this->assertSame('YES', $columns['age']->Null);
        $this->assertObjectNotHasProperty('Extra', $this->db->getTableColumns('#__users')['id'], 'Extra only in the full listing');
    }

    public function testGetTableCreateReturnsTheStoredSql(): void
    {
        $sql = 'CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)';
        $this->db->setQuery($sql);
        $this->db->execute();

        $this->assertSame(['test_users' => $sql], $this->db->getTableCreate(['test_users']));
    }

    public function testGetTableCreateThrowsOnAMissingTable(): void
    {
        // 2.3.0 (D24): the missing table used to be silently left out
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage("Table 'test_missing' does not exist");

        $this->db->getTableCreate(['test_missing']);
    }

    public function testRenameTable(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->db->execute();

        $this->db->renameTable('#__users', '#__people');

        $this->assertSame(['test_people'], $this->db->getTableList());
        $this->db->setQuery('SELECT name FROM test_people');
        $this->assertSame('Alice', $this->db->loadResult());
    }

    public function testRenameTableDoesNothingWithoutASourceOrWithATakenTarget(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery($this->getSqliteTestTableSql('test_people'));
        $this->db->execute();

        $this->db->renameTable('#__missing', '#__other');
        $this->db->renameTable('#__users', '#__people');

        $this->assertSame(['test_people', 'test_users'], $this->db->getTableList());
    }

    public function testTruncateTableResetsTheAutoincrementSequence(): void
    {
        $this->db->setQuery($this->getSqliteTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('A', 'a@test.com'), ('B', 'b@test.com')");
        $this->db->execute();

        $this->db->truncateTable('#__users');

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('C', 'c@test.com')");
        $this->db->execute();

        $this->assertSame(1, $this->db->insertid(), 'ids start over');
        $this->assertSame(1, $this->rowCount());
    }

    public function testTruncateTableWithoutAnAutoincrementSequence(): void
    {
        // no AUTOINCREMENT table in this database, so no sqlite_sequence to reset
        $this->db->setQuery('CREATE TABLE test_plain (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_plain (name) VALUES ('A'), ('B')");
        $this->db->execute();

        $this->db->truncateTable('#__plain');
        $this->db->truncateTable('#__missing'); // a missing table is skipped

        $this->assertSame(0, $this->rowCount('test_plain'));
    }

    public function testForeignKeysAreEnforced(): void
    {
        // SQLite leaves them off unless the connection turns them on
        $this->db->setQuery('CREATE TABLE test_parents (id INTEGER PRIMARY KEY)');
        $this->db->execute();
        $this->db->setQuery('CREATE TABLE test_children (id INTEGER PRIMARY KEY, id_parent INTEGER NOT NULL REFERENCES test_parents (id))');
        $this->db->execute();
        $this->db->setQuery('INSERT INTO test_children (id_parent) VALUES (99)');

        $e = $this->assertThrowsExactly(
            DatabaseException::class,
            'SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed',
            fn() => $this->db->execute()
        );

        $this->assertSame(23000, $e->getCode());
        $this->assertSame(ExecutionFailureException::class, $e->getPrevious()::class);
        $this->assertSame(0, $this->rowCount('test_children'));
    }

    public function testASyntaxErrorFailsToPrepare(): void
    {
        $e = $this->assertThrowsExactly(
            PrepareStatementFailureException::class,
            'SQLSTATE[HY000]: General error: 1 near "SELEC": syntax error',
            fn() => $this->db->setQuery('SELEC 1')
        );

        $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
    }
}
