<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\Mysqli\MysqliDatabase;
use JDZ\Database\Tests\TestCase;

class MysqliDatabaseTest extends TestCase
{
    private ?MysqliDatabase $db = null;

    protected function setUp(): void
    {
        if (!$this->isMysqliAvailable()) {
            $this->markTestSkipped('MySQLi extension not available');
        }

        $this->db = new MysqliDatabase($this->getMysqlOptions());
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
        $affected = $this->db->execute();

        $this->assertEquals(1, $affected);

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
        $affected = $this->db->execute();

        $this->assertEquals(1, $affected);

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
}
