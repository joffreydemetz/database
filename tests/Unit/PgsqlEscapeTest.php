<?php
declare(strict_types=1);

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\Pdo\PdoPostgresqlDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.3.0 (D6): PostgreSQL escaping follows pg_escape_string() under
 * standard_conforming_strings = on (the default since 9.1): only the quote is
 * doubled, a backslash is a plain character — it used to be doubled too.
 */
class PgsqlEscapeTest extends TestCase
{
    public static function texts(): array
    {
        return [
            'a quote' => ["O'Reilly", false, "O''Reilly"],
            'a backslash' => ['C:\\path', false, 'C:\\path'],
            'a windows path with quotes' => ["C:\\it's", false, "C:\\it''s"],
            'a newline is kept' => ["a\nb", false, "a\nb"],
            'like wildcards with extra' => ['100%_x', true, '100\\%\\_x'],
            'nothing' => ['', false, ''],
        ];
    }

    #[DataProvider('texts')]
    public function testEscape(string $text, bool $extra, string $expected): void
    {
        $db = new PdoPostgresqlDatabase(['dbname' => 'app', 'tblprefix' => '']);

        $this->assertSame($expected, $db->escape($text, $extra));
    }
}
