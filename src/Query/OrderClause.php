<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

/**
 * ORDER BY clause trait
 * 
 * Provides ORDER BY functionality for queries
 */
trait OrderClause
{
    protected array $order = [];

    /**
     * Add ORDER BY columns
     * 
     * @param array|string $columns Column(s) to order by (e.g., 'name ASC', ['name ASC', 'id DESC'])
     * @return static
     */
    public function order(array|string $columns): static
    {
        $this->order = array_merge($this->order, is_array($columns) ? $columns : [$columns]);
        return $this;
    }

    /**
     * Build the ORDER BY clause
     * 
     * @return string The ORDER BY SQL clause
     */
    protected function buildOrderClause(): string
    {
        if (empty($this->order)) {
            return '';
        }

        return PHP_EOL . 'ORDER BY ' . implode(', ', $this->order);
    }
}
