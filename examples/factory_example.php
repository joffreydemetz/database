<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;

// Check available drivers on this system
$drivers = DatabaseFactory::getAvailableDrivers();
echo "Available drivers:\n";
print_r($drivers);
echo "\n";

// Check if specific driver is available
if (DatabaseFactory::isDriverAvailable('pgsql')) {
    echo "PostgreSQL driver is available\n";
} else {
    echo "PostgreSQL driver is NOT available\n";
}

if (DatabaseFactory::isDriverAvailable('mysql')) {
    echo "MySQL driver is available\n";
} else {
    echo "MySQL driver is NOT available\n";
}

if (DatabaseFactory::isDriverAvailable('sqlite')) {
    echo "SQLite driver is available\n";
} else {
    echo "SQLite driver is NOT available\n";
}

if (DatabaseFactory::isDriverAvailable('mariadb')) {
    echo "MariaDB driver is available\n";
} else {
    echo "MariaDB driver is NOT available\n";
}

echo "\n";

// Create database connections from configuration arrays
echo "=== Creating Database Instances ===\n\n";

// MySQL - using getDatabaseConfig() helper
$mysql = DatabaseFactory::create(getDatabaseConfig('mysql'));
echo "Created MySQL instance\n";

// PostgreSQL
$pgsql = DatabaseFactory::create(getDatabaseConfig('pgsql'));
echo "Created PostgreSQL instance\n";

// SQLite
$sqlite = DatabaseFactory::create(getDatabaseConfig('sqlite'));
echo "Created SQLite instance\n";

echo "\n";

echo "=== Creating from DSN Strings ===\n\n";

// MySQL DSN
$db = DatabaseFactory::createFromDsn('mysql://root:password@localhost/myapp');
echo "Created from MySQL DSN\n";

// PostgreSQL DSN with port
$db = DatabaseFactory::createFromDsn('pgsql://root:password@localhost:5432/myapp');
echo "Created from PostgreSQL DSN\n";

// SQLite DSN (in-memory)
$db = DatabaseFactory::createFromDsn('sqlite://:memory:');
echo "Created from SQLite DSN (in-memory)\n\n";

echo "=== Multiple Database Connections ===\n\n";

// Using SQLite for demonstration (no server required)
$connections = [
    'main' => DatabaseFactory::create([
        'driver' => 'sqlite',
        'dbname' => ':memory:',
        'tblprefix' => 'main_'
    ]),
    'analytics' => DatabaseFactory::create([
        'driver' => 'sqlite',
        'dbname' => ':memory:',
        'tblprefix' => 'analytics_'
    ]),
    'cache' => DatabaseFactory::create([
        'driver' => 'sqlite',
        'dbname' => ':memory:',
        'tblprefix' => 'cache_'
    ])
];

echo "Created 3 separate database connections:\n";
echo "  - main (SQLite in-memory)\n";
echo "  - analytics (SQLite in-memory)\n";
echo "  - cache (SQLite in-memory)\n\n";
