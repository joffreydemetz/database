<?php

/**
 * MySQL Example - Using String Queries
 * 
 * This example demonstrates basic database operations with MySQL using string queries.
 * For comprehensive Query Builder examples (SelectQuery, InsertQuery, etc.), 
 * see query_builder_example.php
 */

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Pdo\PdoDatabase;

if (!DatabaseFactory::isDriverAvailable('mysql')) {
    echo "✗ MySQL driver is NOT available\n";
    exit(1);
}

echo "NOTE: This example requires a MySQL server.\n";
echo "You can either:\n";
echo "  1. Start the Docker container: docker compose up -d mysql\n";
echo "  2. Use a local MySQL installation\n";
echo "\n";

// Try to connect to MySQL
try {
    $db = new PdoDatabase([
        'driver'    => 'mysql',
        'host'      => getenv('JDZ_MYSQL_HOST') ?: 'localhost',
        'dbname'    => getenv('JDZ_MYSQL_DB') ?: 'test_db',
        'user'      => getenv('JDZ_MYSQL_USER') ?: 'root',
        'pass'      => getenv('JDZ_MYSQL_PASS') ?: 'password',
        'port'      => getenv('JDZ_MYSQL_PORT') ?: 3306,
        'charset'   => 'utf8mb4',
        'tblprefix' => 'app_'
    ]);

    // Test connection
    $db->connect();
    echo "✓ Connected to MySQL\n";
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

echo "=== INSERT EXAMPLES ===\n\n";

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

// Example 3: INSERT IGNORE
$db->setQuery("INSERT IGNORE INTO #__users (name, email) VALUES (:name, :email)")
    ->bindValue(':name', 'Duplicate User')
    ->bindValue(':email', 'john@example.com');
$db->execute();
echo "3. INSERT IGNORE - Ignored duplicate email\n";

// Example 4: INSERT multiple rows
$db->setQuery("INSERT INTO #__users (name, email) VALUES 
    ('Alice Johnson', 'alice@example.com'),
    ('Bob Wilson', 'bob@example.com')");
$db->execute();
echo "4. INSERT multiple rows - Inserted 2 users\n";

// Insert posts for JOIN examples
$db->setQuery("INSERT INTO #__posts (user_id, title, content, status) VALUES 
    ({$userId1}, 'First Post', 'Content 1', 'published'),
    ({$userId1}, 'Second Post', 'Content 2', 'draft'),
    ({$userId2}, 'Jane''s Post', 'Content 3', 'published')");
$db->execute();

echo "\n=== SELECT EXAMPLES ===\n\n";

// Example 5: Basic SELECT
$db->setQuery("SELECT id, name, email FROM #__users ORDER BY name ASC");
$users = $db->loadObjectList();
echo "5. Basic SELECT - Found " . count($users) . " users\n";

// Example 6: SELECT with WHERE
$db->setQuery("SELECT id, name, email FROM #__users WHERE id = :id")
    ->bindValue(':id', $userId1);
$user = $db->loadObject();
echo "6. SELECT with WHERE - User: {$user->name}\n";

// Example 7: SELECT with multiple WHERE
$db->setQuery("SELECT id, name, email FROM #__users WHERE id > :minId AND email LIKE :pattern")
    ->bindValue(':minId', 0)
    ->bindValue(':pattern', '%@example.com');
$users = $db->loadObjectList();
echo "7. SELECT with multiple WHERE - Found " . count($users) . " users\n";

// Example 8: SELECT with INNER JOIN
$db->setQuery("SELECT u.id, u.name, p.title, p.status 
    FROM #__users AS u
    INNER JOIN #__posts AS p ON u.id = p.user_id
    ORDER BY u.name ASC, p.created_at DESC");
$results = $db->loadObjectList();
echo "8. SELECT with INNER JOIN - Found " . count($results) . " user-post combinations\n";

// Example 9: SELECT with LEFT JOIN and aggregation
$db->setQuery("SELECT u.id, u.name, COUNT(p.id) AS post_count
    FROM #__users AS u
    LEFT JOIN #__posts AS p ON u.id = p.user_id
    GROUP BY u.id, u.name
    ORDER BY post_count DESC");
$results = $db->loadObjectList();
echo "9. SELECT with LEFT JOIN and GROUP BY:\n";
foreach ($results as $result) {
    echo "   - {$result->name}: {$result->post_count} posts\n";
}

// Example 10: SELECT with HAVING
$db->setQuery("SELECT u.id, u.name, COUNT(p.id) AS post_count
    FROM #__users AS u
    LEFT JOIN #__posts AS p ON u.id = p.user_id
    GROUP BY u.id, u.name
    HAVING COUNT(p.id) > :minPosts
    ORDER BY post_count DESC")
    ->bindValue(':minPosts', 0);
$results = $db->loadObjectList();
echo "10. SELECT with HAVING - Users with posts: " . count($results) . "\n";

// Example 11: SELECT with LIMIT and OFFSET
$db->setQuery("SELECT id, name, email FROM #__users ORDER BY id ASC LIMIT 1, 2");
$users = $db->loadObjectList();
echo "11. SELECT with LIMIT/OFFSET - Page 2: " . count($users) . " users\n";

// Example 12: SELECT with subquery
$db->setQuery("SELECT id, name FROM #__users 
    WHERE id IN (SELECT user_id FROM #__posts WHERE status = :status)")
    ->bindValue(':status', 'published');
$users = $db->loadObjectList();
echo "12. SELECT with subquery - Users with published posts: " . count($users) . "\n";

echo "\n=== UPDATE EXAMPLES ===\n\n";

// Example 13: Basic UPDATE
$db->setQuery("UPDATE #__users SET name = :name WHERE id = :id")
    ->bindValue(':name', 'John Doe Updated')
    ->bindValue(':id', $userId1);
$db->execute();
echo "13. Basic UPDATE - Updated user name\n";

// Example 14: UPDATE multiple fields
$db->setQuery("UPDATE #__posts SET status = :status, title = :title WHERE user_id = :userId")
    ->bindValue(':status', 'published')
    ->bindValue(':title', 'Updated Title')
    ->bindValue(':userId', $userId1);
$affected = $db->execute();
echo "14. UPDATE multiple fields - Rows affected: {$affected}\n";

echo "\n=== DELETE EXAMPLES ===\n\n";

// Example 15: Basic DELETE
$db->setQuery("DELETE FROM #__posts WHERE status = :status")
    ->bindValue(':status', 'draft');
$affected = $db->execute();
echo "15. Basic DELETE - Deleted {$affected} draft posts\n";

echo "\n=== DATABASE INFO ===\n\n";

// Get table list
$tables = $db->getTableList();
echo "Tables: " . implode(', ', array_slice($tables, 0, 3)) . "\n";

// Get table columns
$columns = $db->getTableColumns('#__users');
echo "Columns in #__users: " . implode(', ', array_keys($columns)) . "\n";

// Database info
echo "MySQL version: " . $db->getVersion() . "\n";
echo "Collation: " . $db->getCollation() . "\n";

echo "\n=== PROFILING EXAMPLE ===\n\n";

// Profiling (MySQL with PDO)
$db->startProfiling();

$db->setQuery("SELECT * FROM #__users");
$db->execute();

$db->setQuery("SELECT * FROM #__posts");
$db->execute();

$profiles = $db->showProfiles();
if ($profiles) {
    echo "Query profiles:\n";
    foreach ($profiles as $profile) {
        echo "Query ID: {$profile['Query_ID']}\n";
        echo "Duration: {$profile['Duration']} seconds\n";
        echo "Query: {$profile['Query']}\n\n";
    }
}

$db->stopProfiling();

echo "\n=== TRANSACTION EXAMPLE ===\n\n";

// Transaction example
try {
    $db->transactionStart();

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Transaction User')
        ->bindValue(':email', 'txn@example.com');
    $db->execute();

    $db->setQuery("UPDATE #__posts SET status = :status WHERE user_id = :userId")
        ->bindValue(':status', 'archived')
        ->bindValue(':userId', 999);
    $db->execute();

    $db->transactionCommit();
    echo "Transaction committed successfully\n";
} catch (Exception $e) {
    $db->transactionRollback();
    echo "Transaction failed and rolled back: " . $e->getMessage() . "\n";
}

// Cleanup database
cleanupDatabase($db, 'mysql');
