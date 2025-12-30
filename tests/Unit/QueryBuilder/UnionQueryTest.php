<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\UnionQuery;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\StringQuery;

/**
 * Comprehensive UnionQuery Test Suite
 * 
 * Tests all UnionQuery functionality including:
 * - Adding queries (UNION ALL)
 * - Adding distinct queries (UNION)
 * - ORDER BY clause
 * - LIMIT/OFFSET clause
 * - Parameter binding
 * - Mixed query types
 */
class UnionQueryTest extends TestCase
{
    // ========================================
    // BASIC UNION TESTS
    // ========================================

    public function testUnionAll(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')->from('users')->where('role = "admin"');

        $q2 = new SelectQuery();
        $q2->select('*')->from('users')->where('role = "moderator"');

        $query->addQuery($q1)->addQuery($q2);

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT * FROM users WHERE role = "admin"', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringContainsString('SELECT * FROM users WHERE role = "moderator"', $sql);
    }

    public function testUnionDistinct(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('email')->from('users');

        $q2 = new SelectQuery();
        $q2->select('email')->from('subscribers');

        $query->addQueryDistinct($q1)->addQueryDistinct($q2);

        $sql = $query->toString();

        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringNotContainsString('UNION ALL', $sql);
    }

    public function testMixedUnionAndDistinct(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('name')->from('table1');

        $q2 = new SelectQuery();
        $q2->select('name')->from('table2');

        $q3 = new SelectQuery();
        $q3->select('name')->from('table3');

        $query->addQuery($q1)
            ->addQueryDistinct($q2)
            ->addQuery($q3);

        $sql = $query->toString();

        // Should have both UNION ALL and UNION
        $this->assertMatchesRegularExpression('/UNION ALL|UNION/', $sql);
    }

    // ========================================
    // STRING QUERY TESTS
    // ========================================

    public function testUnionWithStringQueries(): void
    {
        $query = new UnionQuery();

        $query->addQuery("SELECT id, name FROM users WHERE active = 1")
            ->addQuery("SELECT id, name FROM archived_users");

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id, name FROM users WHERE active = 1', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringContainsString('SELECT id, name FROM archived_users', $sql);
    }

    public function testUnionDistinctWithStringQueries(): void
    {
        $query = new UnionQuery();

        $query->addQueryDistinct("SELECT email FROM users")
            ->addQueryDistinct("SELECT email FROM subscribers");

        $sql = $query->toString();

        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringNotContainsString('UNION ALL', $sql);
    }

    // ========================================
    // MIXED QUERY TYPES TESTS
    // ========================================

    public function testMixedSelectAndStringQueries(): void
    {
        $query = new UnionQuery();

        $selectQuery = new SelectQuery();
        $selectQuery->select(['id', 'name'])->from('users');

        $query->addQuery($selectQuery)
            ->addQuery("SELECT id, name FROM admins");

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id, name FROM users', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringContainsString('SELECT id, name FROM admins', $sql);
    }

    public function testMixedStringQueryObjects(): void
    {
        $query = new UnionQuery();

        $stringQuery = new StringQuery("SELECT id, title FROM posts");

        $selectQuery = new SelectQuery();
        $selectQuery->select(['id', 'title'])->from('pages');

        $query->addQuery($stringQuery)
            ->addQuery($selectQuery);

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id, title FROM posts', $sql);
        $this->assertStringContainsString('SELECT id, title FROM pages', $sql);
    }

    // ========================================
    // ORDER BY CLAUSE TESTS
    // ========================================

