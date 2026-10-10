<?php

namespace JDZ\Database\Tests\Integration;

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Mysqli\MysqliConnection;
use JDZ\Database\Pdo\PdoConnection;
use JDZ\Database\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a live MySQL server receives from the connection classes and from the
 * factory's defaults: the charset (read back as @@character_set_client), the
 * selected database, and how the server's own refusal is wrapped.
 */
class MysqlConnectionTest extends TestCase
{
    private array $options;

    protected function setUp(): void
    {
        if (!$this->isMysqlAvailable()) {
            $this->markTestSkipped('MySQL PDO driver not available');
        }

        $this->options = $this->getMysqlOptions();

        try {
            $this->pdoConnection()->connect();
        } catch (DatabaseException $e) {
            $this->markTestSkipped('MySQL server not available (set JDZ_MYSQL_HOST/PORT/DATABASE/USERNAME/PASSWORD): ' . $e->getMessage());
        }
    }

    private function pdoConnection(?string $dbname = null): PdoConnection
    {
        $connection = new PdoConnection($this->options['host'], $dbname ?? $this->options['dbname'], $this->options['user'], $this->options['pass']);
        $connection->port = $this->options['port'];

        return $connection;
    }

    private function requireMysqli(string $driver = 'mysqli'): void
    {
        if ('mysql' !== $driver && !$this->isMysqliAvailable()) {
            $this->markTestSkipped('MySQLi extension not available');
        }
    }

    // ------------------------------------------------------------ connections

    public function testPdoConnectionSendsTheCharsetAndSelectsTheDatabase(): void
    {
        $connection = $this->pdoConnection();
        $connection->charset = 'latin1';

        $pdo = $connection->connect();

        $this->assertSame(['latin1', $this->options['dbname']], $pdo->query('SELECT @@character_set_client, DATABASE()')->fetch(\PDO::FETCH_NUM));
        $this->assertSame($pdo, $connection->connect(), 'connected once');
    }

    public function testPdoConnectionWrapsTheServersRefusal(): void
    {
        $connection = $this->pdoConnection('jdz_no_such_database');

        try {
            $connection->connect();
            $this->fail('connected to a database that does not exist');
        } catch (DatabaseException $e) {
            $this->assertSame(DatabaseException::class, $e::class);
            // the server's text follows, in the server's lc_messages language
            $this->assertStringStartsWith('Could not connect to PDO: SQLSTATE[HY000] [1049] ', $e->getMessage());
            $this->assertSame(1049, $e->getCode());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testMysqliConnectionSendsTheCharsetAndSelectsTheDatabase(): void
    {
        $this->requireMysqli();

        $connection = new MysqliConnection($this->options['host'], $this->options['dbname'], $this->options['user'], $this->options['pass']);
        $connection->port = $this->options['port'];
        $connection->charset = 'latin1';

        $mysqli = $connection->connect();

        $this->assertSame(['latin1', $this->options['dbname']], $mysqli->query('SELECT @@character_set_client, DATABASE()')->fetch_row());
        $this->assertSame($mysqli, $connection->connect(), 'connected once');
    }

    // ------------------------------------------------------ factory defaults

    public static function factoryCharsets(): array
    {
        // without the factory's default the connections would send utf8 (utf8mb3)
        return [
            'mysql defaults to utf8mb4'   => ['mysql', null, 'utf8mb4'],
            'mysqli defaults to utf8mb4'  => ['mysqli', null, 'utf8mb4'],
            'mariadb defaults to utf8mb4' => ['mariadb', null, 'utf8mb4'],
            'mysql, explicit charset'     => ['mysql', 'latin1', 'latin1'],
            'mysqli, explicit charset'    => ['mysqli', 'latin1', 'latin1'],
        ];
    }

    #[DataProvider('factoryCharsets')]
    public function testTheFactoryCharsetReachesTheServer(string $driver, ?string $charset, string $expected): void
    {
        $this->requireMysqli($driver);

        $options = $this->options;
        unset($options['charset']);
        $options['driver'] = $driver;

        if (null !== $charset) {
            $options['charset'] = $charset;
        }

        $db = DatabaseFactory::create($options);
        $db->setQuery('SELECT @@character_set_client');

        $this->assertSame($expected, $db->loadResult());
    }

    public static function dsnCharsets(): array
    {
        return [
            'mysql, from the DSN query'  => ['mysql', [], 'latin1'],
            'mysqli, from the DSN query' => ['mysqli', [], 'latin1'],
            'an explicit option wins'    => ['mysql', ['charset' => 'ascii'], 'ascii'],
        ];
    }

    #[DataProvider('dsnCharsets')]
    public function testTheDsnCharsetReachesTheServer(string $scheme, array $options, string $expected): void
    {
        $this->requireMysqli($scheme);

        $dsn = sprintf(
            '%s://%s:%s@%s:%d/%s?charset=latin1',
            $scheme,
            rawurlencode($this->options['user']),
            rawurlencode($this->options['pass']),
            $this->options['host'],
            $this->options['port'],
            $this->options['dbname']
        );

        $db = DatabaseFactory::createFromDsn($dsn, $options);
        $db->setQuery('SELECT @@character_set_client, DATABASE()');

        $this->assertSame([$expected, $this->options['dbname']], $db->loadRow());
    }
}
