<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Query\Query;

/**
 * INSERT Query Builder
 * 
 * Builds INSERT queries with support for:
 * - INSERT INTO table
 * - Column specification
 * - VALUES (multiple rows)
 * - SET syntax (INSERT ... SET)
 * - INSERT IGNORE
 */
class InsertQuery extends Query
{
    protected string $table = '';
    protected bool $ignore = false;
    protected array $columns = [];
    protected array $values = [];
    protected array $set = [];
    protected string $setGlue = ',';

    /**
     * Convert the query to a SQL string
     */
    public function toString(): string
    {
        $query = 'INSERT' . ($this->ignore ? ' IGNORE' : '') . ' INTO ' . $this->table;

        // SET method
        if (!empty($this->set)) {
            $query .= PHP_EOL . 'SET ' . implode(", ", $this->set);
        }
        // Columns-Values method
        elseif (!empty($this->values)) {
            if (!empty($this->columns)) {
                $query .= PHP_EOL . '(' . implode(', ', $this->columns) . ')';
            }

            $query .= PHP_EOL . 'VALUES ';
            $query .= '(' . implode('), (', $this->values) . ')';
        }

        return $query;
    }

    /**
     * Set the table to insert into
     */
    public function insert(string $table, bool $ignore = false): static
    {
        $this->table = $table;
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * Set the columns for INSERT
     */
    public function columns(array|string $columns): static
    {
        $this->columns = array_merge($this->columns, is_array($columns) ? $columns : [$columns]);
        return $this;
    }

    /**
     * Add values for INSERT
     */
    public function values(array|string $values): static
    {
        $this->values = array_merge($this->values, is_array($values) ? $values : [$values]);
        return $this;
    }

    /**
     * Set column assignments (INSERT ... SET syntax)
     */
    public function set(array|string $conditions, string $glue = ','): static
    {
        $this->setGlue = strtoupper($glue);
        $this->set = array_merge($this->set, is_array($conditions) ? $conditions : [$conditions]);
        return $this;
    }
}
