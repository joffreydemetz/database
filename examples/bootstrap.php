<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use JDZ\Database\Contract\DatabaseInterface;

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
            $sqlitePath = config('database.sqlite.path', __DIR__ . '/database.sqlite');
            if (file_exists($sqlitePath) && $sqlitePath !== ':memory:') {
                unlink($sqlitePath);
            }
            return;
        }

        if (!$db->connected()) {
            throw new \Exception('Database not connected');
        }

        // Get all tables with the configured prefix
        $tables = $db->getTableList();
        $prefix = config('database.prefix', 'app_');
        $prefixedTables = array_filter($tables, fn($t) => str_starts_with($t, $prefix));

        // Drop each table
        foreach ($prefixedTables as $table) {
            try {
                if ($driver === 'postgresql' || $driver === 'pgsql') {
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
