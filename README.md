# JDZ Database

A developer-friendly database abstraction layer for PHP 8.2+ applications.

**Unified Interface** - Write once, run anywhere. Switch between MySQL, PostgreSQL, and SQLite without changing your code.

**Type-Safe** - Built with PHP 8.2+ features including enums, typed properties, and strict types for reliability.

**Developer-Friendly** - Fluent query builder, intuitive methods, and comprehensive error handling make database operations straightforward.

**Production-Ready** - Thoroughly tested with 200+ unit and integration tests covering all drivers and features.

**Flexible** - Use query builders for type safety or raw SQL when you need full control. Mix and match as needed.

## Installation

```bash
composer require jdz/database
```

## Requirements
- PHP 8.2 or higher

**Required Extensions (install as needed)**
- `ext-pdo` - PDO extension (core requirement for PDO-based drivers)
- `ext-pdo_mysql` - For MySQL/MariaDB via PDO
- `ext-pdo_pgsql` - For PostgreSQL support
- `ext-pdo_sqlite` - For SQLite support (usually built-in)
- `ext-mysqli` - For MySQL/MariaDB via MySQLi native driver

**Check Available Drivers:**
The library includes methods to detect which drivers are available on your system.

## Configuration Options

### Common Options
- **driver** - Database type: mysql, mysqli, pgsql, sqlite, mariadb
- **tblprefix** - Table prefix for `#__` replacement (default: empty string)
- **driverOptions** - PDO-specific options array

### Connection Options (MySQL, PostgreSQL)
- **host** - Database server hostname (default: localhost)
- **port** - Server port (default: 3306 for MySQL, 5432 for PostgreSQL)
- **dbname** - Database name to connect to
- **user** - Database username
- **pass** - Database password
- **charset** - Character encoding (default: utf8mb4 for MySQL, utf8 for others)
- **socket** - Unix socket path (alternative to host/port)

### SQLite Options
- **dbname** - Database file path or `:memory:` for in-memory database

### MySQLi Options
- **sqlModes** - Array of SQL modes (STRICT_TRANS_TABLES, NO_ZERO_DATE, etc.)

## Supported Database Drivers

### PDO-Based Drivers
- **MySQL 5.7+** - Via PDO MySQL driver with full MySQL 8.0 support
- **MariaDB 10.3+** - Via PDO MySQL driver with automatic MariaDB detection
- **PostgreSQL 12+** - Via PDO PostgreSQL driver with PostgreSQL-specific features
- **SQLite 3+** - Via PDO SQLite driver for file-based and in-memory databases

### Native Drivers
- **MySQLi** - Native MySQL/MariaDB driver offering MySQL-specific optimizations like profiling and table locking

All drivers share the same interface, making it easy to switch between them or support multiple databases in the same application.

## Core Features

### Database Operations
- **Connection Management** - Automatic connection handling, reconnection support, and connection pooling  
- **CRUD Operations** - Full support for Create, Read, Update, Delete with intuitive methods  
- **Transaction Support** - BEGIN, COMMIT, ROLLBACK with savepoint support for PostgreSQL  
- **Prepared Statements** - Automatic SQL injection protection through parameter binding  
- **Multiple Result Formats** - Load as objects, arrays, single values, or custom classes  

### Query Building
- **SelectQuery** - Build SELECT statements with WHERE, JOIN, GROUP BY, HAVING, ORDER BY, LIMIT  
- **InsertQuery** - INSERT with VALUES or SET syntax, INSERT IGNORE, multiple row insertion  
- **UpdateQuery** - UPDATE with SET, WHERE, JOIN, ORDER BY, LIMIT  
- **DeleteQuery** - DELETE with WHERE, JOIN, ORDER BY, LIMIT  
- **UnionQuery** - Combine queries with UNION and UNION DISTINCT  
- **StringQuery** - Use raw SQL with parameter binding when needed  

