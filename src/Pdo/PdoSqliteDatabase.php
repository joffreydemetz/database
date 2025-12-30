<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Pdo\PdoDatabase;

/**
 * SQLite Database Driver (PDO)
 * 
 * Supports SQLite-specific features via PDO sqlite driver
 */
class PdoSqliteDatabase extends PdoDatabase
{
    public string $nullDate = '1970-01-01 00:00:00';
    public string $nameQuote = '`';

    public function __construct(array $options)
    {
        $options['driver'] = 'sqlite';

        // SQLite doesn't use traditional host/port
        if (empty($options['host'])) {
            $options['host'] = '';
        }

        $options['port'] = 0;
        $options['socket'] = '';

        parent::__construct($options);
    }

    /**
     * Connect to SQLite database
     * SQLite uses file path as dbname
     */
    public function connect()
    {
        if (!$this->sqlConn) {
            $this->connection = new PdoSqliteConnection(
                $this->options['dbname'],  // File path for SQLite
                $this->options['user'] ?? '',
                $this->options['pass'] ?? ''
            );

            $this->connection->driver = 'sqlite';

            $this->sqlConn = $this->connection->connect($this->options['driverOptions']);

            // Enable foreign key constraints by default
            $this->sqlConn->exec('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * SQLite has simpler escaping than MySQL
     */
    public function escape(string $text, bool $extra = false): string
    {
        if (\is_int($text)) {
            return $text;
        }

        if (\is_float($text)) {
            return str_replace(',', '.', (string)$text);
        }

        // SQLite uses '' for escaping single quotes
        $text = str_replace("'", "''", (string)$text);

        if ($extra) {
            $text = \addcslashes($text, '%_');
        }

        return $text;
    }

    // Database Information Methods

    public function getVersion(): string
    {
        $this->setQuery('SELECT sqlite_version()');
        return (string)$this->loadResult();
    }

    public function getCollation(): string|null
    {
        // SQLite doesn't have traditional collations like MySQL
        // Default is BINARY
        return 'BINARY';
    }

    public function getDatabaseName(): string
    {
        // For SQLite, return the database file path
        return $this->options['dbname'];
    }

    // Table Query Methods

    public function getTableList(): array
    {
        $this->setQuery("
            SELECT name 
            FROM sqlite_master 
                WHERE type = 'table' 
                AND name NOT LIKE 'sqlite_%'
                ORDER BY name
            ");

        return $this->loadColumn() ?: [];
    }

    public function getTableColumns(string $table, bool $full = false): array
    {
        $table = str_replace('#__', $this->tablePrefix, $table);

        $this->setQuery('PRAGMA table_info(' . $this->quoteName($table) . ')');

        $fields = $this->loadObjectList();
        $result = [];

        if ($fields) {
            foreach ($fields as $field) {
                // Convert SQLite PRAGMA output to MySQL-like format
                $obj = (object)[
                    'Field' => $field->name,
                    'Type' => $field->type,
                    'Null' => $field->notnull ? 'NO' : 'YES',
                    'Default' => $field->dflt_value,
                    'Key' => $field->pk ? 'PRI' : '',
                ];

                if ($full) {
                    $obj->Extra = $field->pk && strtoupper($field->type) === 'INTEGER' ? 'auto_increment' : '';
                }

                $result[$field->name] = $obj;
            }
        }

        return $result;
    }

    public function getTableKeys(string $table): array
    {
        $table = str_replace('#__', $this->tablePrefix, $table);

        $keys = [];

        // Get primary keys
        $this->setQuery('PRAGMA table_info(' . $this->quoteName($table) . ')');
        $columns = $this->loadObjectList();

        if ($columns) {
            foreach ($columns as $col) {
                if ($col->pk) {
                    $keys[] = (object)[
                        'Key_name' => 'PRIMARY',
                        'Column_name' => $col->name,
                        'Non_unique' => 0,
                    ];
                }
            }
        }

        // Get indexes
        $this->setQuery('PRAGMA index_list(' . $this->quoteName($table) . ')');
        $indexes = $this->loadObjectList();

        if ($indexes) {
            foreach ($indexes as $index) {
                $this->setQuery('PRAGMA index_info(' . $this->quoteName($index->name) . ')');
                $indexCols = $this->loadObjectList();

                if ($indexCols) {
                    foreach ($indexCols as $col) {
                        $keys[] = (object)[
                            'Key_name' => $index->name,
                            'Column_name' => $col->name,
                            'Non_unique' => $index->unique ? 0 : 1,
                        ];
                    }
                }
            }
        }


        return $keys;
    }

    public function getTableCreate(array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            $this->setQuery(
                "
                SELECT sql 
                FROM sqlite_master 
                WHERE type = 'table' 
                AND name = " . $this->quote($table)
            );
            $sql = $this->loadResult();
            if ($sql) {
                $results[$table] = $sql;
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
        $this->setQuery('ALTER TABLE ' . $this->quoteName($oldTable) . ' RENAME TO ' . $this->quoteName($newTable));
        $this->execute();
    }

    public function truncateTable(string $table): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        // SQLite doesn't have TRUNCATE, use DELETE
        $table = str_replace('#__', $this->tablePrefix, $table);
        $this->setQuery('DELETE FROM ' . $this->quoteName($table));
        $this->execute();

        // Reset auto-increment counter if sqlite_sequence table exists
        try {
            $this->setQuery("DELETE FROM sqlite_sequence WHERE name = " . $this->quote($table));
            $this->execute();
        } catch (\Exception $e) {
            // Ignore if sqlite_sequence doesn't exist (no AUTOINCREMENT columns)
        }
    }

    // Transaction Methods (SQLite-specific)

    public function transactionStart(): void
    {
        // SQLite supports BEGIN, BEGIN TRANSACTION, BEGIN IMMEDIATE, BEGIN EXCLUSIVE
        $this->setQuery('BEGIN TRANSACTION');
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

    /**
     * Get the last insert ID
     * SQLite handles this automatically
     */
    public function insertid(): int
    {
        $this->connect();
        return (int)$this->sqlConn->lastInsertId();
    }

    public function charLength(string $field): string
    {
        return 'LENGTH(' . $field . ')';
    }

    public function concatenate(array $values, string $separator = ''): string
    {
        if ($separator) {
            // SQLite uses || operator, need to intersperse separator
            $parts = [];
            foreach ($values as $i => $value) {
                if ($i > 0) {
                    $parts[] = $this->quote($separator);
                }
                $parts[] = $value;
            }
            return '(' . implode(' || ', $parts) . ')';
        }
        return '(' . implode(' || ', $values) . ')';
    }
}
