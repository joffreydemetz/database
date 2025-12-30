<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Query\Query;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\OrderClause;
use JDZ\Database\Query\LimitClause;

/**
 * UNION Query Builder
 * 
 * Combines multiple SELECT queries with UNION or UNION DISTINCT.
 * Each query can individually be marked as DISTINCT.
 * 
 * Example:
 * ```php
 * $union = new UnionQuery();
 * $union->addQuery("SELECT id, name FROM users")
 *       ->addQuery("SELECT id, name FROM admins", true)  // UNION DISTINCT
 *       ->addQuery("SELECT id, name FROM guests");        // UNION ALL
 * ```
 */
class UnionQuery extends Query
{
    use OrderClause, LimitClause;

    /**
     * Array of queries with their DISTINCT flag
     * Each item: ['query' => string, 'distinct' => bool]
     */
    protected array $queries = [];

    /**
     * Convert the UNION query to a SQL string
     */
    public function toString(): string
    {
        if (empty($this->queries)) {
            return '';
        }

        $parts = [];

        foreach ($this->queries as $i => $item) {
            if ($i === 0) {
                // First query has no UNION keyword
                $parts[] = (string)$item['query'];
            } else {
                // Subsequent queries prepend UNION [DISTINCT|ALL]
                $union = $item['distinct'] ? 'UNION DISTINCT' : 'UNION';
                $parts[] = $union . PHP_EOL . (string)$item['query'];
            }
        }

        $query = implode(PHP_EOL, $parts);

        // ORDER BY clause (applies to entire UNION result)
        $query .= $this->buildOrderClause();

        // LIMIT/OFFSET clause
        $query .= $this->buildLimitClause();

        return $query;
    }

    /**
     * Add a query to the UNION
     * 
     * @param SelectQuery|StringQuery|string $query The SELECT query to add
     * @param bool $distinct Whether to use UNION DISTINCT (default: false = UNION ALL)
     * @return static
     */
    public function addQuery(SelectQuery|StringQuery|string $query, bool $distinct = false): static
    {
        $this->queries[] = [
            'query' => trim((string)$query),
            'distinct' => $distinct
        ];

        return $this;
    }

    /**
     * Add a query with UNION DISTINCT
     * 
     * @param SelectQuery|StringQuery|string $query The SELECT query to add
     * @return static
     */
    public function addQueryDistinct(SelectQuery|StringQuery|string $query): static
    {
        return $this->addQuery($query, true);
    }
}
