<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Pdo\PdoDatabase;

/**
 * PostgreSQL Database Driver (PDO)
 * 
 * Supports PostgreSQL-specific features via PDO pgsql driver
 */
class PdoPostgresqlDatabase extends PdoDatabase
{
    public string $nullDate = '1970-01-01 00:00:00';
    public string $nameQuote = '"';

    public function __construct(array $options)
    {
        $options['driver'] = 'pgsql';

        if (empty($options['port'])) {
            $options['port'] = 5432;
        }

        parent::__construct($options);
    }

    /**
     * PostgreSQL uses different escaping than MySQL
     */
    public function escape(string $text, bool $extra = false): string
    {
        if (\is_int($text)) {
            return $text;
        }

        if (\is_float($text)) {
            return str_replace(',', '.', (string)$text);
        }

        // PostgreSQL uses '' for escaping single quotes
        $text = str_replace("'", "''", (string)$text);

        // PostgreSQL also needs backslash escaping
        $text = str_replace('\\', '\\\\', $text);

        if ($extra) {
            $text = \addcslashes($text, '%_');
        }

        return $text;
    }

    // Database Information Methods

    public function getVersion(): string
    {
        $this->setQuery('SELECT VERSION()');
        return (string)$this->loadResult();
    }

    public function getCollation(): string|null
    {
        $this->setQuery("SELECT datcollate FROM pg_database WHERE datname = current_database()");
        $result = $this->loadResult();
        return $result ? (string)$result : null;
    }

    public function getDatabaseName(): string
    {
        $this->setQuery('SELECT current_database()');
        return (string)$this->loadResult();
    }

    // Table Query Methods

    public function getTableList(): array
    {
        $this->setQuery("
            SELECT tablename 
            FROM pg_catalog.pg_tables 
            WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
            ORDER BY tablename
        ");
        return $this->loadColumn() ?: [];
    }

    public function getTableColumns(string $table, bool $full = false): array
    {
        $table = str_replace('#__', $this->tablePrefix, $table);

        if ($full) {
            $this->setQuery("
                SELECT 
                    column_name as \"Field\",
                    data_type as \"Type\",
                    is_nullable as \"Null\",
                    column_default as \"Default\",
                    '' as \"Extra\"
                FROM information_schema.columns
                WHERE table_name = " . $this->quote($table) . "
                ORDER BY ordinal_position
            ");
        } else {
            $this->setQuery("
                SELECT 
                    column_name as \"Field\",
                    data_type as \"Type\"
                FROM information_schema.columns
                WHERE table_name = " . $this->quote($table) . "
                ORDER BY ordinal_position
            ");
        }

        $fields = $this->loadObjectList();
        $result = [];

        if ($fields) {
            foreach ($fields as $field) {
                $result[$field->Field] = $field;
            }
        }

        return $result;
    }

    public function getTableKeys(string $table): array
    {
        $table = str_replace('#__', $this->tablePrefix, $table);

        $this->setQuery("
            SELECT 
                i.relname as \"Key_name\",
                a.attname as \"Column_name\",
                CASE WHEN ix.indisunique THEN 0 ELSE 1 END as \"Non_unique\",
                CASE WHEN ix.indisprimary THEN 'PRIMARY' ELSE '' END as \"Index_type\"
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relname = " . $this->quote($table) . "
            ORDER BY i.relname, a.attnum
        ");
        return $this->loadObjectList() ?: [];
    }

    public function getTableCreate(array $tables): array
    {
        // PostgreSQL doesn't have SHOW CREATE TABLE
        // Would need to reconstruct from information_schema
        // For now, return empty array
        return [];
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

        $table = str_replace('#__', $this->tablePrefix, $table);
        $this->setQuery('TRUNCATE TABLE ' . $this->quoteName($table) . ' RESTART IDENTITY');
        $this->execute();
    }

    // Transaction Methods (PostgreSQL-specific)

    public function transactionStart(): void
    {
        // PostgreSQL supports both BEGIN and START TRANSACTION
        $this->setQuery('BEGIN');
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
     * Get the last insert ID using PostgreSQL's RETURNING or currval
     * 
     * Note: For PostgreSQL, it's better to use RETURNING clause in INSERT queries
     */
    public function insertid(): int
    {
        $this->connect();

        // Try to get last value from a sequence
        // This assumes the sequence name follows PostgreSQL convention
        // Better approach is to use RETURNING id in the INSERT query
        try {
            return (int)$this->sqlConn->lastInsertId();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    /**
     * PostgreSQL-specific table prefix replacement
     * Also handles schema prefixes
     */
    protected function replacePrefix(string $sql): string
    {
        $sql = parent::replacePrefix($sql);

        // PostgreSQL uses public schema by default
        // You can extend this to handle schema.table notation
        return $sql;
    }

    public function concatenate(array $values, string $separator = ''): string
    {
        if ($separator) {
            // PostgreSQL doesn't have CONCAT_WS, use CONCAT with separators
            $parts = [];
            foreach ($values as $i => $value) {
                if ($i > 0) {
                    $parts[] = $this->quote($separator);
                }
                $parts[] = $value;
            }
            return 'CONCAT(' . implode(', ', $parts) . ')';
        }

        return 'CONCAT(' . implode(', ', $values) . ')';
    }
}
