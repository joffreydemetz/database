<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\InsertQuery;

class InsertQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'one row with a column list' => [
                static fn() => (new InsertQuery())->insert('users')->columns(['name', 'email'])->values(':name, :email'),
                self::sql('INSERT INTO users', '(name, email)', 'VALUES (:name, :email)'),
            ],
            'several rows, columns accumulate' => [
                static fn() => (new InsertQuery())->insert('users')->columns('name')->columns(['email'])->values([':n1, :e1', ':n2, :e2']),
                self::sql('INSERT INTO users', '(name, email)', 'VALUES (:n1, :e1), (:n2, :e2)'),
            ],
            'no column list' => [
                static fn() => (new InsertQuery())->insert('logs')->values("NOW(), 'started'"),
                self::sql('INSERT INTO logs', "VALUES (NOW(), 'started')"),
            ],
            'ignore' => [
                static fn() => (new InsertQuery())->insert('users', true)->columns('name')->values(':name'),
                self::sql('INSERT IGNORE INTO users', '(name)', 'VALUES (:name)'),
            ],
            'SET syntax' => [
                static fn() => (new InsertQuery())->insert('#__users')->set(['name = :name', 'email = :email']),
                self::sql('INSERT INTO #__users', 'SET name = :name, email = :email'),
            ],
            'SET wins over VALUES' => [
                static fn() => (new InsertQuery())->insert('users')->columns('name')->values(':n')->set('name = :name'),
                self::sql('INSERT INTO users', 'SET name = :name'),
            ],
            'a table only' => [
                static fn() => (new InsertQuery())->insert('users'),
                'INSERT INTO users',
            ],
        ];
    }
}
