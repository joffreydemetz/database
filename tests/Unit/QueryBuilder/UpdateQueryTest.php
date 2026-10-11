<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\UpdateQuery;

class UpdateQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'one table' => [
                static fn() => (new UpdateQuery())->update('users')->set(['name = :name'])->where('id = :id'),
                self::sql('UPDATE users', 'SET name = :name', 'WHERE id = :id'),
            ],
            'set accumulates' => [
                static fn() => (new UpdateQuery())->update('users')->set('a = 1')->set(['b = 2', 'c = 3']),
                self::sql('UPDATE users', 'SET a = 1, b = 2, c = 3'),
            ],
            'ignore, several tables' => [
                static fn() => (new UpdateQuery())->update(['users u', 'profiles p'], true)->set('u.name = p.name')->where('u.id = p.id_user'),
                self::sql('UPDATE IGNORE users u, profiles p', 'SET u.name = p.name', 'WHERE u.id = p.id_user'),
            ],
            'a join comes before SET' => [
                static fn() => (new UpdateQuery())->update('users u')->innerJoin('roles r ON r.id = u.id_role')->set('u.level = r.level'),
                self::sql('UPDATE users u', 'INNER JOIN roles r ON r.id = u.id_role', 'SET u.level = r.level'),
            ],
            'order and limit' => [
                static fn() => (new UpdateQuery())->update('jobs')->set('done = 1')->where('done = 0')->order('id ASC')->setLimit(100),
                self::sql('UPDATE jobs', 'SET done = 1', 'WHERE done = 0', 'ORDER BY id ASC', 'LIMIT 100'),
            ],
        ];
    }
}
