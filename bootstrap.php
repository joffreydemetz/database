<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use JDZ\Utils\Data;

/**
 * Load configuration from config.php (or config.php.dist as fallback)
 *
 * @return Data Configuration object with dot notation access
 */
function loadConfig(): Data
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/config.php';
    $configDistFile = __DIR__ . '/config.php.dist';

    if (file_exists($configFile)) {
        $data = require $configFile;
    } elseif (file_exists($configDistFile)) {
        $data = require $configDistFile;
    } else {
        $data = [];
    }

    $config = new Data();
    $config->sets($data);

    return $config;
}

/**
 * Get configuration value using dot notation
 *
 * @param string $path Dot notation path (e.g., 'database.mysql.host')
 * @param mixed $default Default value if path not found
 * @return mixed
 */
function config(string $path, mixed $default = null): mixed
{
    return loadConfig()->get($path, $default);
}

/**
 * Get database configuration array for the specified driver
 *
 * @param string|null $driver Driver name (defaults to configured driver)
 * @return array Database configuration array for DatabaseFactory::create()
 */
function getDatabaseConfig(?string $driver = null): array
{
    $driver = $driver ?? config('database.driver', 'sqlite');
    $prefix = config('database.prefix', '');

    return match ($driver) {
        'sqlite' => [
            'driver' => 'sqlite',
            'dbname' => config('database.sqlite.path', ':memory:'),
            'tblprefix' => $prefix,
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => config('database.mysql.host', 'localhost'),
            'port' => config('database.mysql.port', 3306),
            'user' => config('database.mysql.user', 'root'),
            'pass' => config('database.mysql.password', ''),
            'dbname' => config('database.mysql.database', 'test_db'),
            'tblprefix' => $prefix,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => config('database.pgsql.host', 'localhost'),
            'port' => config('database.pgsql.port', 5432),
            'user' => config('database.pgsql.user', 'root'),
            'pass' => config('database.pgsql.password', ''),
            'dbname' => config('database.pgsql.database', 'test_db'),
            'tblprefix' => $prefix,
        ],
        'mariadb' => [
            'driver' => 'mariadb',
            'host' => config('database.mariadb.host', 'localhost'),
            'port' => config('database.mariadb.port', 3306),
            'user' => config('database.mariadb.user', 'root'),
            'pass' => config('database.mariadb.password', ''),
            'dbname' => config('database.mariadb.database', 'test_db'),
            'tblprefix' => $prefix,
        ],
        default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
    };
}