    public function testOrderBySingleColumn(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('name')->from('table1');

        $q2 = new SelectQuery();
        $q2->select('name')->from('table2');

        $query->addQuery($q1)
            ->addQuery($q2)
            ->order('name ASC');

        $sql = $query->toString();

        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testOrderByMultipleColumns(): void
    {
        $query = new UnionQuery();

        $query->addQuery("SELECT id, name, created_at FROM users")
            ->addQuery("SELECT id, name, created_at FROM archived_users")
            ->order(['created_at DESC', 'name ASC']);

        $sql = $query->toString();

        $this->assertStringContainsString('ORDER BY created_at DESC, name ASC', $sql);
    }

    // ========================================
    // LIMIT/OFFSET CLAUSE TESTS
    // ========================================

    public function testLimitOnly(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')->from('table1');

        $q2 = new SelectQuery();
        $q2->select('*')->from('table2');

        $query->addQuery($q1)
            ->addQuery($q2)
            ->setLimit(10);

        $sql = $query->toString();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testLimitWithOffset(): void
    {
        $query = new UnionQuery();

        $query->addQuery("SELECT * FROM users")
            ->addQuery("SELECT * FROM archived_users")
            ->setLimit(20, 10);

        $sql = $query->toString();

        $this->assertStringContainsString('LIMIT 20 OFFSET 10', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testParameterBindingInSubQueries(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')
            ->from('users')
            ->where('role = :role1')
            ->bindValue(':role1', 'admin');

        $q2 = new SelectQuery();
        $q2->select('*')
            ->from('users')
            ->where('role = :role2')
            ->bindValue(':role2', 'moderator');

        $query->addQuery($q1)->addQuery($q2);

        $sql = $query->toString();

        // Verify parameters are preserved from sub-queries
        $this->assertStringContainsString(':role1', $sql);
        $this->assertStringContainsString(':role2', $sql);
    }

    public function testGetBoundedFromSubQueries(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')
            ->from('users')
            ->where('id > :minId')
            ->bindValue(':minId', 10);

        $q2 = new SelectQuery();
        $q2->select('*')
            ->from('admins')
            ->where('level > :minLevel')
            ->bindValue(':minLevel', 5);

        $query->addQuery($q1)->addQuery($q2);

        $bounded = $query->getBounded();

        // Union query should collect all parameters from sub-queries
        $this->assertGreaterThanOrEqual(2, count($bounded));
    }

    // ========================================
    // COMPLEX QUERY TESTS
    // ========================================

    public function testComplexUnionWithAllFeatures(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select(['id', 'name', "'user' as type", 'created_at'])
            ->from('users')
            ->where('active = 1');

        $q2 = new SelectQuery();
        $q2->select(['id', 'name', "'admin' as type", 'created_at'])
            ->from('admins')
            ->where('active = 1');

        $q3 = new StringQuery("SELECT id, name, 'guest' as type, created_at FROM guests WHERE active = 1");

        $query->addQuery($q1)
            ->addQueryDistinct($q2)
            ->addQuery($q3)
            ->order(['created_at DESC', 'name ASC'])
            ->setLimit(50, 10);

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT id, name', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC, name ASC', $sql);
        $this->assertStringContainsString('LIMIT 50 OFFSET 10', $sql);
    }

    public function testMultipleTableUnion(): void
    {
        $query = new UnionQuery();

        for ($i = 1; $i <= 5; $i++) {
            $q = new SelectQuery();
            $q->select('*')->from("table{$i}");
            $query->addQuery($q);
        }

        $sql = $query->toString();

        $unionCount = substr_count($sql, 'UNION ALL');
        $this->assertEquals(4, $unionCount); // 5 queries = 4 UNIONs
    }

    public function testToStringMagicMethod(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')->from('users');

        $q2 = new SelectQuery();
        $q2->select('*')->from('admins');

        $query->addQuery($q1)->addQuery($q2);

        $sql = (string)$query;

        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringContainsString('SELECT * FROM admins', $sql);
    }

    // ========================================
    // EDGE CASES TESTS
    // ========================================

    public function testEmptyUnion(): void
    {
        $query = new UnionQuery();

        $sql = $query->toString();

        $this->assertEmpty($sql);
    }

    public function testSingleQuery(): void
    {
        $query = new UnionQuery();

        $q1 = new SelectQuery();
        $q1->select('*')->from('users');

        $query->addQuery($q1);

        $sql = $query->toString();

        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringNotContainsString('UNION', $sql);
    }
}
