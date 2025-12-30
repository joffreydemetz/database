<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Database;
use JDZ\Database\Pdo\PdoStatement;
use JDZ\Database\Exception\PrepareStatementFailureException;

class PdoDatabase extends Database
{
	public string $nullDate = '1000-01-01 00:00:00';
	public string $nameQuote = '`';

	/**
	 * @var    \PDO|null
	 */
	protected mixed $sqlConn;

	public function __construct(array $options)
	{
		if (empty($options['driver'])) {
			$options['driver'] = 'mysqli';
		}

		if (empty($options['port'])) {
			$options['port'] = 3306;
		}

		parent::__construct($options);
	}

	public function connect()
	{
		if (!$this->sqlConn) {
			$this->connection = new PdoConnection($this->options['host'], $this->options['dbname'], $this->options['user'], $this->options['pass']);
			$this->connection->charset = $this->options['charset'] ?? 'utf8';

			if ($this->options['driver']) {
				$this->connection->driver = $this->options['driver'];
			}

			if ($this->options['port']) {
				$this->connection->port = $this->options['port'];
			}

			if (!empty($this->options['socket'])) {
				$this->connection->socket = $this->options['socket'];
			}

			$this->sqlConn = $this->connection->connect($this->options['driverOptions']);
		}
	}

	public function connected(): bool
	{
		if (!$this->sqlConn) {
			return false;
		}

		try {
			// Simple query to test connection
			$this->sqlConn->query('SELECT 1');
			return true;
		} catch (\PDOException $e) {
			return false;
		}
	}

	public function insertid(): int
	{
		$this->connect();
		return $this->sqlConn->lastInsertId();
	}

	public function escape(string $text, bool $extra = false): string
	{
		if (\is_int($text)) {
			return $text;
		}

		if (\is_float($text)) {
			return str_replace(',', '.', (string)$text);
		}

		$text = str_replace("'", "''", (string)$text);
		return addcslashes($text, "\000\n\r\\\032");
	}

	// Database Information Methods

	public function getVersion(): string
	{
		$this->setQuery('SELECT VERSION()');
		return (string)$this->loadResult();
	}

	public function getCollation(): string|null
	{
		$this->setQuery('SELECT @@collation_database');
		$result = $this->loadResult();
		return $result ? (string)$result : null;
	}

	public function getDatabaseName(): string
	{
		$this->setQuery('SELECT DATABASE()');
		return (string)$this->loadResult();
	}

	// Table Query Methods

	public function getTableList(): array
	{
		$this->setQuery('SHOW TABLES');
		return $this->loadColumn() ?: [];
	}

	public function getTableColumns(string $table, bool $full = false): array
	{
		$table = str_replace('#__', $this->tablePrefix, $table);
		$this->setQuery('SHOW ' . ($full ? 'FULL ' : '') . 'COLUMNS FROM ' . $this->quoteName($table));

		$fields = $this->loadObjectList();
		$results = [];

		if ($fields) {
			foreach ($fields as $field) {
				$field->Type = preg_replace("/^([^\\(]+).*$/", "$1", $field->Type);
				$results[$field->Field] = $field;
			}
		}

		return $results;
	}

	public function getTableKeys(string $table): array
	{
		$table = str_replace('#__', $this->tablePrefix, $table);
		$this->setQuery('SHOW KEYS FROM ' . $this->quoteName($table));
		return $this->loadObjectList() ?: [];
	}

	public function getTableCreate(array $tables): array
	{
		$results = [];

		foreach ($tables as $table) {
			$this->setQuery('SHOW CREATE table ' . $this->quoteName($this->escape($table)));
			$row = $this->loadRow();
			if ($row) {
				$results[$table] = $row[1];
			}
		}

		return $results;
	}

	public function tableExists(string $table): bool
	{
		$table = str_replace('#__', $this->tablePrefix, $table);
		return in_array($table, $this->getTableList());
	}

	public function dropTable(string $table): void
	{
		if (!$this->tableExists($table)) {
			return;
		}

		$table = str_replace('#__', $this->tablePrefix, $table);
		$this->setQuery('DROP TABLE ' . $this->quoteName($table));
		$this->execute();
	}

	public function renameTable(string $oldTable, string $newTable): void
	{
		if (!$this->tableExists($oldTable) || $this->tableExists($newTable)) {
			return;
		}

		$oldTable = str_replace('#__', $this->tablePrefix, $oldTable);
		$newTable = str_replace('#__', $this->tablePrefix, $newTable);
		$this->setQuery('RENAME TABLE ' . $this->quoteName($oldTable) . ' TO ' . $this->quoteName($newTable));
		$this->execute();
	}

	public function truncateTable(string $table): void
	{
		if (!$this->tableExists($table)) {
			return;
		}

		$table = str_replace('#__', $this->tablePrefix, $table);
		$this->setQuery('TRUNCATE TABLE ' . $this->quoteName($table));
		$this->execute();
	}

	// MySQL-specific Methods

	protected bool $profiling = false;

	public function startProfiling(): void
	{
		$this->profiling = true;
		$this->setQuery('SET profiling = 1');
		$this->execute();
	}

	public function showProfiles(): array|null
	{
		if (!$this->profiling) {
			return null;
		}

		$profiles = [];
		$this->setQuery('SHOW PROFILES');

		$rows = $this->loadAssocList();
		if ($rows) {
			foreach ($rows as $row) {
				$this->setQuery('SHOW PROFILE FOR QUERY ' . $row['Query_ID']);
				$row['infos'] = $this->loadAssocList();
				$profiles[] = $row;
			}
		}

		return $profiles;
	}

	public function stopProfiling(): void
	{
		if ($this->profiling) {
			$this->setQuery('SET profiling = 0');
			$this->execute();
			$this->profiling = false;
		}
	}

	public function lockTable(string $table): void
	{
		$table = str_replace('#__', $this->tablePrefix, $table);
		$this->setQuery('LOCK TABLES ' . $this->quoteName($table) . ' WRITE');
		$this->execute();
	}

	public function unlockTables(): void
	{
		$this->setQuery('UNLOCK TABLES');
		$this->execute();
	}

	// Transaction Methods

	public function transactionStart(): void
	{
		$this->setQuery('START TRANSACTION');
		$this->execute();
	}

	public function transactionCommit(): void
	{
		$this->setQuery('COMMIT');
		$this->execute();
	}

	public function transactionRollback(): void
	{
		$this->setQuery('ROLLBACK');
		$this->execute();
	}

	public function prepareStatement(string $query): PdoStatement
	{
		try {
			return new PdoStatement($this->sqlConn->prepare($query, $this->options['driverOptions']));
		} catch (\PDOException $e) {
			throw new PrepareStatementFailureException($e->getMessage(), (int)$e->getCode(), $e);
		}
	}

	public function currentTimestamp(): string
	{
		return 'NOW()';
	}
}
