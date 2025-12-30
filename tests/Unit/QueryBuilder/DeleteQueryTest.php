<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\DeleteQuery;

/**
 * Comprehensive DeleteQuery Test Suite
 * 
 * Tests all DeleteQuery functionality including:
 * - DELETE FROM clause
 * - WHERE clause
 * - JOIN clause
 * - ORDER BY clause
 * - LIMIT clause
 * - Parameter binding
 */
class DeleteQueryTest extends TestCase
{
    // ========================================
    // BASIC DELETE TESTS
    // ========================================

    public function testDeleteBasic(): void
    {
        $query = new DeleteQuery();
        $query->delete('users');

        $sql = $query->toString();

        $this->assertStringContainsString('DELETE', $sql);
        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testDeleteWithTablePrefix(): void
    {
        $query = new DeleteQuery();
        $query->delete('#__users');

        $sql = $query->toString();

        $this->assertStringContainsString('FROM #__users', $sql);
    }

    public function testDeleteWithTableAlias(): void
    {
        $query = new DeleteQuery();
        $query->delete('users u');

        $sql = $query->toString();

        $this->assertStringContainsString('FROM users u', $sql);
    }

    // ========================================
    // WHERE CLAUSE TESTS
    // ========================================

    public function testWhereSimple(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('id = :id')
            ->bindValue(':id', 123);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id = :id', $sql);
        $this->assertCount(1, $query->getBounded());
    }

    public function testWhereMultipleAnd(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where(['active = 0', 'deleted_at IS NOT NULL']);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('active = 0', $sql);
        $this->assertStringContainsString('AND', $sql);
        $this->assertStringContainsString('deleted_at IS NOT NULL', $sql);
    }

    public function testWhereMultipleOr(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where(['role = "guest"', 'role = "temp"'], 'OR');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testWhereIn(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('id IN (1, 2, 3, 4, 5)');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id IN (1, 2, 3, 4, 5)', $sql);
    }

    public function testWhereNotNull(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('email_verified_at IS NOT NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('IS NOT NULL', $sql);
    }

    public function testWhereLessThan(): void
    {
        $query = new DeleteQuery();
        $query->delete('logs')
            ->where('created_at < :date')
            ->bindValue(':date', '2020-01-01');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE created_at < :date', $sql);
    }

    // ========================================
    // JOIN CLAUSE TESTS
    // ========================================

    public function testDeleteWithInnerJoin(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->innerJoin('banned b ON b.user_id = users.id');

        $sql = $query->toString();

        $this->assertStringContainsString('INNER JOIN banned b', $sql);
    }

    public function testDeleteWithLeftJoin(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->leftJoin('profiles p ON p.user_id = users.id')
            ->where('p.id IS NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('LEFT JOIN profiles p', $sql);
        $this->assertStringContainsString('WHERE p.id IS NULL', $sql);
    }

    public function testDeleteWithMultipleJoins(): void
    {
        $query = new DeleteQuery();
        $query->delete('posts p')
            ->innerJoin('users u ON u.id = p.user_id')
            ->leftJoin('categories c ON c.id = p.category_id')
            ->where('u.deleted_at IS NOT NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('INNER JOIN users u', $sql);
        $this->assertStringContainsString('LEFT JOIN categories c', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testBindValue(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('id = :id')
            ->bindValue(':id', 456);

        $bounded = $query->getBounded();

        $this->assertCount(1, $bounded);
        $this->assertArrayHasKey(':id', $bounded);
        $this->assertEquals(456, $bounded[':id']->value);
    }

    public function testBindMultipleValues(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where(['created_at < :date', 'active = :active'])
            ->bindValue(':date', '2020-01-01')
            ->bindValue(':active', 0);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertArrayHasKey(':date', $bounded);
        $this->assertArrayHasKey(':active', $bounded);
    }

    public function testBindArray(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where(['email = :email', 'status = :status'])
            ->bindArray([
                ':email' => 'test@example.com',
                ':status' => 'deleted'
            ]);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertEquals('test@example.com', $bounded[':email']->value);
        $this->assertEquals('deleted', $bounded[':status']->value);
    }

    public function testUnbindParameter(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('id = :id')
            ->bindValue(':id', 123)
            ->unbind(':id');

        $bounded = $query->getBounded();

        $this->assertCount(0, $bounded);
    }

    // ========================================
    // COMPLEX QUERY TESTS
    // ========================================

    public function testComplexDeleteWithAllFeatures(): void
    {
        $query = new DeleteQuery();
        $query->delete('logs l')
            ->innerJoin('users u ON u.id = l.user_id')
            ->where(['l.level = :level', 'l.created_at < :date', 'u.deleted_at IS NOT NULL'])
            ->bindValue(':level', 'debug')
            ->bindValue(':date', '2020-01-01');

        $sql = $query->toString();

        $this->assertStringContainsString('DELETE', $sql);
        $this->assertStringContainsString('FROM logs l', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 1000', $sql);
        $this->assertCount(2, $query->getBounded());
    }

    public function testDeleteOrphanedRecords(): void
    {
        $query = new DeleteQuery();
        $query->delete('posts')
            ->leftJoin('users ON users.id = posts.user_id')
            ->where('users.id IS NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('LEFT JOIN users', $sql);
        $this->assertStringContainsString('WHERE users.id IS NULL', $sql);
    }

    public function testDeleteOldRecords(): void
    {
        $query = new DeleteQuery();
        $query->delete('sessions')
            ->where('last_activity < :timestamp')
            ->bindValue(':timestamp', time() - 86400);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE last_activity < :timestamp', $sql);
        $this->assertStringContainsString('ORDER BY last_activity ASC', $sql);
        $this->assertStringContainsString('LIMIT 10000', $sql);
    }

    public function testToStringMagicMethod(): void
    {
        $query = new DeleteQuery();
        $query->delete('users')
            ->where('id = :id')
            ->bindValue(':id', 1);

        $sql = (string)$query;

        $this->assertStringContainsString('DELETE', $sql);
        $this->assertStringContainsString('FROM users', $sql);
    }
}
