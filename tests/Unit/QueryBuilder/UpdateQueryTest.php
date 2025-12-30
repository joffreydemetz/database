<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\UpdateQuery;

/**
 * Comprehensive UpdateQuery Test Suite
 * 
 * Tests all UpdateQuery functionality including:
 * - UPDATE clause (single/multiple tables)
 * - IGNORE modifier
 * - SET clause
 * - WHERE clause
 * - JOIN clause
 * - ORDER BY clause
 * - LIMIT clause
 * - Parameter binding
 */
class UpdateQueryTest extends TestCase
{
    // ========================================
    // BASIC UPDATE TESTS
    // ========================================

    public function testUpdateBasic(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('name = :name')
            ->bindValue(':name', 'John Doe');

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE users', $sql);
        $this->assertStringContainsString('SET name = :name', $sql);
    }

    public function testUpdateWithTablePrefix(): void
    {
        $query = new UpdateQuery();
        $query->update('#__users')
            ->set('name = :name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE #__users', $sql);
    }

    // ========================================
    // UPDATE IGNORE TESTS
    // ========================================

    public function testUpdateIgnore(): void
    {
        $query = new UpdateQuery();
        $query->update('users', true)
            ->set('email = :email')
            ->bindValue(':email', 'john@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE IGNORE users', $sql);
    }

    // ========================================
    // SET CLAUSE TESTS
    // ========================================

    public function testSetSingleField(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('name = :name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('SET name = :name', $sql);
    }

    public function testSetMultipleFields(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set([
                'name = :name',
                'email = :email',
                'age = :age'
            ])
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30);

        $sql = $query->toString();

        $this->assertStringContainsString('SET name = :name, email = :email, age = :age', $sql);
        $this->assertCount(3, $query->getBounded());
    }

    public function testSetWithLiterals(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set([
                'name = :name',
                "status = 'active'",
                'updated_at = NOW()'
            ])
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString("status = 'active'", $sql);
        $this->assertStringContainsString('updated_at = NOW()', $sql);
    }

    public function testSetWithExpressions(): void
    {
        $query = new UpdateQuery();
        $query->update('posts')
            ->set('views = views + 1');

        $sql = $query->toString();

        $this->assertStringContainsString('SET views = views + 1', $sql);
    }

    // ========================================
    // WHERE CLAUSE TESTS
    // ========================================

    public function testWhereSimple(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('active = 1')
            ->where('id = :id')
            ->bindValue(':id', 123);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id = :id', $sql);
    }

    public function testWhereMultipleAnd(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('verified = 1')
            ->where(['email_verified = 1', 'phone_verified = 1']);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('email_verified = 1', $sql);
        $this->assertStringContainsString('AND', $sql);
        $this->assertStringContainsString('phone_verified = 1', $sql);
    }

    public function testWhereMultipleOr(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('status = :status')
            ->where(['role = "admin"', 'role = "moderator"'], 'OR')
            ->bindValue(':status', 'vip');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    // ========================================
    // JOIN CLAUSE TESTS
    // ========================================

    public function testUpdateWithInnerJoin(): void
    {
        $query = new UpdateQuery();
        $query->update('users u')
            ->innerJoin('profiles p ON p.user_id = u.id')
            ->set('u.name = p.display_name')
            ->where('p.id IS NOT NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('INNER JOIN profiles p', $sql);
    }

    public function testUpdateWithLeftJoin(): void
    {
        $query = new UpdateQuery();
        $query->update('posts p')
            ->leftJoin('users u ON u.id = p.user_id')
            ->set('p.author_name = u.name');

        $sql = $query->toString();

        $this->assertStringContainsString('LEFT JOIN users u', $sql);
    }

    public function testUpdateMultipleTables(): void
    {
        $query = new UpdateQuery();
        $query->update(['users u', 'profiles p'])
            ->set([
                'u.updated_at = NOW()',
                'p.updated_at = NOW()'
            ])
            ->where('u.id = p.user_id');

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE users u, profiles p', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testBindValue(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('name = :name')
            ->where('id = :id')
            ->bindValue(':name', 'John Doe')
            ->bindValue(':id', 123);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertArrayHasKey(':name', $bounded);
        $this->assertArrayHasKey(':id', $bounded);
        $this->assertEquals('John Doe', $bounded[':name']->value);
        $this->assertEquals(123, $bounded[':id']->value);
    }

    public function testBindArray(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set(['name = :name', 'email = :email'])
            ->where('id = :id')
            ->bindArray([
                ':name' => 'Jane',
                ':email' => 'jane@example.com',
                ':id' => 456
            ]);

        $bounded = $query->getBounded();

        $this->assertCount(3, $bounded);
        $this->assertEquals('Jane', $bounded[':name']->value);
        $this->assertEquals('jane@example.com', $bounded[':email']->value);
        $this->assertEquals(456, $bounded[':id']->value);
    }

    public function testUnbindParameter(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('name = :name')
            ->bindValue(':name', 'John')
            ->unbind(':name');

        $bounded = $query->getBounded();

        $this->assertCount(0, $bounded);
    }

    // ========================================
    // COMPLEX QUERY TESTS
    // ========================================

    public function testComplexUpdateWithAllFeatures(): void
    {
        $query = new UpdateQuery();
        $query->update('users u', true)
            ->innerJoin('profiles p ON p.user_id = u.id')
            ->set([
                'u.name = :name',
                'u.updated_at = NOW()',
                'p.last_sync = NOW()'
            ])
            ->where(['u.active = 1', 'p.verified = 1'])
            ->bindValue(':name', 'Updated Name');

        $sql = $query->toString();

        $this->assertStringContainsString('UPDATE IGNORE users u', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('SET', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 50', $sql);
    }

    public function testToStringMagicMethod(): void
    {
        $query = new UpdateQuery();
        $query->update('users')
            ->set('name = :name')
            ->bindValue(':name', 'John');

        $sql = (string)$query;

        $this->assertStringContainsString('UPDATE users', $sql);
    }
}
