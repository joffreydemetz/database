<?php

/**
 * PostgreSQL Example - Using String Queries
 * 
 * This example demonstrates basic database operations with PostgreSQL using string queries.
 * For comprehensive Query Builder examples (SelectQuery, InsertQuery, etc.), 
 * see query_builder_example.php
 */

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;

if (!DatabaseFactory::isDriverAvailable('pgsql')) {
    echo "✗ PostgreSQL driver is NOT available\n";
    exit(1);
}

echo "NOTE: This example requires a PostgreSQL server.\n";
echo "You can either:\n";
echo "  1. Start the Docker container: docker compose up -d postgres\n";
echo "  2. Use a local PostgreSQL installation\n\n";

// Try to connect to PostgreSQL
try {
    // Method 1: Using the Factory
    $db = DatabaseFactory::create([
        'driver'    => 'pgsql',
        'host'      => getenv('JDZ_PGSQL_HOST') ?: 'localhost',
        'dbname'    => getenv('JDZ_PGSQL_DB') ?: 'test_db',
        'user'      => getenv('JDZ_PGSQL_USER') ?: 'postgres',
        'pass'      => getenv('JDZ_PGSQL_PASS') ?: 'password',
        'port'      => getenv('JDZ_PGSQL_PORT') ?: 5432,
        'charset'   => 'utf8',
        'tblprefix' => 'app_'
    ]);

    // Test connection
    $db->connect();
    echo "✓ Connected to PostgreSQL\n";
    echo "\n";
} catch (Exception $e) {
    echo "✗ Could not connect to PostgreSQL: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Please ensure PostgreSQL is running and accessible with the credentials:\n";
    echo "  Host: " . getenv('JDZ_PGSQL_HOST') . ":" . getenv('JDZ_PGSQL_PORT') . "\n";
    echo "  User: " . getenv('JDZ_PGSQL_USER') . "\n";
    echo "  Password: " . getenv('JDZ_PGSQL_PASS') . "\n";
    echo "  Database: " . getenv('JDZ_PGSQL_DB') . "\n\n";
    exit(1);
}

// Create tables for examples
$createUsersTable = "CREATE TABLE IF NOT EXISTS app_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$db->setQuery($createUsersTable);
$db->execute();

$createPostsTable = "CREATE TABLE IF NOT EXISTS app_posts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    status VARCHAR(50) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES app_users(id)
)";

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
$db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
    ->bindValue(':name', 'Jane Smith')
    ->bindValue(':email', 'jane@example.com');
$db->execute();
$userId2 = $db->insertid();
echo "2. INSERT - User ID: {$userId2}\n";

// Example 3: INSERT with RETURNING (PostgreSQL-specific)
$db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email) RETURNING id, name")
    ->bindValue(':name', 'Alice Johnson')
    ->bindValue(':email', 'alice@example.com');
$db->execute();
$result = $db->loadObject();
echo "3. INSERT with RETURNING - ID: {$result->id}, Name: {$result->name}\n";

