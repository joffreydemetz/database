# Upgrade Guide

## Typed Query Elements (NEW)

The query builder now uses strongly-typed Element classes in the `JDZ\Database\Query\Element` namespace instead of generic `Element` instances. This provides better IDE support, type safety, and clearer code intent.

### New Element Classes

All SQL clause components now have dedicated typed classes:

- **`SelectElement`** - SELECT clause
- **`FromElement`** - FROM clause
- **`WhereElement`** - WHERE clause
- **`JoinElement`** - JOIN clauses (LEFT, RIGHT, INNER, OUTER)
- **`GroupByElement`** - GROUP BY clause
- **`HavingElement`** - HAVING clause
- **`OrderByElement`** - ORDER BY clause
- **`SetElement`** - SET clause (UPDATE/INSERT)
- **`DeleteElement`** - DELETE clause
- **`UpdateElement`** - UPDATE clause
- **`InsertElement`** - INSERT INTO clause
- **`ColumnsElement`** - Column list for INSERT
- **`ValuesElement`** - VALUES for INSERT
- **`UnionElement`** - UNION/UNION DISTINCT

### Benefits

1. **Type Safety** - IDEs can provide better autocomplete and type checking
2. **Self-Documenting** - Code clearly shows what SQL clause is being built
3. **Simplified Logic** - Element-specific logic encapsulated in dedicated classes
4. **Better Testing** - Each element type can be tested independently

### Example

