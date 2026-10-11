<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Exception\QueryException;

/**
 * WHERE clause trait
 * 
 * Provides WHERE clause functionality for queries
 */
trait WhereClause
{
    protected array $where = [];
    protected string $whereGlue = 'AND';

    /**
     * Add WHERE conditions, glued with AND (default) or OR.
     *
     * One glue per statement: the glue of the first call holds, a later call
     * with the other one is refused (2.3.0 — it used to win silently, so
     * ->where('a', 'OR')->where('b') glued everything with AND).
     *
     * @throws QueryException on a glue other than AND / OR, or a changed glue
     */
    public function where(array|string $conditions, string $glue = 'AND'): static
    {
        $glue = strtoupper(trim($glue));

        if (!in_array($glue, ['AND', 'OR'], true)) {
            throw new QueryException('The WHERE glue must be AND or OR, "' . $glue . '" given.');
        }

        if ([] !== $this->where && $glue !== $this->whereGlue) {
            throw new QueryException('The WHERE glue cannot change from ' . $this->whereGlue . ' to ' . $glue . ' within one statement.');
        }

        $this->whereGlue = $glue;
        $this->where = array_merge($this->where, is_array($conditions) ? $conditions : [$conditions]);
        return $this;
    }

    /**
     * Build the WHERE clause
     * 
     * @return string The WHERE SQL clause
     */
    protected function buildWhereClause(): string
    {
        if (empty($this->where)) {
            return '';
        }

        return PHP_EOL . 'WHERE ' . implode(' ' . $this->whereGlue . ' ', $this->where);
    }
}
