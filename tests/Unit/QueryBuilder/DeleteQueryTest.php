<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\DeleteQuery;

class DeleteQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'one table' => [
                static fn() => (new DeleteQuery())->delete('users')->where('id = :id'),
                self::sql('DELETE', 'FROM users', 'WHERE id = :id'),
            ],
            'delete() and from() accumulate' => [
                static fn() => (new DeleteQuery())->delete('a')->from(['b']),
                self::sql('DELETE', 'FROM a, b'),
            ],
            'where conditions joined with AND' => [
                static fn() => (new DeleteQuery())->delete('#__sessions')->where(['expires < :now', 'id_user = 0']),
                self::sql('DELETE', 'FROM #__sessions', 'WHERE expires < :now AND id_user = 0'),
            ],
            'order and limit' => [
                static fn() => (new DeleteQuery())->delete('logs')->where('ts < :before')->order('ts ASC')->setLimit(1000),
                self::sql('DELETE', 'FROM logs', 'WHERE ts < :before', 'ORDER BY ts ASC', 'LIMIT 1000'),
            ],
        ];
    }
}
