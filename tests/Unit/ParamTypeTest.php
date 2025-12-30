<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\ParamType;
use JDZ\Database\Tests\TestCase;

class ParamTypeTest extends TestCase
{
    public function testParamTypeConstants(): void
    {
        $this->assertIsInt(ParamType::BOOL->value);
        $this->assertIsInt(ParamType::NULL->value);
        $this->assertIsInt(ParamType::INT->value);
        $this->assertIsInt(ParamType::STR->value);
        $this->assertIsInt(ParamType::LOB->value);
    }

    public function testParamTypeValues(): void
    {
        // These should match PDO constants
        $this->assertEquals(\PDO::PARAM_BOOL, ParamType::BOOL->value);
        $this->assertEquals(\PDO::PARAM_NULL, ParamType::NULL->value);
        $this->assertEquals(\PDO::PARAM_INT, ParamType::INT->value);
        $this->assertEquals(\PDO::PARAM_STR, ParamType::STR->value);
        $this->assertEquals(\PDO::PARAM_LOB, ParamType::LOB->value);
    }

    public function testParamTypeUniqueness(): void
    {
        $types = [
            ParamType::BOOL->value,
            ParamType::NULL->value,
            ParamType::INT->value,
            ParamType::STR->value,
            ParamType::LOB->value
        ];

        // All values should be unique
        $this->assertCount(5, array_unique($types));
    }

    public function testParamTypeIsBackedEnum(): void
    {
        // Test that ParamType is a backed enum
        $this->assertInstanceOf(\BackedEnum::class, ParamType::BOOL);
        $this->assertInstanceOf(\UnitEnum::class, ParamType::INT);
    }

    public function testFromString(): void
    {
        // Test bool variations
        $this->assertSame(ParamType::BOOL, ParamType::fromString('bool'));
        $this->assertSame(ParamType::BOOL, ParamType::fromString('boolean'));

        // Test int variations
        $this->assertSame(ParamType::INT, ParamType::fromString('int'));
        $this->assertSame(ParamType::INT, ParamType::fromString('integer'));
        $this->assertSame(ParamType::INT, ParamType::fromString('i'));

        // Test string variations
        $this->assertSame(ParamType::STR, ParamType::fromString('string'));
        $this->assertSame(ParamType::STR, ParamType::fromString('str'));
        $this->assertSame(ParamType::STR, ParamType::fromString('s'));

        // Test LOB variations
        $this->assertSame(ParamType::LOB, ParamType::fromString('blob'));
        $this->assertSame(ParamType::LOB, ParamType::fromString('lob'));
        $this->assertSame(ParamType::LOB, ParamType::fromString('b'));

        // Test null
        $this->assertSame(ParamType::NULL, ParamType::fromString('null'));

        // Test default (unknown type defaults to STR)
        $this->assertSame(ParamType::STR, ParamType::fromString('unknown'));
    }

    public function testFromStringCaseInsensitive(): void
    {
        $this->assertSame(ParamType::BOOL, ParamType::fromString('BOOL'));
        $this->assertSame(ParamType::INT, ParamType::fromString('INT'));
        $this->assertSame(ParamType::STR, ParamType::fromString('STRING'));
    }
}
