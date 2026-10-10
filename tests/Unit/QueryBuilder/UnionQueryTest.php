<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\StringQuery;
use JDZ\Database\Query\UnionQuery;
use JDZ\Database\ParamType;

class UnionQueryTest extends QueryTestCase
{
    public static function queries(): array
    {
        return [
            'UNION ALL by default' => [
                static fn() => (new UnionQuery())->addQuery('SELECT id FROM a')->addQuery('SELECT id FROM b'),
                self::sql('SELECT id FROM a', 'UNION ALL', 'SELECT id FROM b'),
            ],
            'UNION (distinct)' => [
                static fn() => (new UnionQuery())->addQuery('SELECT id FROM a')->addQueryDistinct('SELECT id FROM b'),
                self::sql('SELECT id FROM a', 'UNION', 'SELECT id FROM b'),
            ],
            'a builder is inlined on one line' => [
                static fn() => (new UnionQuery())
                    ->addQuery((new SelectQuery())->select('id')->from('a')->where('x = 1'))
                    ->addQuery(new StringQuery('SELECT id FROM b')),
                self::sql('SELECT id FROM a WHERE x = 1', 'UNION ALL', 'SELECT id FROM b'),
            ],
            'order and limit apply to the whole union' => [
                static fn() => (new UnionQuery())->addQuery('SELECT id FROM a')->addQuery('SELECT id FROM b')->order('id DESC')->setLimit(5),
                self::sql('SELECT id FROM a', 'UNION ALL', 'SELECT id FROM b', 'ORDER BY id DESC LIMIT 5'),
            ],
            'one query' => [
                static fn() => (new UnionQuery())->addQuery('SELECT id FROM a'),
                'SELECT id FROM a',
            ],
            'no query' => [
                static fn() => new UnionQuery(),
                '',
            ],
        ];
    }

    public function testCollectsTheParametersOfItsQueries(): void
    {
        $a = (new SelectQuery())->select('id')->from('a')->where('x = :x')->bindValue(':x', 1, 'int');
        $b = (new StringQuery('SELECT id FROM b WHERE y = :y'))->bindValue(':y', 'two');

        $union = (new UnionQuery())->addQuery($a)->addQuery($b);

        $bounded = $union->getBounded();
        $this->assertSame([':x', ':y'], array_keys($bounded));
        $this->assertSame(ParamType::INT->value, $bounded[':x']->dataType);
        $this->assertSame('two', $bounded[':y']->value);
    }
}
