<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\StringQuery;

/**
 * Comprehensive StringQuery Test Suite
 * 
 * Tests all StringQuery functionality including:
 * - Constructor with SQL string
 * - setQuery/getQuery methods
 * - Parameter binding
 * - toString() method
 * - Magic __toString() method
 */
class StringQueryTest extends TestCase
{
    // ========================================
    // CONSTRUCTOR TESTS
    // ========================================

    public function testConstructorWithoutQuery(): void
    {
        $query = new StringQuery();

        $sql = $query->toString();

        $this->assertEmpty($sql);
    }

    public function testConstructorWithQuery(): void
    {
        $query = new StringQuery("SELECT * FROM users");

        $sql = $query->toString();

        $this->assertEquals("SELECT * FROM users", $sql);
    }

    public function testConstructorWithComplexQuery(): void
    {
        $sql = "SELECT u.id, u.name, COUNT(p.id) AS post_count
                FROM users u
                LEFT JOIN posts p ON p.user_id = u.id
                WHERE u.active = 1
                GROUP BY u.id
                ORDER BY post_count DESC
                LIMIT 10";

        $query = new StringQuery($sql);

        $result = $query->toString();

        $this->assertEquals($sql, $result);
    }

    // ========================================
    // SETQUERY/GETQUERY TESTS
    // ========================================

    public function testSetQuery(): void
    {
        $query = new StringQuery();
        $query->setQuery("SELECT * FROM users");

        $sql = $query->toString();

        $this->assertEquals("SELECT * FROM users", $sql);
    }

    public function testGetQuery(): void
    {
        $sql = "SELECT * FROM posts";
        $query = new StringQuery($sql);

        $result = (string)$query;

        $this->assertEquals($sql, $result);
    }