### Advanced Features
- **Table Prefix Replacement** - Automatically replace `#__` placeholder with configured prefix  
- **Driver Factory** - Create database instances from arrays or DSN strings  
- **Database Introspection** - List tables, get columns, check existence  
- **Type-Safe Enums** - FetchMode and ParamType enums for better IDE support  
- **Error Handling** - Comprehensive exceptions for debugging  
- **Metadata Access** - Database version, collation, table structures

## How It Works

### Database Factory Pattern
The library uses a factory pattern to create database instances. You can instantiate databases from configuration arrays or DSN strings, with automatic driver selection based on your environment. The factory also provides methods to check available drivers on your system.

### Query Builder Architecture
Instead of writing raw SQL strings, you can use dedicated query builder classes. Each query type (SELECT, INSERT, UPDATE, DELETE, UNION) has its own builder with methods corresponding to SQL clauses. Builders use method chaining for a fluent interface and maintain type safety through PHP's type system.

### Connection Management
Database connections are lazy-loaded and managed automatically. The library handles connection state, automatic reconnection on failure, and proper resource cleanup. You can check connection status, manually connect/disconnect, and manage multiple database connections simultaneously.

### Parameter Binding
All queries support parameter binding to prevent SQL injection. Named parameters (`:name`) and typed parameters are supported. The ParamType enum provides type hints (INT, STR, BOOL, NULL, LOB) for proper data handling across different database engines.

### Data Loading Methods
Results can be loaded in various formats:
- **loadObject()** - Single row as standard object or custom class instance
- **loadObjectList()** - Multiple rows as array of objects, optionally keyed by column
- **loadAssoc()** - Single row as associative array
- **loadAssocList()** - Multiple rows as associative arrays
- **loadColumn()** - Single column values as array
- **loadResult()** - Single scalar value (useful for COUNT, SUM, etc.)

### Table Prefix System
Use `#__` as a placeholder in table names, which gets replaced with your configured prefix. This allows writing portable code that works across different installations with different prefixes.

## Query Builders

***SelectQuery*** Builds SELECT statements with full SQL feature support. Chain methods to add columns, specify tables, add WHERE conditions, JOIN other tables, group results, filter groups with HAVING, order results, and limit/offset for pagination. Supports complex nested queries and all JOIN types (INNER, LEFT, RIGHT, CROSS).

***InsertQuery*** Constructs INSERT statements supporting two syntaxes: VALUES (traditional column/value pairs) and SET (key=value pairs). Handles single or multiple row insertion, INSERT IGNORE for duplicate handling, and parameter binding for all values. Returns insert ID after execution.

***UpdateQuery*** Builds UPDATE statements with SET clause for field updates. Supports WHERE conditions for targeting specific rows, JOIN for updating based on related tables, ORDER BY for controlling update order, and LIMIT for restricting update count. All values support parameter binding.

***DeleteQuery*** Creates DELETE statements with WHERE conditions for row targeting, JOIN support for deleting based on related tables, ORDER BY for deletion order control, and LIMIT for restricting deletion count. Ensures safe deletions through parameter binding.

***UnionQuery*** Combines multiple SELECT queries using UNION (remove duplicates) or UNION ALL (keep duplicates). Each subquery can be a SelectQuery object or raw SQL string. Supports ORDER BY and LIMIT on the combined result set. Useful for merging data from multiple tables or queries.

***StringQuery*** Wraps raw SQL strings when you need full control. Still supports parameter binding for security. Useful for complex queries, database-specific features, or when query builders don't cover your use case. Integrates seamlessly with the database instance.

## Database Introspection

The library provides methods to inspect database structure and metadata:

**Table Operations**
- List all tables in the database
- Check if a table exists before operations
- Drop tables with CASCADE support (PostgreSQL)
- Truncate tables to remove all data
- Rename tables (driver-dependent)
- Get CREATE TABLE statement (MySQL/MariaDB)

**Column Information**
- List all columns in a table
- Get column data types and properties
- Check for specific columns before queries

**Database Metadata**
- Database version (MySQL 8.0.34, PostgreSQL 15.2, etc.)
- Character set and collation information
- Database name and connection details

## Transaction Handling

