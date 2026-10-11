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
 * 2.3.0 (D6): a WHERE glue that changes between calls is refused (it used to be last-call-wins, silently).
 */
class WhereGlueTest extends TestCase
{
    private static function sql(string ...$lines): string
    {
        return implode(PHP_EOL, $lines);
    }
    public static function builders(): array
    {
        return [
            'select' => [static fn() => (new SelectQuery())->select('*')->from('t')],
            'update' => [static fn() => (new UpdateQuery())->update('t')->set('a = 1')],
            'delete' => [static fn() => (new DeleteQuery())->delete('t')],
        ];
    }

    #[DataProvider('builders')]
    public function testTheGlueOfOneCallGluesEveryCondition(\Closure $make): void
    {
        $query = $make()->where(['a = 1', 'b = 2'], 'or')->where('c = 3', 'OR');

        $this->assertStringEndsWith(PHP_EOL . 'WHERE a = 1 OR b = 2 OR c = 3', $query->toString());
    }

    #[DataProvider('builders')]
    public function testAChangedGlueIsRefused(\Closure $make): void
    {
        $query = $make()->where('a = 1', 'OR');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('WHERE glue');

        $query->where('b = 2', 'AND');
    }

    #[DataProvider('builders')]
    public function testTheDefaultGlueIsAndAndStaysSo(\Closure $make): void
    {
        $query = $make()->where('a = 1')->where('b = 2', 'and');

        $this->assertStringEndsWith(PHP_EOL . 'WHERE a = 1 AND b = 2', $query->toString());
    }

    public function testAnUnknownGlueIsRefused(): void
    {
        $this->expectException(QueryException::class);

        (new SelectQuery())->select('*')->from('t')->where('a = 1', 'XOR');
    }
}