// Example 4: INSERT multiple rows
$db->setQuery("INSERT INTO #__users (name, email) VALUES 
    ('Bob Wilson', 'bob@example.com'),
    ('Carol Brown', 'carol@example.com')");
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

// Example 7: SELECT with INNER JOIN
$db->setQuery("SELECT u.id, u.name, p.title, p.status 
    FROM #__users AS u
    INNER JOIN #__posts AS p ON u.id = p.user_id
    ORDER BY u.name ASC, p.created_at DESC");
$results = $db->loadObjectList();
echo "7. SELECT with INNER JOIN - Found " . count($results) . " user-post combinations\n";

// Example 8: SELECT with LEFT JOIN and aggregation
$db->setQuery("SELECT u.id, u.name, COUNT(p.id) AS post_count
    FROM #__users AS u
    LEFT JOIN #__posts AS p ON u.id = p.user_id
    GROUP BY u.id, u.name
    ORDER BY post_count DESC");
$results = $db->loadObjectList();
echo "8. SELECT with LEFT JOIN and GROUP BY:\n";
foreach ($results as $result) {
    echo "   - {$result->name}: {$result->post_count} posts\n";
}

// Example 9: SELECT with HAVING
$db->setQuery("SELECT u.id, u.name, COUNT(p.id) AS post_count
    FROM #__users AS u
    LEFT JOIN #__posts AS p ON u.id = p.user_id
    GROUP BY u.id, u.name
    HAVING COUNT(p.id) > :minPosts
    ORDER BY post_count DESC")
    ->bindValue(':minPosts', 0);
$results = $db->loadObjectList();
echo "9. SELECT with HAVING - Users with posts: " . count($results) . "\n";

// Example 10: SELECT with LIMIT and OFFSET
$db->setQuery("SELECT id, name, email FROM #__users ORDER BY id ASC LIMIT 2 OFFSET 1");
$users = $db->loadObjectList();
echo "10. SELECT with LIMIT/OFFSET - Page 2: " . count($users) . " users\n";

// Example 11: SELECT with subquery
$db->setQuery("SELECT id, name FROM #__users 
    WHERE id IN (SELECT user_id FROM #__posts WHERE status = :status)")
    ->bindValue(':status', 'published');
$users = $db->loadObjectList();
echo "11. SELECT with subquery - Users with published posts: " . count($users) . "\n";

// Example 12: PostgreSQL full-text search
$db->setQuery("SELECT id, title, content FROM #__posts 
    WHERE to_tsvector('english', content) @@ to_tsquery(:search)")
    ->bindValue(':search', 'Content');
$results = $db->loadObjectList();
echo "12. PostgreSQL full-text search - Found: " . count($results) . " posts\n";

echo "\n=== UPDATE EXAMPLES ===\n\n";

// Example 13: Basic UPDATE
$db->setQuery("UPDATE #__users SET name = :name WHERE id = :id")
    ->bindValue(':name', 'John Doe Updated')
    ->bindValue(':id', $userId1);
$db->execute();
echo "13. Basic UPDATE - Updated user name\n";

// Example 14: UPDATE with JOIN (PostgreSQL syntax)
$db->setQuery("UPDATE #__posts SET title = :title 
    FROM #__users 
    WHERE #__posts.user_id = #__users.id AND #__users.email = :email")
    ->bindValue(':title', 'Post by John')
    ->bindValue(':email', 'john@example.com');
$affected = $db->execute();
echo "14. UPDATE with JOIN - Rows affected: {$affected}\n";

echo "\n=== DELETE EXAMPLES ===\n\n";

// Example 15: Basic DELETE
$db->setQuery("DELETE FROM #__posts WHERE status = :status")
    ->bindValue(':status', 'draft');
$affected = $db->execute();
echo "15. Basic DELETE - Deleted {$affected} draft posts\n";

echo "\n=== DATABASE INFO ===\n\n";

$tables = $db->getTableList();
echo "PostgreSQL version: " . $db->getVersion() . "\n";
echo "Database encoding: " . $db->getCollation() . "\n";
echo "Tables: " . implode(', ', array_slice($tables, 0, 3)) . "\n";

// Transaction with savepoints (PostgreSQL feature)
echo "\n=== TRANSACTION WITH SAVEPOINTS ===\n\n";
try {
    $db->transactionStart();

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Transaction User 1')
        ->bindValue(':email', 'txn1@example.com');
    $db->execute();

    // Create savepoint
    $db->setQuery("SAVEPOINT sp1");
    $db->execute();

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Transaction User 2')
        ->bindValue(':email', 'txn2@example.com');
    $db->execute();

    // Release savepoint
    $db->setQuery("RELEASE SAVEPOINT sp1");
    $db->execute();

    $db->transactionCommit();
    echo "Transaction with savepoints committed successfully\n";
} catch (Exception $e) {
    $db->transactionRollback();
    echo "Transaction failed and rolled back: " . $e->getMessage() . "\n";
}

cleanupDatabase($db, 'postgresql');