Transactions ensure data consistency across multiple operations. Start a transaction with `transactionStart()`, execute your queries, then either `transactionCommit()` to save changes or `transactionRollback()` to undo them. PostgreSQL supports savepoints for fine-grained control within transactions.

## Examples

The `examples/` directory contains comprehensive, runnable examples demonstrating all library features.

### Run examples

```bash
# Run all examples inside a Docker containers
composer examples

# Or run directly (if databases are already set up)
php examples/run.php

# Run individual examples
php examples/query_builder_example.php
php examples/factory_example.php
php examples/mysql_example.php
php examples/postgresql_example.php
php examples/sqlite_example.php
```

### Notes

- All examples use `#__` table prefix (replaced with `app_` by default)
- Examples automatically clean up created tables
- SQLite files are created in examples/ directory
- Docker Compose available for test databases

## Testing

The library includes a comprehensive test suite with 200+ unit and integration tests covering all drivers and features.

### Running Tests

```bash
# Run all tests with Docker containers
composer test

# Or run directly (if databases are already set up)
composer phpunit
# Or 
vendor/bin/phpunit --colors=always

# Run unit tests only (fast, no database needed)
vendor/bin/phpunit --testsuite Unit

# Run integration tests (requires databases)
vendor/bin/phpunit --testsuite Integration

# Run specific test file
vendor/bin/phpunit tests/Unit/DatabaseFactoryTest.php
vendor/bin/phpunit tests/Integration/PdoSqliteDatabaseTest.php
```

Tests automatically start/stop Docker containers for MySQL and PostgreSQL, ensuring consistent test environments.

### Test Coverage

**Unit Tests** (no database required)
- **DatabaseFactoryTest** - Factory pattern and driver creation
- **FetchModeTest** - FetchMode enum validation
- **ParamTypeTest** - ParamType enum with fromString() conversion

**Query Builder Tests** (2,156 lines of comprehensive testing)
- **SelectQueryTest** (517 lines) - SELECT with WHERE, JOIN, GROUP BY, HAVING, ORDER BY, LIMIT
- **InsertQueryTest** (345 lines) - INSERT with columns/values, SET syntax, IGNORE modifier
- **UpdateQueryTest** (315 lines) - UPDATE with SET, WHERE, JOIN, ORDER BY, LIMIT
- **DeleteQueryTest** (302 lines) - DELETE with WHERE, JOIN, ORDER BY, LIMIT
- **UnionQueryTest** (379 lines) - UNION ALL, UNION DISTINCT, ORDER BY, LIMIT
- **StringQueryTest** (398 lines) - Raw SQL with parameter binding

**Integration Tests** (automatically skip if driver unavailable)
- **PdoDatabaseTest** - MySQL/MariaDB via PDO driver
- **MysqliDatabaseTest** - MySQL/MariaDB via MySQLi native driver
- **PdoPostgresqlDatabaseTest** - PostgreSQL-specific features
- **PdoSqliteDatabaseTest** - SQLite in-memory and file databases

### What's Tested

**Core Database Functionality**
- Connection management (connect, disconnect, reconnect)  
- Query execution (SQL queries, prepared statements)  
- Data operations (INSERT, UPDATE, DELETE, SELECT)  
- Data retrieval (loadObject, loadObjectList, loadAssoc, loadColumn, loadResult)  
- Transaction handling (start, commit, rollback)  
- Parameter binding (named parameters, type hints)  
- Table operations (create, drop, truncate, rename, exists)  
- Table introspection (columns, metadata)  
- Quote/escape functions  

**Driver-Specific Features**
- **MySQL PDO**: Profiling, collation, standard operations  
- **MySQLi**: Table locking, profiling, prepared statements  
- **PostgreSQL**: Double-quoted identifiers, SERIAL primary keys, ALTER TABLE  
- **SQLite**: In-memory databases, backtick identifiers, limited ALTER TABLE  

**Factory & Configuration**
- DatabaseFactory creation from arrays  
- DatabaseFactory creation from DSN strings  
- Driver availability detection  
- Environment variable configuration  
- Error handling for unsupported drivers  

## License

MIT License - Free for personal and commercial use. See [LICENSE](LICENSE) file for full text.
