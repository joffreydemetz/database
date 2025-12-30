<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Michel\Env\DotEnv;
use JDZ\Database\DatabaseInterface;

/**
 * Load environment variables from .env file or set defaults
 */
function loadEnvVariables(): void
{
    $defaults = [
        'JDZ_MYSQL_HOST' => 'localhost',
        'JDZ_MYSQL_DB' => 'test_db',
        'JDZ_MYSQL_USER' => 'root',
        'JDZ_MYSQL_PASS' => 'password',
        'JDZ_MYSQL_PORT' => '3306',

        'JDZ_PGSQL_HOST' => 'localhost',
        'JDZ_PGSQL_DB' => 'test_db',
        'JDZ_PGSQL_USER' => 'root',
        'JDZ_PGSQL_PASS' => 'password',
        'JDZ_PGSQL_PORT' => '5432',

        'JDZ_SQLITE_PATH' => __DIR__ . '/database.sqlite'
    ];

    $envFile = __DIR__ . '/../.env';

    // load user .env file
    if (file_exists($envFile)) {
        (new DotEnv($envFile))->load();
    } else {
        // load default .env file
        $envFile = __DIR__ . '/../.env.dist';

        if (file_exists($envFile)) {
            (new DotEnv($envFile))->load();
        }
    }

    foreach ($defaults as $name => $value) {
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
        }
    }
}

/**
 * Cleanup database tables after example execution
 */
function cleanupDatabase(DatabaseInterface $db, string $driver = 'mysql'): void
{
    try {
        if ($driver === 'sqlite') {
            // Disconnect first
            if ($db && $db->connected()) {
                $db->disconnect();
            }
            // Delete the database file
            if (file_exists(__DIR__ . '/database.sqlite')) {
                unlink(__DIR__ . '/database.sqlite');
            }
            return;
        }

        if (!$db->connected()) {
            throw new \Exception('Database not connected');
        }

        // Get all tables with the app_ prefix
        $tables = $db->getTableList();
        $appTables = array_filter($tables, fn($t) => str_starts_with($t, 'app_'));

        // Drop each table
        foreach ($appTables as $table) {
            try {
                if ($driver === 'postgresql') {
                    $db->setQuery("DROP TABLE IF EXISTS {$table} CASCADE");
                } else {
                    $db->setQuery("DROP TABLE IF EXISTS {$table}");
                }
                $db->execute();
            } catch (\Throwable $e) {
                // Continue even if drop fails
            }
        }
    } catch (\Throwable $e) {
        // Silently fail - database might not be connected
    }
}

loadEnvVariables();
