<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\SelectQuery;

class SelectQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'all columns' => [
                static fn() => (new SelectQuery())->select('*')->from('users'),
                self::sql('SELECT *', 'FROM users'),
            ],
            'columns and tables accumulate' => [
                static fn() => (new SelectQuery())->select(['id', 'name'])->select('email')->from('users u')->from(['groups g']),
                self::sql('SELECT id, name, email', 'FROM users u, groups g'),
            ],
            'where conditions joined with AND' => [
                static fn() => (new SelectQuery())->select('*')->from('users')->where(['active = 1', 'age > 18'])->where('deleted = 0'),
                self::sql('SELECT *', 'FROM users', 'WHERE active = 1 AND age > 18 AND deleted = 0'),
            ],
            'where with an OR glue' => [
                static fn() => (new SelectQuery())->select('*')->from('users')->where(['role = :a', 'role = :b'], 'or'),
                self::sql('SELECT *', 'FROM users', 'WHERE role = :a OR role = :b'),
            ],
            'joins in call order' => [
                static fn() => (new SelectQuery())->select('u.id')->from('users u')
                    ->innerJoin('roles r ON r.id = u.id_role')
                    ->leftJoin('posts p ON p.id_user = u.id')
                    ->rightJoin('teams t ON t.id = u.id_team')
                    ->outerJoin('logs l ON l.id_user = u.id')
                    ->join('cross', 'calendar c'),
                self::sql(
                    'SELECT u.id',
                    'FROM users u',
                    'INNER JOIN roles r ON r.id = u.id_role',
                    'LEFT JOIN posts p ON p.id_user = u.id',
                    'RIGHT JOIN teams t ON t.id = u.id_team',
                    'OUTER JOIN logs l ON l.id_user = u.id',
                    'CROSS JOIN calendar c',
                ),
            ],
            'group by and having (AND)' => [
                static fn() => (new SelectQuery())->select(['id_user', 'COUNT(*) AS n'])->from('posts')
                    ->group('id_user')->having(['COUNT(*) > 1', 'MAX(views) > 10']),
                self::sql('SELECT id_user, COUNT(*) AS n', 'FROM posts', 'GROUP BY id_user', 'HAVING COUNT(*) > 1 AND MAX(views) > 10'),
            ],
            'order by accumulates' => [
                static fn() => (new SelectQuery())->select('*')->from('users')->order(['name ASC'])->order('id DESC'),
                self::sql('SELECT *', 'FROM users', 'ORDER BY name ASC, id DESC'),
            ],
            'limit' => [
                static fn() => (new SelectQuery())->select('*')->from('users')->setLimit(10),
                self::sql('SELECT *', 'FROM users LIMIT 10'),
            ],
            'limit and offset' => [
                static fn() => (new SelectQuery())->select('*')->from('users')->setLimit(10, 20),
                self::sql('SELECT *', 'FROM users LIMIT 10 OFFSET 20'),
            ],
            'every clause, in SQL order' => [
                static fn() => (new SelectQuery())
                    ->setLimit(10, 5)
                    ->order('total DESC')
                    ->having('COUNT(p.id) > :min')
                    ->group('u.id')
                    ->where('u.active = 1')
                    ->leftJoin('posts p ON p.id_user = u.id')
                    ->from('#__users u')
                    ->select(['u.id', 'COUNT(p.id) AS total']),
                self::sql(
                    'SELECT u.id, COUNT(p.id) AS total',
                    'FROM #__users u',
                    'LEFT JOIN posts p ON p.id_user = u.id',
                    'WHERE u.active = 1',
                    'GROUP BY u.id',
                    'HAVING COUNT(p.id) > :min',
                    'ORDER BY total DESC LIMIT 10 OFFSET 5',
                ),
            ],
        ];
    }
}