**Internal Implementation (you don't need to change anything):**

```php
// Before (generic Element)
$this->select = new Element('SELECT', $columns);
$this->from = new Element('FROM', $tables);
$this->where = new Element('WHERE', $conditions, " AND ");

// After (typed Elements)
$this->select = new SelectElement($columns);
$this->from = new FromElement($tables);
$this->where = new WhereElement($conditions, 'AND');
```

**For Users:** This change is transparent - the public API remains the same. You continue using the query builder as before:

```php
$query = $db->createSelectQuery()
    ->select(['id', 'name'])
    ->from('#__users')
    ->where('active = 1');
```

The internal representation now uses typed elements for better code quality and maintainability.

---

## Query Class Refactoring (BREAKING CHANGE)

The monolithic `Query` class has been split into specialized query builders in the `JDZ\Database\Query` namespace. This provides better type safety, clearer code, and eliminates unnecessary null properties.

### New Query Classes

The following query-specific classes are now available:

- **`JDZ\Database\Query\SelectQuery`** - For SELECT queries
- **`JDZ\Database\Query\DeleteQuery`** - For DELETE queries
- **`JDZ\Database\Query\UpdateQuery`** - For UPDATE queries
- **`JDZ\Database\Query\InsertQuery`** - For INSERT queries

### Migration Path

#### Using Factory Methods (Recommended)

**Before:**
```php
$query = $db->getQuery(true);
$query->select('*')
    ->from('#__users')
    ->where('id = :id')
    ->bindValue(':id', 1);

$db->setQuery($query);
```

**After:**
```php
$query = $db->createSelectQuery()
    ->select('*')
    ->from('#__users')
    ->where('id = :id')
    ->bindValue(':id', 1);

$db->setQuery($query);
```

### Factory Methods

Use these factory methods on the `Database` instance:

```php
// SELECT query
$query = $db->createSelectQuery();

// DELETE query
$query = $db->createDeleteQuery();

// UPDATE query
$query = $db->createUpdateQuery();

// INSERT query
$query = $db->createInsertQuery();
```

### Detailed Examples

#### SELECT Query

**Before:**
```php
$query = $db->getQuery(true);
$query->select(['id', 'name', 'email'])
    ->from('#__users')
    ->leftJoin('#__profiles ON profiles.user_id = users.id')
    ->where('users.active = 1')
    ->group('users.id')
    ->having('COUNT(*) > 1')
    ->order('users.name ASC')
    ->setLimit(10, 0);
```

**After:**
```php
$query = $db->createSelectQuery()
    ->select(['id', 'name', 'email'])
    ->from('#__users')
    ->leftJoin('#__profiles ON profiles.user_id = users.id')
    ->where('users.active = 1')
    ->group('users.id')
    ->having('COUNT(*) > 1')
    ->order('users.name ASC')
    ->setLimit(10, 0);
```

#### DELETE Query

**Before:**
```php
$query = $db->getQuery(true);
$query->delete('#__users')
    ->where('id = :id')
    ->bindValue(':id', 123);
```

**After:**
```php
$query = $db->createDeleteQuery()
    ->delete('#__users')
    ->where('id = :id')
    ->bindValue(':id', 123);
```

#### UPDATE Query

**Before:**
```php
$query = $db->getQuery(true);
$query->update('#__users')
    ->set('name = :name')
    ->set('email = :email')
    ->where('id = :id')
    ->bindValue(':name', 'John Doe')
    ->bindValue(':email', 'john@example.com')
    ->bindValue(':id', 123);
```

**After:**
```php
$query = $db->createUpdateQuery()
    ->update('#__users')
    ->set('name = :name')
    ->set('email = :email')
    ->where('id = :id')
    ->bindValue(':name', 'John Doe')
    ->bindValue(':email', 'john@example.com')
    ->bindValue(':id', 123);
```

**Note:** The `update()` method now accepts either a string or an array of table names. This is useful for UPDATE queries with JOINs:

```php
// Single table
$query = $db->createUpdateQuery()
    ->update('#__users')
    ->set('name = :name');

// Multiple tables (for UPDATE with JOIN - PostgreSQL style)
$query = $db->createUpdateQuery()
    ->update(['#__posts AS p', '#__users AS u'])
    ->set('p.title = :title')
    ->where('p.user_id = u.id')
    ->where('u.email = :email');

// Multiple tables (MySQL/MariaDB style with JOIN clause)
$query = $db->createUpdateQuery()
    ->update('#__users AS u')
    ->innerJoin('#__profiles AS p ON u.id = p.user_id')
    ->set('u.name = p.display_name');
```

#### INSERT Query

**Before:**
```php
$query = $db->getQuery(true);
$query->insert('#__users')
    ->columns(['name', 'email'])
    ->values([':name', ':email'])
    ->bindValue(':name', 'John Doe')
    ->bindValue(':email', 'john@example.com');
```

**After:**
```php
$query = $db->createInsertQuery()
    ->insert('#__users')
    ->columns(['name', 'email'])
    ->values([':name', ':email'])
    ->bindValue(':name', 'John Doe')
    ->bindValue(':email', 'john@example.com');
```

### Benefits of New Structure

1. **No Null Properties** - Each query class only contains properties it needs
2. **Better IDE Support** - Autocomplete shows only relevant methods for each query type
3. **Type Safety** - Can type-hint specific query types
4. **Clearer Code** - Immediately obvious what kind of query you're building
5. **Easier Testing** - Each query type can be tested independently
6. **Single Responsibility** - Each class has a clear, focused purpose

### Backward Compatibility

The old `JDZ\Database\Query` class is **deprecated** but remains functional for backward compatibility. It will be removed in the next major version.

**Important:** `$db->getQuery()` is now deprecated. Use the factory methods instead:
- `createSelectQuery()`
- `createDeleteQuery()`
- `createUpdateQuery()`
- `createInsertQuery()`

---

## Deprecated Query Methods

The following methods in the `Query` class are deprecated and will be removed in a future version. They currently delegate to the `Database` class but should be accessed directly from the database instance.

### Database-Specific SQL Functions

These methods have been moved to the `Database` class because they generate database-specific SQL syntax.

#### `currentTimestamp()`

**Before:**
```php
$timestamp = $query->currentTimestamp();
```

**After:**
```php
$timestamp = $db->currentTimestamp();
```

**Reason:** Database-specific SQL syntax
- MySQL/MariaDB: `NOW()`
- PostgreSQL/SQLite: `CURRENT_TIMESTAMP`

---

#### `concatenate(array $values, string $separator = '')`

**Before:**
```php
$concat = $query->concatenate(['field1', 'field2'], ', ');
```

**After:**
```php
$concat = $db->concatenate(['field1', 'field2'], ', ');
```

**Reason:** Database-specific syntax
- MySQL/MariaDB: `CONCAT_WS()` with separator, `CONCAT()` without
- PostgreSQL: `CONCAT()` with manually interspersed separators
- SQLite: `||` operator

---

#### `charLength(string $field)`

**Before:**
```php
$length = $query->charLength('username');
```

**After:**
```php
$length = $db->charLength('username');
```

**Reason:** Database-specific function names
- MySQL/MariaDB/PostgreSQL: `CHAR_LENGTH()`
- SQLite: `LENGTH()`

---

### Database Utility Methods

These methods are database operations, not query building operations, and should be called directly on the database instance.

#### `escape(string $text, bool $extra = false)`

**Before:**
```php
$escaped = $query->escape($text);
```

**After:**
```php
$escaped = $db->escape($text);
```

**Reason:** Database functionality, not query building

---

#### `quote(string $text, bool $escape = true)`

**Before:**
```php
$quoted = $query->quote($text);
```

**After:**
```php
$quoted = $db->quote($text);
```

**Reason:** Database functionality, not query building

---

#### `quoteName(string $name, ?string $as = null)`

**Before:**
```php
$quotedName = $query->quoteName('column');
$quotedName = $query->quoteName('table', 't');
```

**After:**
```php
$quotedName = $db->quoteName('column');
$quotedName = $db->quoteName('table', 't');
```

**Reason:** Database functionality, not query building

---

#### `dateFormat()`

**Before:**
```php
$format = $query->dateFormat();
```

**After:**
```php
$format = $db->dateFormat; // Property access, not method call
```

**Reason:** Direct property access is more appropriate

---

#### `nullDate(bool $quoted = true)`

**Before:**
```php
$nullDate = $query->nullDate();        // Quoted
$nullDate = $query->nullDate(false);   // Unquoted
```

**After:**
```php
$nullDate = $db->quote($db->getNullDate()); // Quoted
$nullDate = $db->getNullDate();              // Unquoted
```

**Reason:** Database property access with optional quoting

---

#### `dump()`

**Before:**
```php
$sql = $query->dump();
```

**After:**
```php
$sql = str_replace('#__', $db->tablePrefix, (string)$query);
```

**Reason:** Simple string operation, no need for dedicated method

---

### Shorthand Aliases

These shorthand aliases are deprecated in favor of explicit method names on the database instance.

#### `e(string $text, bool $extra = false)`

**Before:**
```php
$escaped = $query->e($text);
```

**After:**
```php
$escaped = $db->escape($text);
```

**Reason:** Alias for `escape()` - use explicit method name

---

#### `q(string $text, bool $escape = true)`

**Before:**
```php
$quoted = $query->q($text);
```

**After:**
```php
$quoted = $db->quote($text);
```

**Reason:** Alias for `quote()` - use explicit method name

---

#### `qn(string $name, ?string $as = null)`

**Before:**
```php
$quotedName = $query->qn('column');
```

**After:**
```php
$quotedName = $db->quoteName('column');
```

**Reason:** Alias for `quoteName()` - use explicit method name

---

## Migration Summary

All deprecated methods fall into one of these categories:

1. **Database-specific SQL functions** - Moved to `Database` class for proper driver-specific implementations
2. **Database utility methods** - Should be accessed directly from the database instance
3. **Shorthand aliases** - Replaced with explicit method names for better code clarity

**Key Benefit:** The Query builder now focuses purely on query construction, while the Database class handles escaping, quoting, and database-specific SQL functions.

**Timeline:** These methods are currently marked as deprecated but remain functional. They will be removed in the next major version.
