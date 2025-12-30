<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Tests\TestCase;
use JDZ\Database\Query\InsertQuery;

/**
 * Comprehensive InsertQuery Test Suite
 * 
 * Tests all InsertQuery functionality including:
 * - INSERT INTO clause
 * - IGNORE modifier
 * - Column specification
 * - Values (single and multiple rows)
 * - SET syntax
 * - Parameter binding
 */
class InsertQueryTest extends TestCase
{
    // ========================================
    // BASIC INSERT TESTS
    // ========================================

    public function testInsertBasic(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email'])
            ->values(':name, :email')
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT INTO users', $sql);
        $this->assertStringContainsString('(name, email)', $sql);
        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString(':name, :email', $sql);
    }

    public function testInsertWithTablePrefix(): void
    {
        $query = new InsertQuery();
        $query->insert('#__users')
            ->columns(['name'])
            ->values(':name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT INTO #__users', $sql);
    }

    // ========================================
    // INSERT IGNORE TESTS
    // ========================================

    public function testInsertIgnore(): void
    {
        $query = new InsertQuery();
        $query->insert('users', true)
            ->columns(['name', 'email'])
            ->values(':name, :email')
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT IGNORE INTO users', $sql);
    }

    // ========================================
    // COLUMNS AND VALUES TESTS
    // ========================================

    public function testSingleColumn(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns('name')
            ->values(':name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('(name)', $sql);
    }

    public function testMultipleColumns(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email', 'age'])
            ->values(':name, :email, :age')
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30);

        $sql = $query->toString();

        $this->assertStringContainsString('(name, email, age)', $sql);
        $this->assertCount(3, $query->getBounded());
    }

    public function testMultipleRows(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email'])
            ->values([
                ':name1, :email1',
                ':name2, :email2',
                ':name3, :email3'
            ])
            ->bindValue(':name1', 'John')
            ->bindValue(':email1', 'john@example.com')
            ->bindValue(':name2', 'Jane')
            ->bindValue(':email2', 'jane@example.com')
            ->bindValue(':name3', 'Bob')
            ->bindValue(':email3', 'bob@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString(':name1, :email1', $sql);
        $this->assertStringContainsString(':name2, :email2', $sql);
        $this->assertStringContainsString(':name3, :email3', $sql);
        $this->assertCount(6, $query->getBounded());
    }

    public function testValuesWithLiterals(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email', 'status'])
            ->values(":name, :email, 'active'")
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com');

        $sql = $query->toString();

        $this->assertStringContainsString("'active'", $sql);
    }

    // ========================================
    // SET SYNTAX TESTS
    // ========================================

    public function testSetSyntaxSingle(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->set('name = :name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('SET', $sql);
        $this->assertStringContainsString('name = :name', $sql);
        $this->assertStringNotContainsString('VALUES', $sql);
    }

    public function testSetSyntaxMultiple(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->set([
                'name = :name',
                'email = :email',
                'age = :age'
            ])
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30);

        $sql = $query->toString();

        $this->assertStringContainsString('SET', $sql);
        $this->assertStringContainsString('name = :name', $sql);
        $this->assertStringContainsString('email = :email', $sql);
        $this->assertStringContainsString('age = :age', $sql);
        $this->assertCount(3, $query->getBounded());
    }

    public function testSetSyntaxWithLiterals(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->set([
                'name = :name',
                "status = 'active'",
                'created_at = NOW()'
            ])
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString("status = 'active'", $sql);
        $this->assertStringContainsString('created_at = NOW()', $sql);
    }

    // ========================================
    // PARAMETER BINDING TESTS
    // ========================================

    public function testBindValue(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name'])
            ->values(':name')
            ->bindValue(':name', 'John Doe');

        $bounded = $query->getBounded();

        $this->assertCount(1, $bounded);
        $this->assertArrayHasKey(':name', $bounded);
        $this->assertEquals('John Doe', $bounded[':name']->value);
    }

    public function testBindMultipleValues(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email', 'age'])
            ->values(':name, :email, :age')
            ->bindValue(':name', 'John')
            ->bindValue(':email', 'john@example.com')
            ->bindValue(':age', 30);

        $bounded = $query->getBounded();

        $this->assertCount(3, $bounded);
        $this->assertEquals('John', $bounded[':name']->value);
        $this->assertEquals('john@example.com', $bounded[':email']->value);
        $this->assertEquals(30, $bounded[':age']->value);
    }

    public function testBindArray(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email'])
            ->values(':name, :email')
            ->bindArray([
                ':name' => 'Jane Smith',
                ':email' => 'jane@example.com'
            ]);

        $bounded = $query->getBounded();

        $this->assertCount(2, $bounded);
        $this->assertEquals('Jane Smith', $bounded[':name']->value);
        $this->assertEquals('jane@example.com', $bounded[':email']->value);
    }

    public function testUnbindParameter(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name'])
            ->values(':name')
            ->bindValue(':name', 'John')
            ->unbind(':name');

        $bounded = $query->getBounded();

        $this->assertCount(0, $bounded);
    }

    // ========================================
    // EDGE CASES AND VALIDATION TESTS
    // ========================================

    public function testInsertWithoutColumns(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->set('name = :name')
            ->bindValue(':name', 'John');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT INTO users', $sql);
        $this->assertStringContainsString('SET', $sql);
    }

    public function testComplexInsertWithAllFeatures(): void
    {
        $query = new InsertQuery();
        $query->insert('users', true)
            ->columns(['name', 'email', 'age', 'status'])
            ->values([
                ':name1, :email1, :age1, :status1',
                ':name2, :email2, :age2, :status2'
            ])
            ->bindValue(':name1', 'John Doe')
            ->bindValue(':email1', 'john@example.com')
            ->bindValue(':age1', 30)
            ->bindValue(':status1', 'active')
            ->bindValue(':name2', 'Jane Smith')
            ->bindValue(':email2', 'jane@example.com')
            ->bindValue(':age2', 25)
            ->bindValue(':status2', 'active');

        $sql = $query->toString();

        $this->assertStringContainsString('INSERT IGNORE INTO users', $sql);
        $this->assertStringContainsString('(name, email, age, status)', $sql);
        $this->assertStringContainsString('VALUES', $sql);
        $this->assertCount(8, $query->getBounded());
    }

    public function testToStringMagicMethod(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name'])
            ->values(':name')
            ->bindValue(':name', 'John');

        $sql = (string)$query;

        $this->assertStringContainsString('INSERT INTO users', $sql);
    }

    // ========================================
    // SPACING AND FORMATTING TESTS
    // ========================================

    public function testColumnsSpacing(): void
    {
        $query = new InsertQuery();
        $query->insert('users')
            ->columns(['name', 'email'])
            ->values(':name, :email');

        $sql = $query->toString();

        // Ensure there's proper spacing in column list
        $this->assertStringContainsString('(name, email)', $sql);
    }
}
