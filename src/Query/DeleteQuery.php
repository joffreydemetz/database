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
 * DELETE Query Builder
 * 
 * Builds DELETE queries with support for:
 * - DELETE FROM table
 * - JOIN operations (for DELETE with JOIN)
 * - WHERE conditions
 */
class DeleteQuery extends Query
{
    use WhereClause, JoinClause;

    protected array $from = [];

    /**
     * Convert the query to a SQL string
     */
    public function toString(): string
    {
        $query = 'DELETE';

        if (!empty($this->from)) {
            $query .= PHP_EOL . 'FROM ' . implode(', ', $this->from);
        }

        $query .= $this->buildJoinClause();
        $query .= $this->buildWhereClause();

        return $query;
    }

    /**
     * Set the table to delete from
     */
    public function delete(string $table): static
    {
        $this->from($table);
        return $this;
    }

    /**
     * Set the FROM table
     */
    public function from(array|string $tables): static
    {
        $this->from = array_merge($this->from, is_array($tables) ? $tables : [$tables]);
        return $this;
    }
}
