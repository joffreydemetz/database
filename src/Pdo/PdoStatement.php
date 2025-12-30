<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\ParamType;
use JDZ\Database\StatementInterface;
use JDZ\Database\Exception\ExecutionFailureException;

class PdoStatement implements StatementInterface
{
	protected \PDOStatement $pdoStatement;

	public function __construct(\PDOStatement $pdoStatement)
	{
		$this->pdoStatement = $pdoStatement;
	}

	public function bindParam(string|int $parameter, &$variable, string|int|ParamType $dataType = ParamType::STR, ?int $maxLength = null, ?array $driverOptions = null): bool
	{
		$type = $this->convertParameterType($dataType);
		$extraParameters = \array_slice(\func_get_args(), 3);

		if (count($extraParameters) !== 0) {
			$extraParameters[0] = $extraParameters[0] ?? 0;
		}

		return $this->pdoStatement->bindParam($parameter, $variable, $type, ...$extraParameters);
	}

	public function bindValue(string|int $parameter, mixed $value, string|int|ParamType $dataType = ParamType::STR): bool
	{
		$type = $this->convertParameterType($dataType);

		return $this->pdoStatement->bindValue($parameter, $value, $type);
	}

	public function closeCursor(): void
	{
		$this->pdoStatement->closeCursor();
	}

	public function errorCode(): int
	{
		return (int)$this->pdoStatement->errorCode();
	}

	public function errorInfo(): string
	{
		$error = $this->pdoStatement->errorInfo();

		list($sqlState, $errorCode, $errorMessage) = $error;

		if ($errorMessage) {
			return (string)$errorMessage;
		}

		return (string)$sqlState;
	}

	public function queryString(): string
	{
		return $this->pdoStatement->queryString;
	}

	public function debugDumpParams()
	{
		$this->pdoStatement->debugDumpParams();
	}

	public function execute(?array $parameters = null): bool
	{
		try {
			return $this->pdoStatement->execute($parameters);
		} catch (\PDOException $e) {
			throw new ExecutionFailureException(
				$e->getMessage(),
				(int)$e->getCode(),
				$e
			);
		}
	}

	public function fetch(?int $fetchStyle = null, int $cursorOffset = 0)
	{
		if (null === $fetchStyle) {
			return $this->pdoStatement->fetch();
		}

		return $this->pdoStatement->fetch($fetchStyle, $cursorOffset);
	}

	public function rowCount(): int
	{
		return $this->pdoStatement->rowCount();
	}

	public function setFetchMode(int $fetchMode, ...$args): void
	{
		$this->pdoStatement->setFetchMode($fetchMode, ...$args);
	}

	private function convertParameterType(string|int|ParamType $type): int
	{
		if ($type instanceof ParamType) {
			return $type->value;
		}

		if (is_int($type)) {
			return $type;
		}

		// Convert string to ParamType enum
		return ParamType::fromString($type)->value;
	}
}
