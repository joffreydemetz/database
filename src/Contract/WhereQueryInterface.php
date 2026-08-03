<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Contract;

/**
 * Where Query Interface
 *
 * Defines the contract for query objects carrying a WHERE clause
 */
interface WhereQueryInterface extends QueryInterface
{
    /**
     * Add WHERE conditions
     *
     * @param array|string $conditions One or more conditions
     * @param string $glue Glue between the conditions (AND or OR)
     * @return static
     */
    public function where(array|string $conditions, string $glue = 'AND'): static;
}
