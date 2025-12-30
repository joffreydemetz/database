<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Tests\TestCase;

class PdoDatabaseTest extends TestCase
{
    private ?PdoDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isMysqlAvailable()) {
            $this->markTestSkipped('MySQL PDO driver not available');
        }

        $this->db = new PdoDatabase($this->getMysqlOptions());
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
        $quoted = $this->db->quote("O'Reilly");

        $this->assertIsString($quoted);
        $this->assertStringContainsString("'O", $quoted);
    }

    public function testEscape(): void
    {
        $escaped = $this->db->escape("O'Reilly");

        $this->assertIsString($escaped);
        // escape() replaces ' with '' for SQL escaping, so it still contains '
        $this->assertStringContainsString("''", $escaped);
        $this->assertEquals("O''Reilly", $escaped);
    }

    public function testQuoteName(): void
    {
        $quoted = $this->db->quoteName('table_name');

        $this->assertEquals('`table_name`', $quoted);
    }

    public function testGetTableColumns(): void
    {
        $this->db->setQuery($this->getMysqlTestTableSql('test_users'));
        $this->db->execute();

        $columns = $this->db->getTableColumns('test_users');

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
    }

    public function testMysqlSpecificMethods(): void
    {
        // Test MySQL-only profiling methods exist
        $this->assertTrue(method_exists($this->db, 'startProfiling'));
        $this->assertTrue(method_exists($this->db, 'stopProfiling'));
        $this->assertTrue(method_exists($this->db, 'lockTable'));
        $this->assertTrue(method_exists($this->db, 'unlockTables'));
    }
}
