<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Query\Query;
use JDZ\Database\Query\JoinClause;
use JDZ\Database\Query\WhereClause;
use JDZ\Database\Query\OrderClause;
use JDZ\Database\Query\LimitClause;

/**
 * SELECT Query Builder
 * 
 * Builds SELECT queries with support for:
 * - Column selection
 * - FROM clause
 * - JOIN operations
 * - WHERE conditions
 * - GROUP BY
 * - HAVING
 * - ORDER BY
 * - UNION operations
 * - LIMIT/OFFSET
 */
class SelectQuery extends Query
{
    use WhereClause, JoinClause, OrderClause, LimitClause;

    protected array $select = [];
    protected array $from = [];
    protected array $group = [];
    protected array $having = [];

    /**
     * Convert the query to a SQL string
     */
    public function toString(): string
    {
        $query = '';

        // SELECT clause
        if (!empty($this->select)) {
            $query .= 'SELECT ' . implode(', ', $this->select);
        }

        // FROM clause
        if (!empty($this->from)) {
            $query .= PHP_EOL . 'FROM ' . implode(', ', $this->from);
        }

        // JOIN clauses
        $query .= $this->buildJoinClause();

        // WHERE clause
        $query .= $this->buildWhereClause();

        // GROUP BY clause
        if (!empty($this->group)) {
            $query .= PHP_EOL . 'GROUP BY ' . implode(', ', $this->group);
        }

        // HAVING clause
        if (!empty($this->having)) {
            $query .= PHP_EOL . 'HAVING ' . implode(' AND ', $this->having);
        }

        // ORDER BY clause
        $query .= $this->buildOrderClause();

        // LIMIT/OFFSET clause
        $query .= $this->buildLimitClause();

        return $query;
    }

    /**
     * Set the SELECT columns
     */
    public function select(array|string $columns): static
    {
        $this->select = array_merge($this->select, is_array($columns) ? $columns : [$columns]);
        return $this;
    }

    /**
     * Set the FROM table(s)
     */
    public function from(array|string $tables): static
    {
        $this->from = array_merge($this->from, is_array($tables) ? $tables : [$tables]);
        return $this;
    }

    /**
     * Add GROUP BY columns
     */
    public function group(array|string $columns): static
    {
        $this->group = array_merge($this->group, is_array($columns) ? $columns : [$columns]);
        return $this;
    }

    /**
     * Add HAVING conditions
     */
    public function having(array|string $conditions): static
    {
        $this->having = array_merge($this->having, is_array($conditions) ? $conditions : [$conditions]);
        return $this;
    }
}
