<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

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
