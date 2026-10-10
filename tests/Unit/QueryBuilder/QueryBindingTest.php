<?php

namespace JDZ\Database\Tests\Unit\QueryBuilder;

use JDZ\Database\ParamType;
use JDZ\Database\Query\StringQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parameter binding lives in the Query base class: tested once, here, rather than
 * once per builder.
 */
class QueryBindingTest extends TestCase
{
    public function testBindValueStoresAParameterTypedAsAString(): void
    {
        $query = (new StringQuery('SELECT * FROM users WHERE name = :name'))->bindValue(':name', 'Jane');

        $param = $query->getBounded()[':name'];
        $this->assertSame(':name', $param->param);
        $this->assertSame('Jane', $param->value);
        $this->assertSame(ParamType::STR->value, $param->dataType);
    }

    public static function types(): array
    {
        return [
            'int'      => ['int', ParamType::INT],
            'integer'  => ['integer', ParamType::INT],
            'bool'     => ['bool', ParamType::BOOL],
            'null'     => ['null', ParamType::NULL],
            'blob'     => ['blob', ParamType::LOB],
            'enum'     => [ParamType::INT, ParamType::INT],
            'unknown'  => ['whatever', ParamType::STR],
        ];
    }

    #[DataProvider('types')]
    public function testTheTypeIsNormalised(string|ParamType $given, ParamType $expected): void
    {
        $query = (new StringQuery())->bindValue(':p', 1, $given instanceof ParamType ? $given->value : $given);

        $this->assertSame($expected->value, $query->getBounded()[':p']->dataType);
    }

    public function testBindParamKeepsLengthAndDriverOptions(): void
    {
        $query = (new StringQuery())->bindParam(':blob', 'data', 'blob', 1024, ['opt' => true]);

        $param = $query->getBounded()[':blob'];
        $this->assertSame(ParamType::LOB->value, $param->dataType);
        $this->assertSame(1024, $param->maxLength);
        $this->assertSame(['opt' => true], $param->driverOptions);
    }

    public function testBindArrayBindsEveryKeyWithOneType(): void
    {
        $query = (new StringQuery())->bindArray([':a' => 1, ':b' => 2], 'int');

        $bounded = $query->getBounded();
        $this->assertSame([':a', ':b'], array_keys($bounded));
        $this->assertSame([1, 2], [$bounded[':a']->value, $bounded[':b']->value]);
        $this->assertSame([ParamType::INT->value, ParamType::INT->value], [$bounded[':a']->dataType, $bounded[':b']->dataType]);
    }

    public function testBindingAKeyAgainReplacesIt(): void
    {
        $query = (new StringQuery())->bindValue(':a', 1)->bindValue(':a', 2);

        $this->assertCount(1, $query->getBounded());
        $this->assertSame(2, $query->getBounded()[':a']->value);
    }

    public function testUnbindOneKeyOrSeveral(): void
    {
        $query = (new StringQuery())->bindArray([':a' => 1, ':b' => 2, ':c' => 3]);

        $query->unbind(':a');
        $this->assertSame([':b', ':c'], array_keys($query->getBounded()));

        $query->unbind([':b', ':c', ':missing']);
        $this->assertSame([], $query->getBounded());
    }
}
