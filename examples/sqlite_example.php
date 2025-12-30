<?php

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;

if (!DatabaseFactory::isDriverAvailable('sqlite')) {
    echo "✗ SQLite driver is NOT available\n";
    exit(1);
}

// Clean up existing database file to ensure fresh start
$dbFile = __DIR__ . '/database.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

try {

    $db = DatabaseFactory::create([
        'driver'    => 'sqlite',
        'dbname'    => $dbFile,
        'tblprefix' => 'app_'
    ]);
    echo "✓ Created SQLite database instance\n";
    echo "\n";
} catch (Exception $e) {
    echo "✗ Could not create SQLite database instance: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Create a table (SQLite syntax)
$createTable = "CREATE TABLE IF NOT EXISTS app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

$db->setQuery($createTable);
$db->execute();

// Create additional tables for JOIN examples
$createPostsTable = "CREATE TABLE IF NOT EXISTS app_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    status TEXT DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

// Example 2: INSERT another user
$db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
    ->bindValue(':name', 'Jane Smith')
    ->bindValue(':email', 'jane@example.com');
$db->execute();
$userId2 = $db->insertid();
echo "2. INSERT another user - User ID: {$userId2}\n";

// Example 3: INSERT multiple rows
$db->setQuery("INSERT INTO #__users (name, email) VALUES 
    ('Alice Johnson', 'alice@example.com'),
    ('Bob Wilson', 'bob@example.com')");
$db->execute();
echo "3. INSERT multiple rows - Inserted 2 users\n";

// Example 4: Handle duplicate with try-catch
try {
    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Duplicate User')
        ->bindValue(':email', 'john@example.com'); // Duplicate email
    $db->execute();
    echo "4. INSERT duplicate - Unexpectedly succeeded\n";
} catch (Exception $e) {
    echo "4. INSERT duplicate - Correctly rejected (unique constraint)\n";
}

// Insert some posts for JOIN examples
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

// Example 7: SELECT with multiple WHERE conditions
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

// Example 9: SELECT with LEFT JOIN
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

// Example 12: SELECT DISTINCT
$db->setQuery("SELECT DISTINCT status FROM #__posts ORDER BY status ASC");
$statuses = $db->loadColumn();
echo "12. SELECT DISTINCT - Post statuses: " . implode(', ', $statuses) . "\n";

// Example 13: SELECT with COUNT
$db->setQuery("SELECT COUNT(*) AS total FROM #__users");
$total = $db->loadResult();
echo "13. SELECT COUNT - Total users: {$total}\n";

// Example 14: SELECT with subquery in WHERE
$db->setQuery("SELECT id, name FROM #__users 
    WHERE id IN (SELECT user_id FROM #__posts WHERE status = :status)")
    ->bindValue(':status', 'published');
$users = $db->loadObjectList();
echo "14. SELECT with subquery - Users with published posts: " . count($users) . "\n";

echo "\n=== UPDATE EXAMPLES ===\n\n";

// Example 15: Basic UPDATE
$db->setQuery("UPDATE #__users SET name = :name WHERE id = :id")
    ->bindValue(':name', 'John Doe Updated')
    ->bindValue(':id', $userId1);
$db->execute();
echo "15. Basic UPDATE - Updated user name\n";

// Example 16: UPDATE multiple fields
$db->setQuery("UPDATE #__posts SET status = :status, title = :title 
    WHERE user_id = :userId AND status = :oldStatus")
    ->bindValue(':status', 'published')
    ->bindValue(':title', 'Updated Post Title')
    ->bindValue(':userId', $userId1)
    ->bindValue(':oldStatus', 'draft');
$affected = $db->execute();
echo "16. UPDATE multiple fields - Rows affected: {$affected}\n";

// Example 17: UPDATE with JOIN (SQLite syntax)
$db->setQuery("UPDATE #__posts 
    SET title = :title 
    WHERE user_id IN (SELECT id FROM #__users WHERE email = :email)")
    ->bindValue(':title', 'Post by John')
    ->bindValue(':email', 'john@example.com');
$affected = $db->execute();
echo "17. UPDATE with subquery - Rows affected: {$affected}\n";

echo "\n=== DELETE EXAMPLES ===\n\n";

// Example 18: Basic DELETE
$db->setQuery("DELETE FROM #__posts WHERE status = :status")
    ->bindValue(':status', 'draft');
$affected = $db->execute();
echo "18. Basic DELETE - Deleted {$affected} draft posts\n";

// Example 19: DELETE with multiple WHERE conditions
$db->setQuery("DELETE FROM #__users WHERE id > :maxId AND email LIKE :pattern")
    ->bindValue(':maxId', 1000)
    ->bindValue(':pattern', '%test%');
$affected = $db->execute();
echo "19. DELETE with multiple WHERE - Deleted {$affected} test users\n";

// Example 20: DELETE with subquery (SQLite syntax)
$db->setQuery("DELETE FROM #__posts 
    WHERE user_id IN (SELECT id FROM #__users WHERE name LIKE :pattern)")
    ->bindValue(':pattern', '%Test%');
$affected = $db->execute();
echo "20. DELETE with subquery - Deleted {$affected} posts\n";

// Get table list (SQLite)
$tables = $db->getTableList();
echo "Tables in database:\n";
print_r($tables);

// Get table columns (SQLite using PRAGMA)
$columns = $db->getTableColumns('app_users');
echo "Columns in app_users table:\n";
print_r($columns);

// Database info
echo "\n=== DATABASE INFO ===\n\n";
echo "SQLite version: " . $db->getVersion() . "\n";
echo "Tables: " . implode(', ', array_slice($tables, 0, 3)) . "\n";

// Transaction example
echo "\n=== TRANSACTION EXAMPLE ===\n\n";
try {
    $db->transactionStart();

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Transaction User 1')
        ->bindValue(':email', 'txn1@example.com');
    $db->execute();

    $db->setQuery("INSERT INTO #__users (name, email) VALUES (:name, :email)")
        ->bindValue(':name', 'Transaction User 2')
        ->bindValue(':email', 'txn2@example.com');
    $db->execute();

    $db->transactionCommit();
    echo "Transaction committed successfully\n";
} catch (Exception $e) {
    $db->transactionRollback();
    echo "Transaction failed and rolled back: " . $e->getMessage() . "\n";
}

// Note: SQLite doesn't support TRUNCATE, use DELETE instead
// This is handled automatically by the trait
$db->truncateTable('#__users'); // Uses DELETE FROM instead

// Enable Write-Ahead Logging (WAL) mode for better concurrency
$db->setQuery("PRAGMA journal_mode=WAL");
$db->execute();

// Set cache size (in pages, negative = KB)
$db->setQuery("PRAGMA cache_size=-8000"); // 8MB cache
$db->execute();

// Vacuum database to reclaim space
$db->setQuery("VACUUM");
$db->execute();

cleanupDatabase($db, 'sqlite');
