<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\StringQuery;

class StringQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'the SQL given' => [
                static fn() => new StringQuery('SELECT 1'),
                'SELECT 1',
            ],
            'setQuery() replaces it' => [
                static fn() => (new StringQuery('SELECT 1'))->setQuery('SELECT 2'),
                'SELECT 2',
            ],
            'the table prefix is left to the database' => [
                static fn() => new StringQuery('SELECT * FROM #__users'),
                'SELECT * FROM #__users',
            ],
            'no SQL' => [
                static fn() => new StringQuery(),
                '',
            ],
        ];
    }
}
