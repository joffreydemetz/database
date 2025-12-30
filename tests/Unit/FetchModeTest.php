<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\FetchMode;
use JDZ\Database\Tests\TestCase;

class FetchModeTest extends TestCase
{
    public function testFetchModeConstants(): void
    {
        $this->assertIsInt(FetchMode::ASSOCIATIVE->value);
        $this->assertIsInt(FetchMode::NUMERIC->value);
        $this->assertIsInt(FetchMode::MIXED->value);
        $this->assertIsInt(FetchMode::STANDARD_OBJECT->value);
        $this->assertIsInt(FetchMode::COLUMN->value);
        $this->assertIsInt(FetchMode::CUSTOM_OBJECT->value);
    }

    public function testFetchModeValues(): void
    {
        // These should match PDO constants
        $this->assertEquals(\PDO::FETCH_ASSOC, FetchMode::ASSOCIATIVE->value);
        $this->assertEquals(\PDO::FETCH_NUM, FetchMode::NUMERIC->value);
        $this->assertEquals(\PDO::FETCH_BOTH, FetchMode::MIXED->value);
        $this->assertEquals(\PDO::FETCH_OBJ, FetchMode::STANDARD_OBJECT->value);
        $this->assertEquals(\PDO::FETCH_COLUMN, FetchMode::COLUMN->value);
        $this->assertEquals(\PDO::FETCH_CLASS, FetchMode::CUSTOM_OBJECT->value);
    }

    public function testFetchModeUniqueness(): void
    {
        $modes = [
            FetchMode::ASSOCIATIVE->value,
            FetchMode::NUMERIC->value,
            FetchMode::MIXED->value,
            FetchMode::STANDARD_OBJECT->value,
            FetchMode::COLUMN->value,
            FetchMode::CUSTOM_OBJECT->value
        ];

        // All values should be unique
        $this->assertCount(6, array_unique($modes));
    }

    public function testFetchModeIsBackedEnum(): void
    {
        // Test that FetchMode is a backed enum
        $this->assertInstanceOf(\BackedEnum::class, FetchMode::ASSOCIATIVE);
        $this->assertInstanceOf(\UnitEnum::class, FetchMode::NUMERIC);
    }
}
