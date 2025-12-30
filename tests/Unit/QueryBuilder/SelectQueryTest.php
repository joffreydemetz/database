<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\SelectQuery;

/**
 * Comprehensive SelectQuery Test Suite
 * 
 * Tests all SelectQuery functionality including:
 * - SELECT clause (columns, expressions, DISTINCT)
 * - FROM clause (single/multiple tables)
 * - WHERE clause (conditions, AND/OR logic)
 * - JOIN clause (INNER, LEFT, RIGHT, CROSS)
 * - GROUP BY clause
 * - HAVING clause
 * - ORDER BY clause
 * - LIMIT/OFFSET clause
 * - Parameter binding
 * - Complex queries
 */
class SelectQueryTest extends TestCase
{
    // ========================================
    // SELECT CLAUSE TESTS
    // ========================================

    public function testSelectAll(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testSelectSingleColumn(): void
    {
        $query = new SelectQuery();
        $query->select('id')->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id', $sql);
    }

    public function testSelectMultipleColumns(): void
    {
        $query = new SelectQuery();
        $query->select(['id', 'name', 'email'])->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id, name, email', $sql);
    }

    public function testSelectWithAliases(): void
    {
        $query = new SelectQuery();
        $query->select(['u.id', 'u.name AS user_name', 'u.email'])->from('users AS u');

        $sql = $query->toString();

        $this->assertStringContainsString('u.name AS user_name', $sql);
    }

    public function testSelectWithExpressions(): void
    {
        $query = new SelectQuery();
        $query->select(['COUNT(*) AS total', 'MAX(age) AS max_age'])->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('COUNT(*)', $sql);
        $this->assertStringContainsString('MAX(age)', $sql);
    }

    public function testSelectDistinct(): void
    {
        $query = new SelectQuery();
        $query->select('DISTINCT country')->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT DISTINCT country', $sql);
    }

    // ========================================
    // FROM CLAUSE TESTS
    // ========================================

    public function testFromSingleTable(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users');

        $sql = $query->toString();

        $this->assertStringContainsString('FROM users', $sql);
    }

    public function testFromTableWithAlias(): void
    {
        $query = new SelectQuery();
        $query->select('u.*')->from('users AS u');

        $sql = $query->toString();

        $this->assertStringContainsString('FROM users AS u', $sql);
    }

    public function testFromMultipleTables(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from(['users', 'posts']);

        $sql = $query->toString();

        $this->assertStringContainsString('FROM users, posts', $sql);
    }

    // ========================================
    // WHERE CLAUSE TESTS
    // ========================================

    public function testWhereSimple(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users')->where('id = 1');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id = 1', $sql);
    }

    public function testWhereMultipleAnd(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where(['id > 10', 'active = 1']);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('id > 10', $sql);
        $this->assertStringContainsString('AND', $sql);
        $this->assertStringContainsString('active = 1', $sql);
    }

