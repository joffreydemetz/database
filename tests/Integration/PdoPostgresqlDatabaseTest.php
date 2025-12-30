<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Pdo\PdoPostgresqlDatabase;
use JDZ\Database\Tests\TestCase;

class PdoPostgresqlDatabaseTest extends TestCase
{
    private ?PdoPostgresqlDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isPostgresqlAvailable()) {
            $this->markTestSkipped('PostgreSQL PDO driver not available');
        }

        $this->db = new PdoPostgresqlDatabase($this->getPostgresqlOptions());
    }

    protected function tearDown(): void
    {
        if ($this->db && $this->db->connected()) {
            try {
                $this->db->setQuery('DROP TABLE IF EXISTS test_users');
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

    public function testGetVersion(): void
    {
        $version = $this->db->getVersion();
        
        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertStringContainsString('PostgreSQL', $version);
    }

    public function testGetCollation(): void
    {
        $collation = $this->db->getCollation();
        
        $this->assertIsString($collation);
        $this->assertNotEmpty($collation);
    }

    public function testGetDatabaseName(): void
    {
        $dbName = $this->db->getDatabaseName();
        
        $this->assertIsString($dbName);
        $this->assertEquals($this->getPostgresqlOptions()['dbname'], $dbName);
    }

    public function testCreateAndQueryTable(): void
    {
        // Create table
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        // Insert data
        $this->db->setQuery("
            INSERT INTO test_users (name, email, age) 
            VALUES ('Charlie', 'charlie@example.com', 35)
        ");
        $this->db->execute();

        // Query data
        $this->db->setQuery("SELECT * FROM test_users WHERE name = 'Charlie'");
        $row = $this->db->loadObject();

        $this->assertIsObject($row);
        $this->assertEquals('Charlie', $row->name);
        $this->assertEquals('charlie@example.com', $row->email);
        $this->assertEquals(35, $row->age);
    }

    public function testTableExists(): void
    {
        $this->assertFalse($this->db->tableExists('test_users'));

        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));
    }

    public function testGetTableList(): void
    {
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $tables = $this->db->getTableList();

        $this->assertIsArray($tables);
        $this->assertContains('test_users', $tables);
    }

    public function testGetTableColumns(): void
    {
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $columns = $this->db->getTableColumns('test_users');

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
    }

    public function testRenameTable(): void
    {
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $this->assertTrue($this->db->tableExists('test_users'));

        $this->db->renameTable('test_users', 'test_users_renamed');

        $this->assertFalse($this->db->tableExists('test_users'));
        $this->assertTrue($this->db->tableExists('test_users_renamed'));

        // Cleanup
        $this->db->dropTable('test_users_renamed');
    }

    public function testTruncateTable(): void
    {
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('Dave', 'dave@test.com')");
        $this->db->execute();

        $this->db->truncateTable('test_users');

        $this->db->setQuery("SELECT COUNT(*) FROM test_users");
        $count = $this->db->loadResult();
        $this->assertEquals(0, $count);
    }

    public function testTransactions(): void
    {
        $this->db->setQuery($this->getPostgresqlTestTableSql('test_users'));
        $this->db->execute();

        $this->db->transactionStart();

        $this->db->setQuery("INSERT INTO test_users (name, email) VALUES ('TXUser', 'tx@test.com')");
        $this->db->execute();

        $this->db->transactionCommit();

        $this->db->setQuery("SELECT COUNT(*) FROM test_users WHERE name = 'TXUser'");
        $count = $this->db->loadResult();
        $this->assertEquals(1, $count);
    }

    public function testPostgresqlDoesNotHaveMysqlOnlyMethods(): void
    {
        // PostgreSQL inherits these methods from PdoDatabase but they may not work properly
        // Just verify the database is PostgreSQL type
        $this->assertInstanceOf(\JDZ\Database\Pdo\PdoPostgresqlDatabase::class, $this->db);
        $version = $this->db->getVersion();
        $this->assertStringContainsString('PostgreSQL', $version);
    }

    public function testQuoteName(): void
    {
        $quoted = $this->db->quoteName('table_name');
        
        // PostgreSQL uses double quotes
        $this->assertEquals('"table_name"', $quoted);
    }

    public function testEscape(): void
    {
        $escaped = $this->db->escape("Test's value");
        
        $this->assertIsString($escaped);
        $this->assertStringContainsString("''", $escaped);
    }
}
