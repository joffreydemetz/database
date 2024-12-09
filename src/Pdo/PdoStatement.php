<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\FetchMode;
use JDZ\Database\StatementInterface;

/**
 * PDO Database Statement.
 */
class PdoStatement implements StatementInterface
{
	/**
	 * @var    array
	 */
	private const FETCH_MODE_MAP = [
		FetchMode::ASSOCIATIVE     => \PDO::FETCH_ASSOC,
		FetchMode::NUMERIC         => \PDO::FETCH_NUM,
		FetchMode::MIXED           => \PDO::FETCH_BOTH,
		FetchMode::STANDARD_OBJECT => \PDO::FETCH_OBJ,
		FetchMode::COLUMN          => \PDO::FETCH_COLUMN,
		FetchMode::CUSTOM_OBJECT   => \PDO::FETCH_CLASS,
	];

	/**
	 * @var    array
	 */
	private const PARAMETER_TYPE_MAP = [
		'bool'    => \PDO::PARAM_BOOL,
		'boolean' => \PDO::PARAM_BOOL,
		'int'     => \PDO::PARAM_INT,
		'lob'     => \PDO::PARAM_LOB,
		'null'    => \PDO::PARAM_NULL,
		'string'  => \PDO::PARAM_STR,
	];

	protected \PDOStatement $pdoStatement;

	public function __construct(\PDOStatement $pdoStatement)
	{
		$this->pdoStatement = $pdoStatement;
	}

	public function bindParam(string|int $parameter, &$variable, string $dataType = 'string', ?int $maxLength = null, ?array $driverOptions = null): bool
	{
		$type = $this->convertParameterType($dataType);
		$extraParameters = \array_slice(\func_get_args(), 3);

		if (count($extraParameters) !== 0) {
			$extraParameters[0] = $extraParameters[0] ?? 0;
		}

		$this->pdoStatement->bindParam($parameter, $variable, $type, ...$extraParameters);

		return true;
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
		return $this->pdoStatement->execute($parameters);
	}

	public function fetch(?int $fetchStyle = null, int $cursorOffset = 0)
	{
		if (null === $fetchStyle) {
			return $this->pdoStatement->fetch();
		}

		return $this->pdoStatement->fetch($this->convertFetchMode($fetchStyle), $cursorOffset);
	}

	public function rowCount(): int
	{
		return $this->pdoStatement->rowCount();
	}

	public function setFetchMode(int $fetchMode, ...$args): void
	{
		$this->pdoStatement->setFetchMode($this->convertFetchMode($fetchMode), ...$args);
	}

	private function convertFetchMode(int $mode): int
	{
		if (!isset(self::FETCH_MODE_MAP[$mode])) {
			throw new \InvalidArgumentException(sprintf('Unsupported fetch mode `%s`', $mode));
		}

		return self::FETCH_MODE_MAP[$mode];
	}

	private function convertParameterType(string|int $type): int
	{
		if (($k = array_search($type, self::PARAMETER_TYPE_MAP))) {
			return self::PARAMETER_TYPE_MAP[$k];
		}

		if (!isset(self::PARAMETER_TYPE_MAP[$type])) {
			throw new \InvalidArgumentException(sprintf('Unsupported parameter type `%s`', $type));
		}
		return self::PARAMETER_TYPE_MAP[$type];
	}
}
