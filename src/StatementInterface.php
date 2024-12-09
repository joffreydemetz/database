<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Statement
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface StatementInterface
{
	public function bindParam(string|int $parameter, &$variable, string $dataType = 'string', ?int $length = null, array $driverOptions = []): bool;

	public function closeCursor(): void;

	public function errorCode(): int;

	public function errorInfo(): string;

	public function execute(?array $parameters = null): bool;

	public function fetch(?int $fetchStyle = null, int $cursorOffset = 0);

	public function rowCount(): int;

	public function setFetchMode(int $fetchMode, ...$args): void;
}
