<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Query\StringQuery;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PdoDatabaseTest extends TestCase
{
    private ?PdoDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isMysqlAvailable()) {
            $this->markTestSkipped('MySQL PDO driver not available');
        }

        $options = $this->getMysqlOptions();

        try {
            $this->db = new PdoDatabase($options);
            $this->db->connect();
            $this->db->disconnect();
            $this->db = new PdoDatabase($options);
        } catch (\Exception $e) {
            $this->markTestSkipped('MySQL server not available (set JDZ_MYSQL_HOST/PORT/DATABASE/USERNAME/PASSWORD): ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->db && $this->db->connected()) {
            try {
                $this->db->setQuery($this->getDropTableSql('test_users'));
                $this->db->execute();
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
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

    public function testDisconnect(): void
    {
        $this->db->connect();
        $this->assertTrue($this->db->connected());

        $this->db->disconnect();

        $this->assertFalse($this->db->connected());
    }

    public function testGetVersion(): void
    {
        $version = $this->db->getVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/\d+\.\d+/', $version);
    }

    public function testGetDatabaseName(): void
    {
        $dbName = $this->db->getDatabaseName();

        $this->assertIsString($dbName);
        $this->assertEquals($this->getMysqlOptions()['dbname'], $dbName);
    }

    public function testGetCollation(): void
    {
        $collation = $this->db->getCollation();

        $this->assertIsString($collation);
        $this->assertNotEmpty($collation);
    }

    public function testCreateTable(): void
    {
        $sql = $this->getMysqlTestTableSql('test_users');
        $this->db->setQuery($sql);
        $this->db->execute();

        $tables = $this->db->getTableList();

        $this->assertContains('test_users', $tables);
    }

    public function testInsertAndLoad(): void
    {
        // Create table
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        // Insert data
        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) 
            VALUES ('John Doe', 'john@example.com', 30)
        ");
        $this->db->execute();

        $insertId = $this->db->insertid();
        $this->assertGreaterThan(0, $insertId);

        // Load data
        $this->db->setQuery("SELECT * FROM test_users WHERE id = " . $insertId);
        $row = $this->db->loadObject();

        $this->assertIsObject($row);
        $this->assertEquals('John Doe', $row->name);
        $this->assertEquals('john@example.com', $row->email);
        $this->assertEquals(30, $row->age);
    }

    public function testLoadAssoc(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Jane', 'jane@test.com')");
        $this->db->execute();

        $this->db->setQuery("SELECT * FROM test_users LIMIT 1");
        $row = $this->db->loadAssoc();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('name', $row);
        $this->assertEquals('Jane', $row['name']);
    }

    public function testLoadObjectList(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('User1', 'u1@test.com')");
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('User2', 'u2@test.com')");
        $this->db->execute();

        $this->db->setQuery("SELECT * FROM test_users");
        $rows = $this->db->loadObjectList();

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertIsObject($rows[0]);
        $this->assertEquals('User1', $rows[0]->name);
    }

    public function testLoadColumn(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('A', 'a@test.com')");
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('B', 'b@test.com')");
        $this->db->execute();

        $this->db->setQuery("SELECT name FROM test_users");
        $names = $this->db->loadColumn();

        $this->assertIsArray($names);
        $this->assertCount(2, $names);
        $this->assertEquals('A', $names[0]);
        $this->assertEquals('B', $names[1]);
    }

    public function testTableExists(): void
    {
        $this->assertFalse($this->db->tableExists('test_users'));

        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));
    }

    public function testDropTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));

        $this->db->dropTable('test_users');

        $this->assertFalse($this->db->tableExists('test_users'));
    }

    public function testTruncateTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Test', 'test@test.com')");
        $this->db->execute();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();
        $this->assertEquals(1, $count);

        $this->db->truncateTable('test_users');

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();
        $this->assertEquals(0, $count);
    }

    public function testTransactions(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('TX Test', 'tx@test.com')");
        $this->db->execute();

        $this->db->transactionCommit();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users WHERE name = 'TX Test'");
        $count = $this->db->loadResult();
        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Rollback', 'rb@test.com')");
        $this->db->execute();

        $this->db->transactionRollback();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users WHERE name = 'Rollback'");
        $count = $this->db->loadResult();
        $this->assertEquals(0, $count);
    }

    public function testQuote(): void
    {
        $this->assertSame("'O''Reilly'", $this->db->quote("O'Reilly"));
    }

    public function testEscape(): void
    {
        // the full escaping table is in Unit\PdoEscapeTest
        $this->assertSame("O''Reilly", $this->db->escape("O'Reilly"));
    }

    public static function roundTripTexts(): array
    {
        return [
            'quotes'      => ['it\'s "quoted"'],
            'backslashes' => ['C:\\dir\\'],
            'line breaks' => ["a\r\nb"],
            'accents'     => ["l'été à Noël"],
        ];
    }

    #[DataProvider('roundTripTexts')]
    public function testAQuotedValueReadsBackUnchanged(string $text): void
    {
        $this->db->setQuery('SELECT ' . $this->db->quote($text));

        $this->assertSame($text, $this->db->loadResult());
    }

    public function testEscapeWithExtraMatchesWildcardsLiterally(): void
    {
        $pattern = "'" . $this->db->escape('50%_off', true) . "'";
        $this->db->setQuery("SELECT '50%_off' LIKE {$pattern}, '50% off' LIKE {$pattern}");

        $this->assertSame([1, 0], $this->db->loadRow());
    }

    public function testQuoteName(): void
    {
        $this->assertSame('`table_name`', $this->db->quoteName('table_name'));
        $this->assertSame('`u`.`name` AS `n`', $this->db->qn('u.name', 'n'));
    }

    public function testGetTableColumns(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $columns = $this->db->getTableColumns('#__users');

        $this->assertSame(['id', 'name', 'email', 'age', 'created_at'], array_keys($columns));
        $this->assertSame(['int', 'varchar', 'varchar', 'int', 'datetime'], array_column($columns, 'Type'), 'the length is cut off the type');
        $this->assertObjectNotHasProperty('Collation', $columns['name']);
        $this->assertObjectHasProperty('Collation', $this->db->getTableColumns('#__users', true)['name'], 'SHOW FULL COLUMNS');
    }

    // ---------------------------- rows and bound parameters, as MysqliDatabaseTest

    public function testUpdate(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();
        $userId = $this->db->insertid();

        $this->db->setQuery("UPDATE test_users SET age = 30 WHERE id = {$userId}");
        $this->db->execute();

        $this->assertSame(1, $this->db->getAffectedRows());

        $this->db->setQuery("SELECT age FROM test_users WHERE id = {$userId}");
        $this->assertSame(30, $this->db->loadResult());
    }

    public function testDelete(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) VALUES
            ('John', 'john@example.com', 25),
            ('Jane', 'jane@example.com', 30)
        ");
        $this->db->execute();

        $this->db->setQuery('DELETE FROM test_users WHERE age = 25');
        $this->db->execute();

        $this->assertSame(1, $this->db->getAffectedRows());

        $this->db->setQuery('SELECT COUNT(*) FROM test_users');
        $this->assertSame(1, $this->db->loadResult());
    }

    public function testParameterBinding(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery('INSERT INTO test_users (name, email, age) VALUES (:name, :email, :age)')
            ->bindValue(':name', 'John Doe')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30, 'int');
        $this->db->execute();

        $userId = $this->db->insertid();

        $this->db->setQuery('SELECT name, email, age FROM test_users WHERE id = :id')
            ->bindValue(':id', $userId, 'int');

        $this->assertSame(['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30], $this->db->loadAssoc());
    }

    public function testANamedParameterUsedTwiceIsBoundTwice(): void
    {
        $this->db->setQuery((new StringQuery('SELECT :v AS a, :v AS b'))->bindValue(':v', 'x'));

        $this->assertSame(['a' => 'x', 'b' => 'x'], $this->db->loadAssoc());
    }

    public function testANullParameterIsBound(): void
    {
        $this->db->setQuery((new StringQuery('SELECT :v IS NULL AS isnull'))->bindValue(':v', null, 'null'));

        $this->assertSame(1, $this->db->loadResult());
    }

    public function testAFailedStatementIsWrapped(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES (NULL, 'x')");

        try {
            $this->db->execute();
            $this->fail('a NULL name was inserted');
        } catch (DatabaseException $e) {
            $this->assertSame(DatabaseException::class, $e::class);
            // the server's text follows, in the server's lc_messages language
            $this->assertStringStartsWith('SQLSTATE[23000]: Integrity constraint violation: 1048 ', $e->getMessage());
            $this->assertSame(23000, $e->getCode());
            $this->assertSame(ExecutionFailureException::class, $e->getPrevious()::class);
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious()->getPrevious());
        }
    }

    // --------------------------------------- more tables, as MysqliDatabaseTest

    public function testRenameTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery($this->getMysqlTestTableSql('test_people'));
        $this->db->execute();

        try {
            $this->db->renameTable('#__users', '#__people');
            $this->assertTrue($this->db->tableExists('test_users'), 'the target name is taken: nothing renamed');

            $this->db->dropTable('#__people');
            $this->db->renameTable('#__users', '#__people');
            $this->assertFalse($this->db->tableExists('test_users'));
            $this->assertTrue($this->db->tableExists('test_people'));

            $this->db->renameTable('#__users', '#__other');
            $this->assertFalse($this->db->tableExists('test_other'), 'no source: nothing renamed');
        } finally {
            $this->db->dropTable('#__people');
        }
    }

    public function testGetTableKeys(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery('CREATE UNIQUE INDEX idx_users_email ON test_users (email)');
        $this->db->execute();

        $keys = $this->db->getTableKeys('#__users');

        $this->assertSame(['PRIMARY' => 'id', 'idx_users_email' => 'email'], array_column($keys, 'Column_name', 'Key_name'));
    }

    public function testLockTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->lockTable('#__users');

        try {
            $this->db->setQuery('SELECT COUNT(*) FROM test_users');
            $this->assertSame(0, $this->db->loadResult(), 'the locked table is usable');

            // under LOCK TABLES an alias of the table is not locked
            $this->db->setQuery('SELECT COUNT(*) FROM test_users AS u');
            $this->db->execute();
            $this->fail('the table was not locked');
        } catch (DatabaseException $e) {
            $this->assertStringStartsWith('SQLSTATE[HY000]: General error: 1100 ', $e->getMessage(), 'ER_TABLE_NOT_LOCKED');
        } finally {
            $this->db->unlockTables();
        }

        $this->db->setQuery('SELECT COUNT(*) FROM test_users AS u');
        $this->assertSame(0, $this->db->loadResult(), 'unlocked');
    }
}