    public function testWhereMultipleOr(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where(['role = "admin"', 'role = "moderator"'], 'OR');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testWhereWithParameters(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('id = :id')
            ->bindValue(':id', 123);

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id = :id', $sql);
        $this->assertCount(1, $query->getBounded());
        $this->assertArrayHasKey(':id', $query->getBounded());
    }

    public function testWhereIn(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('id IN (1, 2, 3)');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id IN (1, 2, 3)', $sql);
    }

    public function testWhereLike(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('email LIKE :pattern')
            ->bindValue(':pattern', '%@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('LIKE :pattern', $sql);
    }

    public function testWhereBetween(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('age BETWEEN 18 AND 65');

        $sql = $query->toString();

        $this->assertStringContainsString('BETWEEN 18 AND 65', $sql);
    }

    public function testWhereNull(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('deleted_at IS NULL');

        $sql = $query->toString();

        $this->assertStringContainsString('IS NULL', $sql);
    }

    // ========================================
    // JOIN CLAUSE TESTS
    // ========================================

    public function testInnerJoin(): void
    {
        $query = new SelectQuery();
        $query->select(['u.*', 'p.title'])
            ->from('users u')
            ->innerJoin('posts p ON p.user_id = u.id');

        $sql = $query->toString();

        $this->assertStringContainsString('INNER JOIN posts p ON p.user_id = u.id', $sql);
    }

    public function testLeftJoin(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users u')
            ->leftJoin('posts p ON p.user_id = u.id');

        $sql = $query->toString();

        $this->assertStringContainsString('LEFT JOIN posts p ON p.user_id = u.id', $sql);
    }

    public function testRightJoin(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users u')
            ->rightJoin('posts p ON p.user_id = u.id');

        $sql = $query->toString();

        $this->assertStringContainsString('RIGHT JOIN posts p ON p.user_id = u.id', $sql);
    }

    public function testMultipleJoins(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users u')
            ->innerJoin('posts p ON p.user_id = u.id')
            ->leftJoin('comments c ON c.post_id = p.id');

        $sql = $query->toString();

        $this->assertStringContainsString('INNER JOIN posts p', $sql);
        $this->assertStringContainsString('LEFT JOIN comments c', $sql);
    }

    // ========================================
    // GROUP BY CLAUSE TESTS
    // ========================================

    public function testGroupBySingleColumn(): void
    {
        $query = new SelectQuery();
        $query->select(['user_id', 'COUNT(*) AS total'])
            ->from('posts')
            ->group('user_id');

        $sql = $query->toString();

        $this->assertStringContainsString('GROUP BY user_id', $sql);
    }

    public function testGroupByMultipleColumns(): void
    {
        $query = new SelectQuery();
        $query->select(['user_id', 'status', 'COUNT(*) AS total'])
            ->from('posts')
            ->group(['user_id', 'status']);

        $sql = $query->toString();

        $this->assertStringContainsString('GROUP BY user_id, status', $sql);
    }

    // ========================================
    // HAVING CLAUSE TESTS
    // ========================================

    public function testHavingSimple(): void
    {
        $query = new SelectQuery();
        $query->select(['user_id', 'COUNT(*) AS total'])
            ->from('posts')
            ->group('user_id')
            ->having('COUNT(*) > 5');

        $sql = $query->toString();

        $this->assertStringContainsString('HAVING COUNT(*) > 5', $sql);
    }

    public function testHavingWithParameters(): void
    {
        $query = new SelectQuery();
        $query->select(['user_id', 'COUNT(*) AS post_count'])
            ->from('posts')
            ->group('user_id')
            ->having('COUNT(*) > :minPosts')
            ->bindValue(':minPosts', 10);

        $sql = $query->toString();

        $this->assertStringContainsString('HAVING COUNT(*) > :minPosts', $sql);
        $this->assertArrayHasKey(':minPosts', $query->getBounded());
    }

    // ========================================
    // ORDER BY CLAUSE TESTS
    // ========================================

    public function testOrderBySingleColumn(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users')->order('name ASC');

        $sql = $query->toString();

        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testOrderByMultipleColumns(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->order(['created_at DESC', 'name ASC']);

        $sql = $query->toString();

        $this->assertStringContainsString('ORDER BY created_at DESC, name ASC', $sql);
    }

    // ========================================
    // LIMIT/OFFSET CLAUSE TESTS
    // ========================================

    public function testLimitOnly(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users')->setLimit(10);

        $sql = $query->toString();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testLimitWithOffset(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users')->setLimit(10, 20);

        $sql = $query->toString();

        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testBindValue(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('id = :id')
            ->bindValue(':id', 123);

        $bounded = $query->getBounded();

        $this->assertCount(1, $bounded);
        $this->assertArrayHasKey(':id', $bounded);
        $this->assertEquals(123, $bounded[':id']->value);
    }

    public function testBindMultipleValues(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where(['id > :minId', 'age < :maxAge'])
            ->bindValue(':minId', 10)
            ->bindValue(':maxAge', 50);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertArrayHasKey(':minId', $bounded);
        $this->assertArrayHasKey(':maxAge', $bounded);
    }

    public function testBindArray(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where(['name = :name', 'email = :email'])
            ->bindArray([
                ':name' => 'John Doe',
                ':email' => 'john@example.com'
            ]);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertEquals('John Doe', $bounded[':name']->value);
        $this->assertEquals('john@example.com', $bounded[':email']->value);
    }

    public function testUnbindParameter(): void
    {
        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('id = :id')
            ->bindValue(':id', 123)
            ->unbind(':id');

        $bounded = $query->getBounded();

        $this->assertCount(0, $bounded);
    }

    // ========================================
    // COMPLEX QUERY TESTS
    // ========================================

    public function testComplexQueryWithAllClauses(): void
    {
        $query = new SelectQuery();
        $query->select(['u.id', 'u.name', 'COUNT(p.id) AS post_count', 'SUM(p.views) AS total_views'])
            ->from('users u')
            ->leftJoin('posts p ON p.user_id = u.id')
            ->where(['u.active = 1', 'u.deleted_at IS NULL'])
            ->group('u.id')
            ->having('COUNT(p.id) > :minPosts')
            ->order(['total_views DESC', 'u.name ASC'])
            ->setLimit(10, 0)
            ->bindValue(':minPosts', 5);

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT u.id, u.name', $sql);
        $this->assertStringContainsString('FROM users u', $sql);
        $this->assertStringContainsString('LEFT JOIN posts p', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('GROUP BY u.id', $sql);
        $this->assertStringContainsString('HAVING COUNT(p.id) > :minPosts', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testSubqueryInWhere(): void
    {
        $subquery = new SelectQuery();
        $subquery->select('user_id')->from('posts')->where('status = "published"');

        $query = new SelectQuery();
        $query->select('*')
            ->from('users')
            ->where('id IN (' . $subquery->toString() . ')');

        $sql = $query->toString();

        $this->assertStringContainsString('WHERE id IN (SELECT user_id FROM posts', $sql);
    }

    public function testToStringMagicMethod(): void
    {
        $query = new SelectQuery();
        $query->select('*')->from('users');

        $sql = (string)$query;

        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringContainsString('FROM users', $sql);
    }
}
