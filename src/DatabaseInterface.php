<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\StatementInterface;
use JDZ\Database\Query\QueryInterface;

/**
 * Database Driver Interface
 * 
 * Defines the contract for all database drivers (PDO MySQL, PDO PostgreSQL, PDO SQLite, MySQLi, MariaDB)
 */
interface DatabaseInterface
{
  /**
   * Establish database connection
   * 
   * @throws  DatabaseException
   */
  public function connect();

  /**
   * Check if database is connected
   * 
   * @return  bool  True if connected, false otherwise
   */
  public function connected(): bool;

  /**
   * Disconnect from database
   */
  public function disconnect();

  /**
   * Get the ID generated from the previous INSERT operation
   * 
   * @return  int  The insert ID
   */
  public function insertid(): int;

  /**
   * Escape a string for safe SQL usage
   * 
   * @param   string  $text   String to escape
   * @param   bool    $extra  If true, also escape wildcards (% and _)
   * @return  string  Escaped string
   */
  public function escape(string $text, bool $extra = false): string;

  /**
   * Prepare a SQL statement
   * 
   * @param   string  $query  The SQL query to prepare
   * @return  StatementInterface  The prepared statement
   * @throws  DatabaseException
   */
  public function prepareStatement(string $query): StatementInterface;

  /**
   * Execute the current query
   * 
   * @throws  DatabaseException
   */
  public function execute();

  /**
   * Get the current Query object
   */
  public function getQuery(): ?QueryInterface;

  /**
   * Set the SQL query object to execute
   * 
   * @param   QueryInterface|string  $query   The SQL query
   * @return  QueryInterface  The query object
   */
  public function setQuery(QueryInterface|string $query): QueryInterface;

  /**
   * Get the null date string for this database
   * 
   * @param   bool    $dateTime  If true, return datetime format, else date only
   * @return  string  The null date string
   */
  public function getNullDate(bool $dateTime = true): string;

  /**
   * Check if a date string is a null date
   * 
   * @param   string  $testDate  The date to test
   * @return  bool    True if null date
   */
  public function isNullDate(string $testDate): bool;

  /**
   * Load a single associative array from the query result
   * 
   * @return  array|null  Associative array or null if no result
   */
  public function loadAssoc(): array|null;

  /**
   * Load a single indexed array from the query result
   * 
   * @return  array|null  Indexed array or null if no result
   */
  public function loadRow(): array|null;

  /**
   * Load a single column from all result rows
   * 
   * @param   string|int  $column  Column index or name
   * @return  array       Array of column values
   */
  public function loadColumn(string|int $column = 0): array;

  /**
   * Load a single result value
   * 
   * @return  mixed  Single value or null
   */
  public function loadResult();

  /**
   * Load a single object from the query result
   * 
   * @param   string  $class  Class name to instantiate
   * @return  object|null  Object or null if no result
   */
  public function loadObject(string $class = \stdClass::class): object|null;

  /**
   * Load all result rows as associative arrays
   * 
   * @param   string  $key     Column to use as array key
   * @param   string  $column  Column to use as value (if empty, use full row)
   * @return  array   Array of associative arrays
   */
  public function loadAssocList(string $key = '', string $column = ''): array;

  /**
   * Load all result rows as indexed arrays
   * 
   * @param   string  $key  Column to use as array key
   * @return  array   Array of indexed arrays
   */
  public function loadRowList(string $key = ''): array;

  /**
   * Load all result rows as objects
   * 
   * @param   string  $key    Column to use as array key
   * @param   string  $class  Class name to instantiate
   * @return  array   Array of objects
   */
  public function loadObjectList(string $key = '', string $class = \stdClass::class): array;

  /**
   * Get the number of rows in the result set
   * 
   * @return  int  Number of rows
   */
  public function getNumRows(): int;

  /**
   * Get the number of rows affected by the last query
   * 
   * @return  int  Number of affected rows
   */
  public function getAffectedRows(): int;

  /**
   * Check if a record exists in a table based on properties
   * 
   * @param   string  $tblName      Table name
   * @param   array   $properties   Column=>value pairs to match
   * @param   string  $selectField  Field to select
   * @return  bool    True if record exists
   */
  public function recordExists(string $tblName, array $properties, string $selectField = 'id'): bool;

  /**
   * Quote a string value for SQL usage
   * 
   * @param   string  $text    String to quote
   * @param   bool    $escape  If true, escape the string first
   * @return  string  Quoted string
   */
  public function quote(string $text, bool $escape = true): string;

  /**
   * Quote an array of values for SQL IN clause
   * 
   * @param   array   $values  Array of values
   * @return  string  Comma-separated quoted values
   */
  public function valuesToString(array $values): string;

  /**
   * Quote a database object name (table, column, etc.)
   * 
   * @param   string       $name  Name to quote
   * @param   string|null  $as    Optional alias
   * @return  string  Quoted name
   */
  public function quoteName(string $name, string|null $as = null): string;

  // Database Information Methods

  /**
   * Get the database server version
   * 
   * @return  string  Version string
   */
  public function getVersion(): string;

  /**
   * Get the database collation
   * 
   * @return  string|null  Collation name
   */
  public function getCollation(): string|null;

  /**
   * Get the current database name
   * 
   * @return  string  Database name
   */
  public function getDatabaseName(): string;

  // Table Introspection Methods

  /**
   * Get list of all tables in the database
   * 
   * @return  array  Array of table names
   */
  public function getTableList(): array;

  /**
   * Get column information for a table
   * 
   * @param   string  $table  Table name
   * @param   bool    $full   If true, return full column information
   * @return  array   Associative array of column information
   */
  public function getTableColumns(string $table, bool $full = false): array;

  /**
   * Get key/index information for a table
   * 
   * @param   string  $table  Table name
   * @return  array   Array of key information
   */
  public function getTableKeys(string $table): array;

  /**
   * Get CREATE TABLE statements for tables
   * 
   * @param   array  $tables  Array of table names
   * @return  array  Associative array of table => CREATE statement
   */
  public function getTableCreate(array $tables): array;

  /**
   * Check if a table exists in the database
   * 
   * @param   string  $table  Table name
   * @return  bool    True if table exists
   */
  public function tableExists(string $table): bool;

  /**
   * Drop a table from the database
   * 
   * @param   string  $table  Table name
   */
  public function dropTable(string $table): void;

  /**
   * Rename a table
   * 
   * @param   string  $oldTable  Current table name
   * @param   string  $newTable  New table name
   */
  public function renameTable(string $oldTable, string $newTable): void;

  /**
   * Truncate a table (remove all rows)
   * 
   * @param   string  $table  Table name
   */
  public function truncateTable(string $table): void;

  // Transaction Methods

  /**
   * Start a database transaction
   */
  public function transactionStart(): void;

  /**
   * Commit the current transaction
   */
  public function transactionCommit(): void;

  /**
   * Rollback the current transaction
   */
  public function transactionRollback(): void;

  /**
   * Get the current timestamp SQL function
   * 
   * @return string The SQL function name for current timestamp
   */
  public function currentTimestamp(): string;

  /**
   * Get the character length SQL function
   * 
   * @param string $field The field or expression to measure
   * @return string The SQL function call for character length
   */
  public function charLength(string $field): string;

  /**
   * Concatenate multiple values into a single string
   * 
   * @param array $values Array of values to concatenate
   * @param string $separator Optional separator between values
   * @return string The SQL function call for concatenation
   */
  public function concatenate(array $values, string $separator = ''): string;
}
