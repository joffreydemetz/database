<?php

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Mysqli\MysqliDatabase;

if (!DatabaseFactory::isDriverAvailable('mysqli')) {
    echo "✗ MySQLi driver is NOT available\n";
    exit(1);
}

echo "NOTE: This example requires a MySQL server.\n";
echo "You can either:\n";
echo "  1. Start the Docker container: docker compose up -d mysql\n";
echo "  2. Use a local MySQL installation\n\n";

// Try to connect to MySQL
try {
    $db = new MysqliDatabase([
        'driver'    => 'mysqli',
        'host'      => getenv('JDZ_MYSQL_HOST') ?: 'localhost',
        'dbname'    => getenv('JDZ_MYSQL_DB') ?: 'test_db',
        'user'      => getenv('JDZ_MYSQL_USER') ?: 'root',
        'pass'      => getenv('JDZ_MYSQL_PASS') ?: 'password',
        'port'      => getenv('JDZ_MYSQL_PORT') ?: 3306,
        'charset'   => 'utf8mb4',
        'tblprefix' => 'app_',
        'sqlModes'  => [
            'STRICT_TRANS_TABLES',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ENGINE_SUBSTITUTION'
        ]
    ]);
    // Test connection
    $db->connect();
    echo "✓ Connected to MySQL/MariaDB\n";
    echo "\n";
} catch (Exception $e) {
    echo "✗ Could not connect to MySQL: " . $e->getMessage() . "\n\n";
    echo "Please ensure MySQL is running and accessible with the credentials:\n";
    echo "  Host: " . getenv('JDZ_MYSQL_HOST') . ":" . getenv('JDZ_MYSQL_PORT') . "\n";
    echo "  User: " . getenv('JDZ_MYSQL_USER') . "\n";
    echo "  Password: " . getenv('JDZ_MYSQL_PASS') . "\n";
    echo "  Database: " . getenv('JDZ_MYSQL_DB') . "\n";
    echo "\n";
    exit(1);
}

// Clean up existing tables
$db->setQuery("DROP TABLE IF EXISTS app_posts");
$db->execute();
$db->setQuery("DROP TABLE IF EXISTS app_users");
$db->execute();

// Create tables for examples
$createUsersTable = "CREATE TABLE IF NOT EXISTS app_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$db->setQuery($createUsersTable);
$db->execute();

$createPostsTable = "CREATE TABLE IF NOT EXISTS app_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    status VARCHAR(50) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$db->setQuery($createPostsTable);
$db->execute();

echo "\n=== INSERT EXAMPLES ===\n\n";

// Example 1: Basic INSERT
$db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
    ->bindValue(':name', 'John Doe')
    ->bindValue(':email', 'john@example.com');
$db->execute();
$userId1 = $db->insertid();
echo "1. Basic INSERT - User ID: {$userId1}\n";

// Example 2: INSERT using SET syntax
$db->setQuery("INSERT INTO #__users SET name = :name, email = :email")
    ->bindValue(':name', 'Jane Smith')
    ->bindValue(':email', 'jane@example.com');
$db->execute();
$userId2 = $db->insertid();
echo "2. INSERT with SET - User ID: {$userId2}\n";

// Example 3: INSERT with table locking (MySQLi-specific)
try {
    $db->lockTable('#__users');

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Alice Johnson')
        ->bindValue(':email', 'alice@example.com');
    $db->execute();

    $db->unlockTables();
    echo "3. INSERT with table lock - Success\n";
} catch (Exception $e) {
    $db->unlockTables();
    echo "Error: " . $e->getMessage() . "\n";
}

// Insert posts for JOIN examples
$db->setQuery("INSERT INTO #__posts (user_id, title, content, status) VALUES 
    ({$userId1}, 'First Post', 'Content 1', 'published'),
    ({$userId1}, 'Second Post', 'Content 2', 'draft'),
    ({$userId2}, 'Jane''s Post', 'Content 3', 'published')");
$db->execute();

echo "\n=== SELECT EXAMPLES ===\n\n";

// Example 4: Basic SELECT
$db->setQuery("SELECT id, name, email FROM #__users ORDER BY name ASC");
$users = $db->loadObjectList();
echo "4. Basic SELECT - Found " . count($users) . " users\n";

// Example 5: SELECT with JOIN
$db->setQuery("SELECT u.id, u.name, COUNT(p.id) AS post_count
    FROM #__users AS u
    LEFT JOIN #__posts AS p ON u.id = p.user_id
    GROUP BY u.id, u.name
    ORDER BY post_count DESC");
$results = $db->loadObjectList();
echo "5. SELECT with JOIN:\n";
foreach ($results as $result) {
    echo "   - {$result->name}: {$result->post_count} posts\n";
}

// Example 6: Query profiling (MySQLi-specific)
echo "\n=== QUERY PROFILING ===\n\n";
$db->startProfiling();

$db->setQuery("SELECT * FROM #__users");
$db->execute();
$db->setQuery("SELECT * FROM #__posts");
$db->execute();

$profiles = $db->showProfiles();
if ($profiles) {
    foreach ($profiles as $profile) {
        echo "Query: {$profile->Query}\n";
        echo "Duration: {$profile->Duration} seconds\n\n";
    }
}

$db->stopProfiling();

echo "\n=== UPDATE EXAMPLES ===\n\n";

// Example 7: Basic UPDATE
$db->setQuery("UPDATE #__users SET name = :name WHERE id = :id")
    ->bindValue(':name', 'John Doe Updated')
    ->bindValue(':id', $userId1);
$db->execute();
echo "7. Basic UPDATE - Updated user name\n";

echo "\n=== DELETE EXAMPLES ===\n\n";

// Example 8: Basic DELETE
$db->setQuery("DELETE FROM #__posts WHERE status = :status")
    ->bindValue(':status', 'draft');
$db->execute();
echo "8. Basic DELETE - Deleted draft posts\n";

echo "\n=== MYSQL-SPECIFIC FEATURES ===\n\n";

// Get CREATE TABLE statement
$creates = $db->getTableCreate(['app_users']);
foreach ($creates as $table => $sql) {
    echo "CREATE TABLE for {$table}:\n" . substr($sql, 0, 100) . "...\n\n";
}

// Cleanup database
cleanupDatabase($db, 'mysql');

// Explicit disconnect
$db->disconnect();
