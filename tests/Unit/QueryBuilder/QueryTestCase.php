<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Builder tests compare the whole generated SQL, so clause order, separators and
 * glue are pinned, not just fragments.
 *
 * A subclass provides queries(): rows of [a closure building the query, the exact
 * SQL]. Clauses are joined with PHP_EOL (LIMIT follows with a space): build the
 * expected SQL with sql().
 */
abstract class QueryTestCase extends TestCase
{
    /** @return array<string, array{0: Closure, 1: string}> */
    abstract public static function queries(): array;

    protected static function sql(string ...$lines): string
    {
        return implode(PHP_EOL, $lines);
    }

    #[DataProvider('queries')]
    public function testRendersTheExactSql(Closure $build, string $expected): void
    {
        $query = $build();

        $this->assertSame($expected, $query->toString());
        $this->assertSame($expected, (string)$query);
    }
}
