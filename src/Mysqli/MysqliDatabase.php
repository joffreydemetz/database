<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Mysqli;

use JDZ\Database\Database;
use JDZ\Database\Mysqli\MysqliStatement;
use JDZ\Database\Exception\PrepareStatementFailureException;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;

class MysqliDatabase extends Database
{
	public string $nullDate = '1000-01-01 00:00:00';
	public string $nameQuote = '`';
	public bool $mariadb = false;

	/**
	 * @var    \mysqli|null
	 */
	protected mixed $sqlConn;

	public function __construct(array $options)
	{
		if (empty($options['sqlModes'])) {
			$options['sqlModes'] = [
				// 'ONLY_FULL_GROUP_BY',
				'STRICT_TRANS_TABLES',
				// 'NO_ZERO_IN_DATE',
				// 'NO_ZERO_DATE',
				'ERROR_FOR_DIVISION_BY_ZERO',
				'NO_ENGINE_SUBSTITUTION',
			];
		}

		parent::__construct($options);
	}

	public function connect()
	{
		if (!$this->sqlConn) {
			$this->connection = new MysqliConnection($this->options['host'], $this->options['dbname'], $this->options['user'], $this->options['pass']);
			$this->connection->charset = $this->options['charset'];

			if ($this->options['port']) {
				$this->connection->port = $this->options['port'];
			}

			if (!empty($this->options['socket'])) {
				$this->connection->socket = $this->options['socket'];
			}

			$this->sqlConn = $this->connection->connect($this->options['driverOptions']);

			if (!empty($this->options['sqlModes'])) {
				$this->setQuery('SET @@SESSION.sql_mode = \'' . implode(',', $this->options['sqlModes']) . '\';');
				$this->execute();
			}

			$this->mariadb = stripos($this->sqlConn->server_info, 'mariadb') !== false;
		}
	}

	public function connected(): bool
	{
		if ($this->sqlConn) {
			return $this->sqlConn->ping();
		}

		return false;
	}

	public function disconnect()
	{
		if ($this->sqlConn) {
			$this->sqlConn->close();
		}

		parent::disconnect();
	}

	public function insertid(): int
	{
		$this->connect();

		return $this->sqlConn->insert_id;
	}

	public function escape(string $text, bool $extra = false): string
	{
		if (\is_int($text)) {
			return $text;
		}

		if (\is_float($text)) {
			return str_replace(',', '.', (string)$text);
		}

		$this->connect();

		$result = $this->sqlConn->real_escape_string((string)$text);

		if (true === $extra) {
			$result = \addcslashes($result, '%_');
		}

		return $result;
	}

	// Database Information Methods

	public function getVersion(): string
	{
		$this->connect();
		return $this->sqlConn->server_info;
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
		$result = [];

		if ($fields) {
			foreach ($fields as $field) {
				$field->Type = preg_replace("/^([^\\(]+).*$/", "$1", $field->Type);
				$result[$field->Field] = $field;
			}
		}

		return $result;
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
				$profiles[] = (object)$row;
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
		$sql = 'LOCK TABLES ' . $this->quoteName($table) . ' WRITE';

		// LOCK TABLES cannot be used in prepared statements
		if (!$this->sqlConn->query($sql)) {
			throw new ExecutionFailureException($this->sqlConn->error);
		}
	}

	public function unlockTables(): void
	{
		// UNLOCK TABLES cannot be used in prepared statements
		if (!$this->sqlConn->query('UNLOCK TABLES')) {
			throw new ExecutionFailureException($this->sqlConn->error);
		}
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

	public function prepareStatement(string $query): MysqliStatement
	{
		try {
			return new MysqliStatement($this->sqlConn, $query);
		} catch (\mysqli_sql_exception $e) {
			throw new PrepareStatementFailureException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function currentTimestamp(): string
	{
		return 'NOW()';
	}
}
