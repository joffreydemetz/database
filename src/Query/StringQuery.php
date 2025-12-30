<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Query\Query;

/**
 * String Query
 * 
 * Holds a raw SQL query string
 * Allows for using custom SQL while maintaining the Query interface
 */
class StringQuery extends Query
{
    protected string $query = '';

    /**
     * Convert the query to a SQL string
     * 
     * @return string The SQL query string
     */
    public function toString(): string
    {
        return $this->query;
    }

    /**
     * Set the query string
     * 
     * @param string $query The SQL query string
     * @return static
     */
    public function setQuery(string $query): static
    {
        $this->query = $query;
        return $this;
    }
}
