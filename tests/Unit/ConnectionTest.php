<?php

namespace JDZ\Database\Tests\Unit;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Mysqli\MysqliConnection;
use JDZ\Database\Pdo\PdoConnection;
use JDZ\Database\Pdo\PdoSqliteConnection;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * The connection classes without a database server: the dbname guard, the PDO
 * DSN they build (seen through the errors the client libraries raise before
 * reaching a server), the SQLite file handling, and how a failed connect() is
 * wrapped. What a live MySQL server receives is in Integration\MysqlConnectionTest.
 */
class ConnectionTest extends TestCase
{
    private string $tmpDir = '';

    protected function tearDown(): void
    {
        if ('' !== $this->tmpDir && is_dir($this->tmpDir)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($this->tmpDir);
        }
    }

    /**
     * A path under the system temp dir, not created yet, removed in tearDown().
     */
    private function tmpPath(): string
    {
        return $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jdz-database-test-' . bin2hex(random_bytes(6));
    }

    // ------------------------------------------------------------ dbname guard

    public static function connectionsWithoutADatabase(): array
    {
        return [
            'PDO'    => ['pdo', static fn() => new PdoConnection('localhost', '', 'root', 'secret'), 'Missing dbname'],
            'MySQLi' => ['mysqli', static fn() => new MysqliConnection('localhost', '', 'root', 'secret'), 'Missing dbname'],
            'SQLite' => ['pdo_sqlite', static fn() => new PdoSqliteConnection(''), 'Missing database file path'],
        ];
    }

    #[DataProvider('connectionsWithoutADatabase')]
    public function testAConnectionNeedsADatabase(string $extension, \Closure $construct, string $message): void
    {
        if (!extension_loaded($extension)) {
            $this->markTestSkipped("{$extension} is not loaded");
        }

        $this->assertThrowsExactly(DatabaseException::class, $message, $construct);
    }

    // ---------------------------------------------------- PdoConnection's DSN

    #[RequiresPhpExtension('pdo_mysql')]
    public function testPdoConnectionSendsTheCharsetToMysql(): void
    {
        // pdo_mysql checks the charset before it opens a socket: no server needed
        $connection = new PdoConnection('127.0.0.1', 'app', 'root', 'secret');
        $connection->charset = 'jdz_no_such_charset';

        $e = $this->assertThrowsExactly(
            DatabaseException::class,
            'Could not connect to PDO: SQLSTATE[HY000] [2019] Unknown character set',
            fn() => $connection->connect()
        );

        $this->assertSame(2019, $e->getCode());
        $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
    }

    #[RequiresPhpExtension('pdo_pgsql')]
    public function testPdoConnectionSendsThePortButNoCharsetToPostgresql(): void
    {
        // libpq rejects an unknown keyword such as charset= first, then an
        // out-of-range port, both before dialling: the error names the port
        $connection = new PdoConnection('127.0.0.1', 'app', 'app', 'secret');
        $connection->driver = 'pgsql';
        $connection->port = -1;
        $connection->charset = 'utf8';

        $e = $this->assertThrowsExactly(
            DatabaseException::class,
            'Could not connect to PDO: SQLSTATE[08006] [7] invalid port number: "-1"',
            fn() => $connection->connect()
        );

        $this->assertSame(7, $e->getCode());
    }

    #[RequiresPhpExtension('pdo_mysql')]
    public function testPdoConnectionWrapsAnUnreachableServer(): void
    {
        $connection = new PdoConnection('127.0.0.1', 'app', 'root', 'secret');
        $connection->port = 1;

        try {
            $connection->connect();
            $this->fail('connect() reached a server on port 1');
        } catch (DatabaseException $e) {
            $this->assertSame(DatabaseException::class, $e::class);
            // what follows is the operating system's own, localised, socket error
            $this->assertStringStartsWith('Could not connect to PDO: SQLSTATE[HY000] [2002] ', $e->getMessage());
            $this->assertSame(2002, $e->getCode());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testPdoConnectionOpensSqliteWithItsAttributesAndForeignKeysOn(): void
    {
        $connection = new PdoConnection('', ':memory:');
        $connection->driver = 'sqlite';

        $pdo = $connection->connect([\PDO::ATTR_CASE => \PDO::CASE_UPPER]);

        $this->assertSame(1, $pdo->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame(['ABC' => 1], $pdo->query('SELECT 1 AS abc')->fetch(\PDO::FETCH_ASSOC));
        $this->assertSame($pdo, $connection->connect(), 'connected once');
    }

    // ----------------------------------------------------- PdoSqliteConnection

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSqliteConnectionCreatesTheMissingDirectory(): void
    {
        $dir = $this->tmpPath() . DIRECTORY_SEPARATOR . 'nested';
        $file = $dir . DIRECTORY_SEPARATOR . 'app.sqlite';

        $connection = new PdoSqliteConnection($file);

        $this->assertDirectoryExists($dir, 'created by the constructor, with its parent');
        $this->assertFileDoesNotExist($file, 'left to connect()');

        $pdo = $connection->connect();

        $this->assertFileExists($file);
        $this->assertSame($pdo, $connection->connect(), 'connected once');
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSqliteConnectionFailsWhenTheDirectoryCannotBeCreated(): void
    {
        $base = $this->tmpPath();
        mkdir($base);
        touch($base . DIRECTORY_SEPARATOR . 'file');
        $dir = $base . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . 'sub';

        // mkdir() under a regular file fails, and raises its own PHP warning
        set_error_handler(static fn(): bool => true, \E_WARNING);

        try {
            $this->assertThrowsExactly(
                DatabaseException::class,
                "Cannot create directory: {$dir}",
                fn() => new PdoSqliteConnection($dir . DIRECTORY_SEPARATOR . 'app.sqlite')
            );
        } finally {
            restore_error_handler();
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSqliteConnectionWrapsAFailedOpen(): void
    {
        $dir = $this->tmpPath();
        $connection = new PdoSqliteConnection($dir . DIRECTORY_SEPARATOR . 'app.sqlite');
        rmdir($dir); // gone between the constructor and connect()

        $e = $this->assertThrowsExactly(
            DatabaseException::class,
            'Could not connect to SQLite: SQLSTATE[HY000] [14] unable to open database file',
            fn() => $connection->connect()
        );

        $this->assertSame(14, $e->getCode());
        $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSqliteConnectionPassesItsAttributesToPdo(): void
    {
        $pdo = (new PdoSqliteConnection(':memory:'))->connect([\PDO::ATTR_CASE => \PDO::CASE_UPPER]);

        $this->assertSame(['ABC' => 1], $pdo->query('SELECT 1 AS abc')->fetch(\PDO::FETCH_ASSOC));
    }
}
