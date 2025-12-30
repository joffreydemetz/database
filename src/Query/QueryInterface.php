<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

/**
 * Query Interface
 * 
 * Defines the contract for query objects
 */
interface QueryInterface
{
    /**
     * Convert the query to a SQL string
     * 
     * @return string The SQL query string
     */
    public function toString(): string;

    /**
     * Bind a parameter to the query
     * 
     * @param string|int $key Parameter key
     * @param mixed $value Parameter value
     * @param string|int $dataType Data type
     * @param int $maxLength Maximum length
     * @param array $driverOptions Driver-specific options
     * @return static
     */
    public function bindParam(string|int $key, mixed $value, string|int $dataType = 'string', int $maxLength = 0, array $driverOptions = []): static;

    /**
     * Bind a value to the query
     * 
     * @param string|int $key Parameter key
     * @param mixed $value Parameter value
     * @param string|int $dataType Data type
     * @return static
     */
    public function bindValue(string|int $key, mixed $value, string|int $dataType = 'string'): static;

    /**
     * Unbind one or more parameters
     * 
     * @param string|int|array $key Parameter key(s) to unbind
     * @return static
     */
    public function unbind(string|int|array $key): static;

    /**
     * Bind an array of values
     * 
     * @param array $data Associative array of key-value pairs
     * @param string $dataType Data type for all values
     * @return static
     */
    public function bindArray(array $data, string $dataType = 'string'): static;

    /**
     * Get all bound parameters
     * 
     * @return array Array of Parameter objects
     */
    public function getBounded(): array;
}
