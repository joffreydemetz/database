<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\Pdo\PdoDatabase;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PdoDatabase::escape() is pure string work: no connection needed (the driver
 * connects lazily).
 */
class PdoEscapeTest extends TestCase
{
    public static function texts(): array
    {
        return [
            'a quote is doubled'           => ["O'Reilly", false, "O''Reilly"],
            'a backslash is escaped'       => ['C:\\dir', false, 'C:\\\\dir'],
            'a newline is escaped'         => ["a\nb", false, 'a\\nb'],
            'a NUL byte is \0'             => ["a\0b", false, 'a\\0b'],
            'a Ctrl-Z is \Z'               => ["a\x1ab", false, 'a\\Zb'],
            'wildcards kept by default'    => ['50%_off', false, '50%_off'],
            'wildcards escaped for LIKE'   => ['50%_off', true, '50\\%\\_off'],
        ];
    }

    #[DataProvider('texts')]
    public function testEscape(string $text, bool $extra, string $expected): void
    {
        $db = new PdoDatabase($this->getMysqlOptions());

        $this->assertSame($expected, $db->escape($text, $extra));
        $this->assertSame($expected, $db->e($text, $extra));
    }
}
