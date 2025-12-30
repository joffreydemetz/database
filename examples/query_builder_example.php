<?php

/**
 * Query Builder Example
 * 
 * This example demonstrates how to build SQL queries using the Query objects:
 * - SelectQuery
 * - InsertQuery
 * - UpdateQuery
 * - DeleteQuery
 * - UnionQuery
 * - StringQuery
 */

require_once __DIR__ . '/bootstrap.php';

use JDZ\Database\DatabaseFactory;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\InsertQuery;
use JDZ\Database\Query\UpdateQuery;
use JDZ\Database\Query\DeleteQuery;
use JDZ\Database\Query\UnionQuery;
use JDZ\Database\Query\StringQuery;

if (!DatabaseFactory::isDriverAvailable('sqlite')) {
    echo "✗ SQLite driver is NOT available\n";
    exit(1);
}

// Clean up existing database
$dbFile = __DIR__ . '/query_builder.sqlite';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

// Create database instance
$db = DatabaseFactory::create([
    'driver'    => 'sqlite',
    'dbname'    => $dbFile,
    'tblprefix' => 'app_'
]);

echo "========================================\n";
echo "    QUERY BUILDER EXAMPLES\n";
echo "========================================\n\n";

// Setup tables
$db->setQuery("CREATE TABLE app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    role TEXT DEFAULT 'user',
    status TEXT DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->execute();

$db->setQuery("CREATE TABLE app_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    views INTEGER DEFAULT 0,
    status TEXT DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->execute();

echo "✓ Tables created\n\n";

// ============================================
// INSERT QUERY EXAMPLES
// ============================================
echo "=== INSERT QUERY ===\n\n";

// Example 1: INSERT with columns and values
echo "1. INSERT with columns and values:\n";
$insertQuery = new InsertQuery();
$insertQuery
    ->insert('app_users')
    ->columns(['name', 'email', 'role'])
    ->values([':name1, :email1, :role1', ':name2, :email2, :role2'])
    ->bindValue(':name1', 'John Doe')
    ->bindValue(':email1', 'john@example.com')
    ->bindValue(':role1', 'admin')
    ->bindValue(':name2', 'Jane Smith')
    ->bindValue(':email2', 'jane@example.com')
    ->bindValue(':role2', 'editor');

echo $insertQuery->toString() . "\n";
$db->setQuery($insertQuery);
$db->execute();
echo "✓ Inserted 2 users\n\n";

// Example 2: INSERT with SET syntax
echo "2. INSERT with SET syntax:\n";
$insertQuery = new InsertQuery();
$insertQuery
    ->insert('app_users')
    ->set([
        "name = 'Bob Wilson'",
        "email = 'bob@example.com'",
        "role = 'user'"
    ]);

echo $insertQuery->toString() . "\n";
$db->setQuery($insertQuery);
$db->execute();
echo "✓ Inserted 1 user\n\n";

// Example 3: INSERT IGNORE
echo "3. INSERT IGNORE:\n";
$insertQuery = new InsertQuery();
$insertQuery
    ->insert('app_users', true)  // true = IGNORE
    ->columns(['name', 'email'])
    ->values(':name, :email')
    ->bindValue(':name', 'Alice Brown')
    ->bindValue(':email', 'alice@example.com');

echo $insertQuery->toString() . "\n";
$db->setQuery($insertQuery);
$db->execute();
echo "✓ Inserted with IGNORE\n\n";

// Insert some posts for later examples
$db->setQuery("INSERT INTO app_posts (user_id, title, content, views, status) VALUES 
    (1, 'Getting Started', 'Welcome to our blog', 150, 'published'),
    (1, 'Advanced Tips', 'Some advanced techniques', 75, 'published'),
    (2, 'My First Post', 'Hello world', 50, 'published'),
    (2, 'Draft Article', 'Work in progress', 0, 'draft'),
    (3, 'Tutorial', 'How to guide', 120, 'published')");
$db->execute();

// ============================================
// SELECT QUERY EXAMPLES
// ============================================
echo "=== SELECT QUERY ===\n\n";

// Example 1: Basic SELECT
echo "1. Basic SELECT:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['id', 'name', 'email'])
    ->from('app_users');

echo $selectQuery->toString() . "\n\n";

// Example 2: SELECT with WHERE
echo "2. SELECT with WHERE:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select('*')
    ->from('app_users')
    ->where("role = 'admin'");

echo $selectQuery->toString() . "\n\n";

// Example 3: SELECT with multiple WHERE (AND)
echo "3. SELECT with multiple WHERE conditions:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select('*')
    ->from('app_posts')
    ->where(["status = 'published'", "views > 100"]);

echo $selectQuery->toString() . "\n\n";

// Example 4: SELECT with WHERE (OR)
echo "4. SELECT with OR conditions:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select('*')
    ->from('app_users')
    ->where(["role = 'admin'", "role = 'editor'"], 'OR');

echo $selectQuery->toString() . "\n\n";

// Example 5: SELECT with JOIN
echo "5. SELECT with JOIN:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['u.name', 'p.title', 'p.views'])
    ->from('app_users u')
    ->innerJoin('app_posts p ON u.id = p.user_id')
    ->where("p.status = 'published'");

echo $selectQuery->toString() . "\n\n";

// Example 6: SELECT with multiple JOINs
echo "6. SELECT with multiple JOIN types:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['u.name', 'p.title'])
    ->from('app_users u')
    ->leftJoin('app_posts p ON u.id = p.user_id')
    ->where("u.status = 'active'");

echo $selectQuery->toString() . "\n\n";

// Example 7: SELECT with GROUP BY and HAVING
echo "7. SELECT with GROUP BY and HAVING:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['user_id', 'COUNT(*) as post_count'])
    ->from('app_posts')
    ->group('user_id')
    ->having('post_count > 1');

echo $selectQuery->toString() . "\n\n";

// Example 8: SELECT with ORDER BY
echo "8. SELECT with ORDER BY:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['title', 'views'])
    ->from('app_posts')
    ->where("status = 'published'")
    ->order(['views DESC', 'title ASC']);

echo $selectQuery->toString() . "\n\n";

// Example 9: SELECT with LIMIT and OFFSET
echo "9. SELECT with LIMIT and OFFSET:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select('*')
    ->from('app_posts')
    ->order('created_at DESC')
    ->setLimit(3, 1);  // Limit 3, Offset 1

echo $selectQuery->toString() . "\n\n";

// Example 10: Complex SELECT with all features
echo "10. Complex SELECT query:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['u.name', 'u.role', 'COUNT(p.id) as total_posts', 'SUM(p.views) as total_views'])
    ->from('app_users u')
    ->leftJoin('app_posts p ON u.id = p.user_id')
    ->where(["u.status = 'active'", "u.role IN ('admin', 'editor')"])
    ->group('u.id')
    ->having('total_posts > 0')
    ->order('total_views DESC')
    ->setLimit(10);

echo $selectQuery->toString() . "\n\n";

// ============================================
// UPDATE QUERY EXAMPLES
// ============================================
echo "=== UPDATE QUERY ===\n\n";

// Example 1: Basic UPDATE
echo "1. Basic UPDATE:\n";
$updateQuery = new UpdateQuery();
$updateQuery
    ->update('app_users')
    ->set("status = 'inactive'")
    ->where("id = 3");

echo $updateQuery->toString() . "\n\n";

// Example 2: UPDATE with multiple SET clauses
echo "2. UPDATE with multiple SET:\n";
$updateQuery = new UpdateQuery();
$updateQuery
    ->update('app_posts')
    ->set([
        "status = 'archived'",
        "views = 0"
    ])
    ->where("created_at < '2024-01-01'");

echo $updateQuery->toString() . "\n\n";

// Example 3: UPDATE with JOIN
echo "3. UPDATE with JOIN:\n";
$updateQuery = new UpdateQuery();
$updateQuery
    ->update('app_posts p')
    ->innerJoin('app_users u ON p.user_id = u.id')
    ->set("p.status = 'locked'")
    ->where("u.status = 'inactive'");

echo $updateQuery->toString() . "\n\n";

// Example 4: UPDATE with parameters
echo "4. UPDATE with bound parameters:\n";
$updateQuery = new UpdateQuery();
$updateQuery
    ->update('app_users')
    ->set("role = :role")
    ->where("email = :email")
    ->bindValue(':role', 'admin')
    ->bindValue(':email', 'jane@example.com');

echo $updateQuery->toString() . "\n";
echo "Bound parameters: " . print_r($updateQuery->getBounded(), true) . "\n";

// ============================================
// DELETE QUERY EXAMPLES
// ============================================
echo "=== DELETE QUERY ===\n\n";

// Example 1: Basic DELETE
echo "1. Basic DELETE:\n";
$deleteQuery = new DeleteQuery();
$deleteQuery
    ->delete('app_posts')
    ->where("status = 'draft'");

echo $deleteQuery->toString() . "\n\n";

// Example 2: DELETE with multiple WHERE
echo "2. DELETE with multiple conditions:\n";
$deleteQuery = new DeleteQuery();
$deleteQuery
    ->delete('app_posts')
    ->where(["status = 'archived'", "views = 0"]);

echo $deleteQuery->toString() . "\n\n";

// Example 3: DELETE with JOIN
echo "3. DELETE with JOIN:\n";
$deleteQuery = new DeleteQuery();
$deleteQuery
    ->delete('app_posts')
    ->innerJoin('app_users u ON app_posts.user_id = u.id')
    ->where("u.status = 'deleted'");

echo $deleteQuery->toString() . "\n\n";

// ============================================
// UNION QUERY EXAMPLES
// ============================================
echo "=== UNION QUERY ===\n\n";

// Example 1: Basic UNION ALL
echo "1. Basic UNION ALL:\n";
$unionQuery = new UnionQuery();

$query1 = new SelectQuery();
$query1->select(['name', "'user' as type"])->from('app_users')->where("role = 'user'");

$query2 = new SelectQuery();
$query2->select(['name', "'admin' as type"])->from('app_users')->where("role = 'admin'");

$unionQuery
    ->addQuery($query1)
    ->addQuery($query2);

echo $unionQuery->toString() . "\n\n";

// Example 2: UNION DISTINCT
echo "2. UNION DISTINCT:\n";
$unionQuery = new UnionQuery();

$unionQuery
    ->addQuery("SELECT email FROM app_users WHERE role = 'admin'")
    ->addQueryDistinct("SELECT email FROM app_users WHERE role = 'editor'");

echo $unionQuery->toString() . "\n\n";

// Example 3: UNION with ORDER BY and LIMIT
echo "3. UNION with ORDER BY and LIMIT:\n";
$unionQuery = new UnionQuery();

$query1 = new SelectQuery();
$query1->select(['title', 'views', "'post' as source"])->from('app_posts')->where("status = 'published'");

$query2 = new StringQuery("SELECT name as title, 0 as views, 'user' as source FROM app_users WHERE role = 'admin'");

$unionQuery
    ->addQuery($query1)
    ->addQuery($query2)
    ->order('views DESC')
    ->setLimit(5);

echo $unionQuery->toString() . "\n\n";

// ============================================
// STRING QUERY EXAMPLE
// ============================================
echo "=== STRING QUERY ===\n\n";

// Example 1: Basic StringQuery
echo "1. StringQuery with raw SQL:\n";
$stringQuery = new StringQuery("SELECT * FROM app_users WHERE role = 'admin'");
echo $stringQuery->toString() . "\n\n";

// Example 2: StringQuery with setQuery
echo "2. StringQuery with setQuery:\n";
$stringQuery = new StringQuery();
$stringQuery->setQuery("SELECT COUNT(*) as total FROM app_posts WHERE status = 'published'");
echo $stringQuery->toString() . "\n\n";

// Example 3: StringQuery with parameters
echo "3. StringQuery with bound parameters:\n";
$stringQuery = new StringQuery("SELECT * FROM app_users WHERE email = :email");
$stringQuery->bindValue(':email', 'john@example.com');
echo $stringQuery->toString() . "\n";
echo "Bound parameters: " . print_r($stringQuery->getBounded(), true) . "\n";

// ============================================
// USING QUERIES WITH DATABASE
// ============================================
echo "=== EXECUTING QUERIES ===\n\n";

echo "1. Execute SELECT and fetch results:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['name', 'email', 'role'])
    ->from('app_users')
    ->where("status = 'active'")
    ->order('name ASC');

$db->setQuery($selectQuery);
$users = $db->loadObjectList();
echo "Found " . count($users) . " active users\n";
foreach ($users as $user) {
    echo "  - {$user->name} ({$user->role}) - {$user->email}\n";
}
echo "\n";

echo "2. Execute UPDATE:\n";
$updateQuery = new UpdateQuery();
$updateQuery
    ->update('app_posts')
    ->set("views = views + 1")
    ->where("id = 1");

$db->setQuery($updateQuery);
$affected = $db->execute();
echo "Updated {$affected} row(s)\n\n";

echo "3. Get single row:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select(['title', 'views'])
    ->from('app_posts')
    ->where("id = 1");

$db->setQuery($selectQuery);
$post = $db->loadObject();
echo "Post: {$post->title} - Views: {$post->views}\n\n";

echo "4. Get single value:\n";
$selectQuery = new SelectQuery();
$selectQuery
    ->select('COUNT(*)')
    ->from('app_posts')
    ->where("status = 'published'");

$db->setQuery($selectQuery);
$count = $db->loadResult();
echo "Total published posts: {$count}\n\n";

// Cleanup
unlink($dbFile);

echo "========================================\n";
echo "  ✓ All examples completed successfully\n";
echo "========================================\n";
