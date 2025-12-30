<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

/**
 * Database Statement Interface
 * 
 * Defines the contract for prepared statement implementations
 * Supports both PDOStatement and mysqli_stmt wrappers
 */
interface StatementInterface
{
	/**
	 * Bind a parameter to a variable
	 * 
	 * @param   string|int  $parameter      Parameter identifier (name or position)
	 * @param   mixed       $variable       Variable to bind (by reference)
	 * @param   string      $dataType       Data type (string, int, bool, lob, null)
	 * @param   int|null    $length         Maximum length (optional)
	 * @param   array       $driverOptions  Driver-specific options
	 * @return  bool        True on success
	 */
	public function bindParam(string|int $parameter, &$variable, string $dataType = 'string', ?int $length = null, array $driverOptions = []): bool;

	/**
	 * Close the cursor, enabling the statement to be executed again
	 */
	public function closeCursor(): void;

	/**
	 * Get the error code from the last statement operation
	 * 
	 * @return  int  Error code
	 */
	public function errorCode(): int;

	/**
	 * Get the error message from the last statement operation
	 * 
	 * @return  string  Error message
	 */
	public function errorInfo(): string;

	/**
	 * Execute the prepared statement
	 * 
	 * @param   array|null  $parameters  Optional array of parameters to bind
	 * @return  bool        True on success
	 */
	public function execute(?array $parameters = null): bool;

	/**
	 * Fetch a row from the result set
	 * 
	 * @param   int|null  $fetchStyle    Fetch mode (see FetchMode constants)
	 * @param   int       $cursorOffset  Row offset for cursor-oriented fetch
	 * @return  mixed     Fetched row or false
	 */
	public function fetch(?int $fetchStyle = null, int $cursorOffset = 0);

	/**
	 * Get the number of rows affected or returned
	 * 
	 * @return  int  Row count
	 */
	public function rowCount(): int;

	/**
	 * Set the default fetch mode for this statement
	 * 
	 * @param   int    $fetchMode  Fetch mode constant
	 * @param   mixed  ...$args    Additional arguments (class name, constructor args, etc.)
	 */
	public function setFetchMode(int $fetchMode, ...$args): void;
}
