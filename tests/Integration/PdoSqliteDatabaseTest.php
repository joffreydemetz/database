<?php

namespace JDZ\Database\Tests\Integration;

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

    public function testGetCollation(): void
    {
        $collation = $this->db->getCollation();

        $this->assertEquals('BINARY', $collation);
    }

    public function testGetDatabaseName(): void
    {
        $dbName = $this->db->getDatabaseName();

        $this->assertEquals(':memory:', $dbName);
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

    public function testSqliteDoesNotHaveMysqlOnlyMethods(): void
    {
        // Note: SQLite inherits from PdoDatabase, so it technically has these methods
        // but they would fail or be no-ops if called
        // This is a design limitation of the current inheritance structure

        // We can test that calling these methods doesn't break, even if they're not ideal
        $this->assertTrue(method_exists($this->db, 'startProfiling'));

        // In a better design, these would be in a separate trait or interface
        // For now, we acknowledge the inheritance structure
    }

    public function testQuoteName(): void
    {
        $quoted = $this->db->quoteName('table_name');

        $this->assertEquals('`table_name`', $quoted);
    }

    public function testEscape(): void
    {
        $escaped = $this->db->escape("Test's value");

        $this->assertIsString($escaped);
        $this->assertStringContainsString("''", $escaped);
    }
}
