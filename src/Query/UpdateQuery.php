<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Query\Query;
use JDZ\Database\Query\JoinClause;
use JDZ\Database\Query\WhereClause;

/**
 * UPDATE Query Builder
 * 
 * Builds UPDATE queries with support for:
 * - UPDATE table
 * - SET assignments
 * - JOIN operations (for UPDATE with JOIN)
 * - WHERE conditions
 */
class UpdateQuery extends Query
{
    use WhereClause, JoinClause;

    protected array $tables = [];
    protected bool $ignore = false;
    protected array $set = [];
    protected string $setGlue = ',';

    /**
     * Convert the query to a SQL string
     */
    public function toString(): string
    {
        $query = 'UPDATE' . ($this->ignore ? ' IGNORE' : '') . ' ' . implode(', ', $this->tables);

        $query .= $this->buildJoinClause();

        if (!empty($this->set)) {
            $query .= PHP_EOL . 'SET ' . implode(", ", $this->set);
        }

        $query .= $this->buildWhereClause();

        return $query;
    }

    /**
     * Set the table(s) to update
     * 
     * @param array|string $tables Single table name or array of table names (for UPDATE with JOIN)
     * @param bool $ignore Whether to use UPDATE IGNORE
     */
    public function update(array|string $tables, bool $ignore = false): static
    {
        $this->tables = is_array($tables) ? $tables : [$tables];
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * Set the column assignments
     */
    public function set(array|string $conditions, string $glue = ','): static
    {
        $this->setGlue = strtoupper($glue);
        $this->set = array_merge($this->set, is_array($conditions) ? $conditions : [$conditions]);
        return $this;
    }
}
