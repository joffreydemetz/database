<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

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
     * Add WHERE conditions
     */
    public function where(array|string $conditions, string $glue = 'AND'): static
    {
        $this->whereGlue = strtoupper($glue);
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
