<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\Exception\PrepareStatementFailureException;
use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Query\StringQuery;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MysqliDatabaseTest extends TestCase
{
    private ?MysqliDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isMysqliAvailable()) {
            $this->markTestSkipped('MySQLi extension not available');
        }

        $options = $this->getMysqlOptions();

        try {
            $this->db = new MysqliDatabase($options);
            $this->db->connect();
            $this->db->disconnect();
            $this->db = new MysqliDatabase($options);
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
        $this->db->setQuery("SELECT * FROM test_users WHERE id = {$insertId}");
        $user = $this->db->loadObject();

        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }

    public function testLoadObjectList(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        // Insert multiple rows
        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) VALUES 
            ('John', 'john@example.com', 25),
            ('Jane', 'jane@example.com', 30),
            ('Bob', 'bob@example.com', 35)
        ");
        $this->db->execute();

        $this->db->setQuery("SELECT * FROM test_users ORDER BY age ASC");
        $users = $this->db->loadObjectList();

        $this->assertIsArray($users);
        $this->assertCount(3, $users);
        $this->assertEquals('John', $users[0]->name);
        $this->assertEquals('Bob', $users[2]->name);
    }

    public function testLoadAssoc(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();

        $this->db->setQuery("SELECT * FROM test_users");
        $user = $this->db->loadAssoc();

        $this->assertIsArray($user);
        $this->assertArrayHasKey('name', $user);
        $this->assertEquals('John', $user['name']);
    }

    public function testLoadColumn(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) VALUES 
            ('John', 'john@example.com', 25),
            ('Jane', 'jane@example.com', 30)
        ");
        $this->db->execute();

        $this->db->setQuery("SELECT name FROM test_users ORDER BY name");
        $names = $this->db->loadColumn();

        $this->assertIsArray($names);
        $this->assertCount(2, $names);
        $this->assertContains('John', $names);
        $this->assertContains('Jane', $names);
    }

    public function testLoadResult(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();

        $this->assertEquals(1, $count);
    }

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
        $age = $this->db->loadResult();

        $this->assertEquals(30, $age);
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

        $this->db->setQuery("DELETE FROM test_users WHERE age = 25");
        $this->db->execute();

        $this->assertSame(1, $this->db->getAffectedRows());

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();

        $this->assertEquals(1, $count);
    }

    public function testTransaction(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();

        $this->db->transactionCommit();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();

        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();

        $this->db->transactionRollback();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();

        $this->assertEquals(0, $count);
    }

    public function testTableLocking(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        // Lock table
        $this->db->lockTable('test_users');

        // Perform operation
        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES ('John', 'john@example.com', 25)");
        $this->db->execute();

        // Unlock tables
        $this->db->unlockTables();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();

        $this->assertEquals(1, $count);
    }

    public function testProfiling(): void
    {
        $this->db->startProfiling();

        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("SELECT 1");
        $this->db->execute();

        $profiles = $this->db->showProfiles();

        $this->assertIsArray($profiles);
        $this->assertNotEmpty($profiles);

        $this->db->stopProfiling();
    }

    public function testParameterBinding(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email, age) VALUES (:name, :email, :age)")
            ->bindValue(':name', 'John Doe')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30);
        $this->db->execute();

        $userId = $this->db->insertid();

        $this->db->setQuery("SELECT * FROM test_users WHERE id = :id")
            ->bindValue(':id', $userId);
        $user = $this->db->loadObject();

        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }

    public function testANamedParameterUsedTwiceIsBoundTwice(): void
    {
        $this->db->setQuery((new StringQuery('SELECT :v AS a, :v AS b'))->bindValue(':v', 'x'));

        $this->assertSame(['a' => 'x', 'b' => 'x'], $this->db->loadAssoc());
    }

    public function testANullParameterIsBound(): void
    {
        $this->db->setQuery((new StringQuery('SELECT :v IS NULL AS isnull'))->bindValue(':v', null, 'null'));

        $this->assertSame('1', (string)$this->db->loadResult());
    }

    public function testConnectAppliesTheDefaultSqlModes(): void
    {
        $this->db->setQuery('SELECT @@SESSION.sql_mode');

        $this->assertSame('STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', $this->db->loadResult());
    }

    public function testConnectAppliesTheGivenSqlModes(): void
    {
        $db = new MysqliDatabase(['sqlModes' => ['ANSI_QUOTES', 'NO_ZERO_DATE']] + $this->getMysqlOptions());
        $db->setQuery('SELECT @@SESSION.sql_mode');

        $this->assertSame('ANSI_QUOTES,NO_ZERO_DATE', $db->loadResult());

        $db->disconnect();
    }

    // ------------------------------------------- tables, as PdoDatabaseTest

    public function testTableExists(): void
    {
        $this->assertFalse($this->db->tableExists('test_users'));

        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));
        $this->assertTrue($this->db->tableExists('#__users'), 'with the table prefix');
    }

    public function testDropTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->dropTable('#__users');

        $this->assertFalse($this->db->tableExists('test_users'));

        $this->db->dropTable('#__users'); // dropping a missing table does nothing
    }

    public function testTruncateTable(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();
        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Test', 'test@test.com')");
        $this->db->execute();

        $this->db->truncateTable('#__users');

        $this->db->setQuery('SELECT COUNT(*) FROM test_users');
        $this->assertSame(0, $this->db->loadResult());
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

    // ------------------------------- quoting and escaping, as PdoDatabaseTest

    public function testQuote(): void
    {
        $this->assertSame("'O\\'Reilly'", $this->db->quote("O'Reilly"));
    }

    public static function escapedTexts(): array
    {
        return [
            'a quote is backslashed'   => ["O'Reilly", false, "O\\'Reilly"],
            'a backslash is doubled'   => ['C:\\dir', false, 'C:\\\\dir'],
            'wildcards kept'           => ['50%_off', false, '50%_off'],
            'wildcards escaped (LIKE)' => ['50%_off', true, '50\\%\\_off'],
        ];
    }

    #[DataProvider('escapedTexts')]
    public function testEscape(string $text, bool $extra, string $expected): void
    {
        $this->assertSame($expected, $this->db->escape($text, $extra));
        $this->assertTrue($this->db->connected(), 'escape() connects on demand');
        $this->assertSame($expected, $this->db->e($text, $extra));
    }

    public static function roundTripTexts(): array
    {
        return [
            'quotes'       => ['it\'s "quoted"'],
            'backslashes'  => ['C:\\dir\\'],
            'line breaks'  => ["a\r\nb"],
            'a NUL byte'   => ["a\0b"],
            'a Ctrl-Z'     => ["a\x1ab"],
            'accents'      => ["l'été à Noël"],
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

    // ----------------------------------------------------------- more tables

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

    // ------------------------------------------------------------ statements

    public function testGetNumRowsAfterASelect(): void
    {
        $this->db->setQuery('SELECT 1 UNION SELECT 2 UNION SELECT 3');
        $this->db->execute();

        $this->assertSame(3, $this->db->getNumRows());
    }

    public static function quotedLiterals(): array
    {
        return [
            'single quotes'       => ["SELECT 'a:b' AS lit, :v AS v", ['lit' => 'a:b', 'v' => 'x']],
            'double quotes'       => ['SELECT "a:b" AS lit, :v AS v', ['lit' => 'a:b', 'v' => 'x']],
            'a backslashed quote' => ["SELECT 'it\\'s :x' AS lit, :v AS v", ['lit' => "it's :x", 'v' => 'x']],
            'a doubled quote'     => ["SELECT 'it''s :x' AS lit, :v AS v", ['lit' => "it's :x", 'v' => 'x']],
            'the parameter first' => ["SELECT :v AS v, '12:30:00' AS lit", ['v' => 'x', 'lit' => '12:30:00']],
            'literals around it'  => ["SELECT '12:30' AS lit, :v AS v, \"b:c\" AS lit2", ['lit' => '12:30', 'v' => 'x', 'lit2' => 'b:c']],
        ];
    }

    /**
     * MySQLi has no named parameters: MysqliStatement rewrites :name to ? and
     * must leave the ones inside string literals alone.
     */
    #[DataProvider('quotedLiterals')]
    public function testANamedParameterBesideQuotedLiterals(string $sql, array $expected): void
    {
        $this->db->setQuery((new StringQuery($sql))->bindValue(':v', 'x'));

        $this->assertSame($expected, $this->db->loadAssoc());
    }

    public function testAParameterNameWithoutItsColonIsBound(): void
    {
        $this->db->setQuery((new StringQuery('SELECT :v AS v, :w AS w'))->bindValue('v', 'x')->bindValue(':w', 'y'));

        $this->assertSame(['v' => 'x', 'w' => 'y'], $this->db->loadAssoc());
    }

    public function testPositionalParametersAreBound(): void
    {
        $this->db->setQuery((new StringQuery('SELECT ? AS a, ? AS b'))->bindValue(1, 'x')->bindValue(2, 42, 'int'));

        $this->assertSame(['a' => 'x', 'b' => 42], $this->db->loadAssoc());
    }

    public function testAFailedStatementIsWrapped(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $sql = "INSERT INTO test_users (name, email) VALUES (NULL, 'x')";
        $this->db->setQuery($sql);

        try {
            $this->db->execute();
            $this->fail('a NULL name was inserted');
        } catch (DatabaseException $e) {
            $this->assertSame(DatabaseException::class, $e::class);
            $this->assertSame(1048, $e->getCode(), 'ER_BAD_NULL_ERROR');
            $this->assertSame(ExecutionFailureException::class, $e->getPrevious()::class);
            $this->assertSame($sql, $e->getPrevious()->getSql());
        }
    }

    public function testASyntaxErrorFailsToPrepare(): void
    {
        try {
            $this->db->setQuery('SELEC 1');
            $this->fail('an invalid statement was prepared');
        } catch (PrepareStatementFailureException $e) {
            $this->assertSame(PrepareStatementFailureException::class, $e::class);
            $this->assertSame(1064, $e->getCode(), 'ER_PARSE_ERROR');
            $this->assertInstanceOf(\mysqli_sql_exception::class, $e->getPrevious());
        }
    }

    // ------------------------------------------------------ driver robustness

    /**
     * Positional values used to be bound in the order bindValue() was called,
     * while their types were sorted by position.
     */
    public function testPositionalParametersAreBoundByPosition(): void
    {
        $this->db->setQuery((new StringQuery('SELECT ? AS a, ? AS b'))->bindValue(2, 42, 'int')->bindValue(1, 'x'));

        $this->assertSame(['a' => 'x', 'b' => 42], $this->db->loadAssoc());
    }

    /**
     * lockTable() and unlockTables() used to call query() on a connection that
     * was never opened.
     */
    public function testLockingConnectsFirst(): void
    {
        $setup = new MysqliDatabase($this->getMysqlOptions());
        $setup->setQuery($this->getMysqlTestTableSql('test_users'));
        $setup->execute();
        $setup->disconnect();

        $this->db->lockTable('#__users');
        $this->db->setQuery('SELECT COUNT(*) FROM test_users');
        $this->assertSame(0, $this->db->loadResult());
        $this->db->unlockTables();

        $fresh = new MysqliDatabase($this->getMysqlOptions());
        $fresh->unlockTables();
        $this->assertTrue($fresh->connected());
        $fresh->disconnect();
    }

    public static function missingOptionProvider(): array
    {
        return [
            'no port' => ['port'],
            'no host' => ['host'],
        ];
    }

    /**
     * Like PdoDatabase, a missing host or port falls back to the defaults (it was
     * a TypeError, or an undefined key on every connect).
     */
    #[DataProvider('missingOptionProvider')]
    public function testAMissingHostOrPortFallsBackToTheDefault(string $option): void
    {
        $options = $this->getMysqlOptions();
        if (3306 !== $options['port'] || !in_array($options['host'], ['127.0.0.1', 'localhost'], true)) {
            $this->markTestSkipped('needs the server on localhost:3306');
        }
        unset($options[$option]);

        $db = new MysqliDatabase($options);
        $db->setQuery('SELECT 1');

        $this->assertSame(1, $db->loadResult());
        $db->disconnect();
    }
}
