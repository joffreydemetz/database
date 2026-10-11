<?php
declare(strict_types=1);

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\Exception\QueryException;
use JDZ\Database\Query\DeleteQuery;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\UpdateQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.3.0 (D6): LIMIT on its own line like every other clause, an offset without a limit kept (MySQL's idiom).
 */
class LimitTest extends TestCase
{
    private static function sql(string ...$lines): string
    {
        return implode(PHP_EOL, $lines);
    }
    public static function limits(): array
    {
        return [
            'limit' => [10, 0, self::sql('SELECT *', 'FROM t', 'LIMIT 10')],
            'limit and offset' => [10, 20, self::sql('SELECT *', 'FROM t', 'LIMIT 10 OFFSET 20')],
            'offset alone: all the rows after it' => [0, 20, self::sql('SELECT *', 'FROM t', 'LIMIT 18446744073709551615 OFFSET 20')],
            'nothing' => [0, 0, self::sql('SELECT *', 'FROM t')],
            'a negative limit is no limit' => [-5, 0, self::sql('SELECT *', 'FROM t')],
            'a negative offset is no offset' => [10, -5, self::sql('SELECT *', 'FROM t', 'LIMIT 10')],
        ];
    }

    #[DataProvider('limits')]
    public function testLimitAndOffset(int $limit, int $offset, string $expected): void
    {
        $this->assertSame($expected, (new SelectQuery())->select('*')->from('t')->setLimit($limit, $offset)->toString());
    }
}
