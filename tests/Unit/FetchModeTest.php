<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\FetchMode;
use JDZ\Database\Tests\TestCase;

class FetchModeTest extends TestCase
{
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
}