    public function testSetQueryOverwritesPrevious(): void
    {
        $query = new StringQuery("SELECT * FROM users");

        $query->setQuery("SELECT * FROM posts");

        $sql = $query->toString();

        $this->assertEquals("SELECT * FROM posts", $sql);
        $this->assertStringNotContainsString('users', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testBindValue(): void
    {
        $query = new StringQuery("SELECT * FROM users WHERE id = :id");
        $query->bindValue(':id', 123);

        $bounded = $query->getBounded();

        $this->assertCount(1, $bounded);
        $this->assertArrayHasKey(':id', $bounded);
        $this->assertEquals(123, $bounded[':id']->value);
    }

    public function testBindMultipleValues(): void
    {
        $query = new StringQuery("SELECT * FROM users WHERE name = :name AND email = :email");
        $query->bindValue(':name', 'John Doe')
            ->bindValue(':email', 'john@example.com');

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertArrayHasKey(':name', $bounded);
        $this->assertArrayHasKey(':email', $bounded);
        $this->assertEquals('John Doe', $bounded[':name']->value);
        $this->assertEquals('john@example.com', $bounded[':email']->value);
    }

    public function testBindParam(): void
    {
        $query = new StringQuery("INSERT INTO users (name, email) VALUES (:name, :email)");
        $query->bindParam(':name', 'Jane Smith')
            ->bindParam(':email', 'jane@example.com');

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertArrayHasKey(':name', $bounded);
        $this->assertArrayHasKey(':email', $bounded);
    }

    public function testBindArray(): void
    {
        $query = new StringQuery("UPDATE users SET name = :name, age = :age WHERE id = :id");
        $query->bindArray([
            ':name' => 'Updated Name',
            ':age' => 35,
            ':id' => 456
        ]);

        $bounded = $query->getBounded();

        $this->assertCount(3, $bounded);
        $this->assertEquals('Updated Name', $bounded[':name']->value);
        $this->assertEquals(35, $bounded[':age']->value);
        $this->assertEquals(456, $bounded[':id']->value);
    }

    public function testUnbindParameter(): void
    {
        $query = new StringQuery("SELECT * FROM users WHERE id = :id");
        $query->bindValue(':id', 123)
            ->unbind(':id');

        $bounded = $query->getBounded();

        $this->assertCount(0, $bounded);
    }

    public function testUnbindMultipleParameters(): void
    {
        $query = new StringQuery("SELECT * FROM users WHERE name = :name AND email = :email AND age = :age");
        $query->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30)
            ->unbind([':name', ':email']);

        $bounded = $query->getBounded();

        $this->assertCount(1, $bounded);
        $this->assertArrayHasKey(':age', $bounded);
        $this->assertArrayNotHasKey(':name', $bounded);
        $this->assertArrayNotHasKey(':email', $bounded);
    }

    // ========================================
    // SQL QUERY TYPE TESTS
    // ========================================

    public function testSelectQuery(): void
    {
        $query = new StringQuery("SELECT id, name FROM users WHERE active = 1");

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM users', $sql);
        $this->assertStringContainsString('WHERE active = 1', $sql);
    }

    public function testInsertQuery(): void
    {
        $query = new StringQuery("INSERT INTO users (name, email) VALUES (:name, :email)");
        $query->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT INTO users', $sql);
        $this->assertStringContainsString('VALUES', $sql);
    }

    public function testUpdateQuery(): void
    {
        $query = new StringQuery("UPDATE users SET name = :name WHERE id = :id");
        $query->bindValue(':name', 'Updated')
            ->bindValue(':id', 1);

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE users', $sql);
        $this->assertStringContainsString('SET', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    public function testDeleteQuery(): void
    {
        $query = new StringQuery("DELETE FROM users WHERE id = :id");
        $query->bindValue(':id', 123);

        $sql = $query->toString();

        $this->assertStringContainsString('DELETE FROM users', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    // ========================================
    // COMPLEX QUERY TESTS
    // ========================================

    public function testComplexSelectWithJoins(): void
    {
        $sql = "SELECT u.id, u.name, p.title, c.content
                FROM users u
                INNER JOIN posts p ON p.user_id = u.id
                LEFT JOIN comments c ON c.post_id = p.id
                WHERE u.active = :active
                  AND p.status = :status
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $query = new StringQuery($sql);
        $query->bindValue(':active', 1)
            ->bindValue(':status', 'published')
            ->bindValue(':limit', 10)
            ->bindValue(':offset', 0);

        $result = $query->toString();

        $this->assertEquals($sql, $result);
        $this->assertCount(4, $query->getBounded());
    }

    public function testSubquery(): void
    {
        $sql = "SELECT * FROM users 
                WHERE id IN (SELECT user_id FROM posts WHERE status = :status)";

        $query = new StringQuery($sql);
        $query->bindValue(':status', 'published');

        $result = $query->toString();

        $this->assertStringContainsString('SELECT user_id FROM posts', $result);
        $this->assertCount(1, $query->getBounded());
    }

    public function testUnionQuery(): void
    {
        $sql = "SELECT id, name FROM users WHERE role = 'admin'
                UNION ALL
                SELECT id, name FROM users WHERE role = 'moderator'";

        $query = new StringQuery($sql);

        $result = $query->toString();

        $this->assertStringContainsString('UNION ALL', $result);
    }

    // ========================================
    // TABLE PREFIX TESTS
    // ========================================

    public function testTablePrefix(): void
    {
        $query = new StringQuery("SELECT * FROM #__users WHERE id = :id");
        $query->bindValue(':id', 1);

        $sql = $query->toString();

        $this->assertStringContainsString('#__users', $sql);
    }

    public function testMultipleTablePrefixes(): void
    {
        $sql = "SELECT u.*, p.* 
                FROM #__users u 
                LEFT JOIN #__posts p ON p.user_id = u.id";

        $query = new StringQuery($sql);

        $result = $query->toString();

        $this->assertStringContainsString('#__users', $result);
        $this->assertStringContainsString('#__posts', $result);
    }

    // ========================================
    // MAGIC METHOD TESTS
    // ========================================

    public function testToStringMagicMethod(): void
    {
        $sql = "SELECT * FROM users";
        $query = new StringQuery($sql);

        $result = (string)$query;

        $this->assertEquals($sql, $result);
    }

    public function testToStringWithParameters(): void
    {
        $query = new StringQuery("SELECT * FROM users WHERE id = :id");
        $query->bindValue(':id', 123);

        $result = (string)$query;

        $this->assertStringContainsString('SELECT * FROM users WHERE id = :id', $result);
    }

    // ========================================
    // EDGE CASES TESTS
    // ========================================

    public function testEmptyQuery(): void
    {
        $query = new StringQuery("");

        $sql = $query->toString();

        $this->assertEmpty($sql);
    }

    public function testWhitespaceQuery(): void
    {
        $query = new StringQuery("   SELECT * FROM users   ");

        $sql = $query->toString();

        $this->assertEquals("   SELECT * FROM users   ", $sql);
    }

    public function testMultilineQuery(): void
    {
        $sql = "SELECT *
FROM users
WHERE active = 1
ORDER BY created_at DESC";

        $query = new StringQuery($sql);

        $result = $query->toString();

        $this->assertEquals($sql, $result);
    }

    public function testQueryWithComments(): void
    {
        $sql = "SELECT * FROM users -- Get all users
WHERE active = 1";

        $query = new StringQuery($sql);

        $result = $query->toString();

        $this->assertEquals($sql, $result);
    }

    public function testFluentInterface(): void
    {
        $query = new StringQuery();
        $result = $query->setQuery("SELECT * FROM users WHERE id = :id")
            ->bindValue(':id', 123);

        $this->assertInstanceOf(StringQuery::class, $result);
        $this->assertCount(1, $query->getBounded());
    }
}
