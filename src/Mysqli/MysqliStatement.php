<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Mysqli;

use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\Exception\PrepareStatementFailureException;
use JDZ\Database\FetchMode;
use JDZ\Database\ParamType;
use JDZ\Database\StatementInterface;

class MysqliStatement implements StatementInterface
{
	/**
	 * Values which have been bound to the statement.
	 */
	protected array $bindedValues = [];

	/**
	 * Mapping between named parameters and position in query.
	 */
	protected array $parameterKeyMapping = [];

	protected array $parameterTypeMapping = [
		'bool'    => 'i',
		'boolean' => 'i',
		'int'     => 'i',
		'integer' => 'i',
		'lob'     => 's',
		'null'    => 's',
		'string'  => 's',
		// PDO constants
		'1'       => 'i',  // PDO::PARAM_INT
		'2'       => 's',  // PDO::PARAM_STR
		'3'       => 'b',  // PDO::PARAM_LOB
		'5'       => 's',  // PDO::PARAM_NULL
	];

	/**
	 * Column names from the executed statement.
	 *
	 * @var    array|bool|null
	 */
	protected $columnNames;

	/**
	 * The database connection resource.
	 *
	 * @var    \mysqli
	 */
	protected mixed $connection;

	/**
	 * The default fetch mode for the statement.
	 *
	 * @var    int
	 */
	protected $defaultFetchStyle = FetchMode::MIXED;

	/**
	 * @var    string
	 */
	protected $query;

	/**
	 * Internal tracking flag to set whether there is a result set available for processing
	 *
	 * @var    bool
	 */
	private $result = false;

	/**
	 * Values which have been bound to the rows of each result set.
	 *
	 * @var    array
	 */
	protected $rowBindedValues;

	/**
	 * The prepared statement.
	 *
	 * @var    \mysqli_stmt
	 */
	protected $statement;

	/**
	 * Bound parameter types.
	 *
	 * @var    array
	 */
	protected $typesKeyMapping;

	/**
	 * @throws  PrepareStatementFailureException
	 */
	public function __construct(\mysqli $connection, string $query)
	{
		$this->connection = $connection;
		$this->query = $query;

		$query = $this->prepareParameterKeyMapping($query);

		$this->statement = $connection->prepare($query);

		if (!$this->statement) {
			throw new PrepareStatementFailureException($this->connection->error, $this->connection->errno);
		}
	}

	public function prepareParameterKeyMapping(string $sql): string
	{
		$escaped   	= false;
		$startPos  	= 0;
		$quoteChar 	= '';
		$literal    = '';
		$mapping    = [];
		$replace    = [];
		$matches    = [];
		$pattern    = '/([:][a-zA-Z0-9_]+)/';

		if (!preg_match($pattern, $sql, $matches)) {
			return $sql;
		}

		$sql = trim($sql);
		$n = \strlen($sql);

		while ($startPos < $n) {
			if (!preg_match($pattern, $sql, $matches, 0, $startPos)) {
				break;
			}

			$j = strpos($sql, "'", $startPos);
			$k = strpos($sql, '"', $startPos);

			if (($k !== false) && (($k < $j) || ($j === false))) {
				$quoteChar = '"';
				$j = $k;
			} else {
				$quoteChar = "'";
			}

			if ($j === false) {
				$j = $n;
			}

			// Search for named prepared parameters and replace it with ? and save its position
			$substring = substr($sql, $startPos, $j - $startPos);

			if (preg_match_all($pattern, $substring, $matches, PREG_PATTERN_ORDER + PREG_OFFSET_CAPTURE)) {
				foreach ($matches[0] as $i => $match) {
					if ($i === 0) {
						$literal .= substr($substring, 0, $match[1]);
					}

					$mapping[$match[0]]     = \count($mapping);
					$endOfPlaceholder       = $match[1] + strlen($match[0]);
					$beginOfNextPlaceholder = $matches[0][$i + 1][1] ?? strlen($substring);
					$beginOfNextPlaceholder -= $endOfPlaceholder;
					$literal                .= '?' . substr($substring, $endOfPlaceholder, $beginOfNextPlaceholder);
				}
			} else {
				$literal .= $substring;
			}

			$startPos = $j;
			$j++;

			if ($j >= $n) {
				break;
			}

			// Quote comes first, find end of quote
			while (true) {
				$k = strpos($sql, $quoteChar, $j);
				$escaped = false;

				if ($k === false) {
					break;
				}

				$l = $k - 1;

				while ($l >= 0 && $sql[$l] === '\\') {
					$l--;
					$escaped = !$escaped;
				}

				if ($escaped) {
					$j = $k + 1;
					continue;
				}

				break;
			}

			if ($k === false) {
				// Error in the query - no end quote; ignore it
				break;
			}

			$literal .= substr($sql, $startPos, $k - $startPos + 1);
			$startPos = $k + 1;
		}

		if ($startPos < $n) {
			$literal .= substr($sql, $startPos, $n - $startPos);
		}

		$this->parameterKeyMapping = $mapping;

		return $literal;
	}

	public function bindParam(string|int $parameter, &$variable, string|int|ParamType $dataType = ParamType::STR, ?int $length = null, array $driverOptions = []): bool
	{
		// Normalize parameter key - check if we need to add ':' prefix for named parameters
		$key = $parameter;
		if (is_string($parameter) && !empty($this->parameterKeyMapping)) {
			// If parameter doesn't start with ':' but exists with ':' prefix in mapping, add it
			if ($parameter[0] !== ':' && isset($this->parameterKeyMapping[':' . $parameter])) {
				$key = ':' . $parameter;
			}
		}
		
		$this->bindedValues[$key] = &$variable;
		$this->typesKeyMapping[$key] = $this->convertParameterType($dataType);
		
		return true;
	}

	public function closeCursor(): void
	{
		$this->statement->free_result();
		$this->result = false;
	}

	public function errorCode(): int
	{
		return (int)$this->statement->errno;
	}

	public function errorInfo(): string
	{
		return $this->statement->error;
	}

	public function execute(?array $parameters = null): bool
	{
		if (!empty($this->bindedValues)) {
			$params = [];
			$types  = [];

			if (!empty($this->parameterKeyMapping)) {
				foreach ($this->bindedValues as $key => &$value) {
					$params[$this->parameterKeyMapping[$key]] = &$value;
					$types[$this->parameterKeyMapping[$key]]  = $this->typesKeyMapping[$key];
				}
			} else {
				foreach ($this->bindedValues as $key => &$value) {
					$params[] = &$value;
					$types[$key] = $this->typesKeyMapping[$key];
				}
			}

			ksort($params);
			ksort($types);

			array_unshift($params, implode('', $types));

			if (!\call_user_func_array([$this->statement, 'bind_param'], $params)) {
				throw new PrepareStatementFailureException($this->statement->error, $this->statement->errno);
			}
		} elseif (null !== $parameters) {
			if (!$this->bindValues($parameters)) {
				throw new PrepareStatementFailureException($this->statement->error, $this->statement->errno);
			}
		}

		try {
			if (!$this->statement->execute()) {
				$e = new ExecutionFailureException($this->statement->error, $this->statement->errno);
				$e->setSql((string)$this->query);
				throw $e;
			}
		} catch (\Throwable $e) {
			throw (new ExecutionFailureException($e->getMessage(), $e->getCode(), $e))
				->setSql((string)$this->query);
		}

		if (null === $this->columnNames) {
			$meta = $this->statement->result_metadata();

			if ($meta !== false) {
				$columnNames = [];

				foreach ($meta->fetch_fields() as $col) {
					$columnNames[] = $col->name;
				}

				$meta->free();

				$this->columnNames = $columnNames;
			} else {
				$this->columnNames = false;
			}
		}

		if ($this->columnNames !== false) {
			$this->statement->store_result();

			$this->rowBindedValues = array_fill(0, \count($this->columnNames), null);
			$refs = [];

			foreach ($this->rowBindedValues as $key => &$value) {
				$refs[$key] = &$value;
			}

			if (!\call_user_func_array([$this->statement, 'bind_result'], $refs)) {
				throw new \RuntimeException($this->statement->error, $this->statement->errno);
			}
		}

		$this->result = true;

		return true;
	}

	public function fetch(?int $fetchStyle = null, int $cursorOffset = 0)
	{
		if (!$this->result) {
			return false;
		}

		$fetchStyle = $fetchStyle ?: $this->defaultFetchStyle;

		if ($fetchStyle === FetchMode::COLUMN->value || $fetchStyle === FetchMode::COLUMN) {
			return $this->fetchColumn();
		}

		$values = $this->fetchData();

		if ($values === null) {
			return false;
		}

		if ($values === false) {
			throw new \RuntimeException($this->statement->error, $this->statement->errno);
		}

		// Handle both enum cases and int values
		$mode = is_int($fetchStyle) ? FetchMode::from($fetchStyle) : $fetchStyle;

		switch ($mode) {
			case FetchMode::NUMERIC:
				return $values;

			case FetchMode::ASSOCIATIVE:
				return array_combine($this->columnNames, $values);

			case FetchMode::MIXED:
				$ret = array_combine($this->columnNames, $values);
				$ret += $values;

				return $ret;

			case FetchMode::STANDARD_OBJECT:
				return (object) array_combine($this->columnNames, $values);

			default:
				throw new \InvalidArgumentException("Unknown fetch type '{$fetchStyle}'");
		}
	}

	public function fetchColumn(int $columnIndex = 0)
	{
		$row = $this->fetch(FetchMode::NUMERIC->value);

		if (false === $row) {
			return false;
		}

		return $row[$columnIndex] ?? null;
	}

	public function rowCount(): int
	{
		if (false === $this->columnNames) {
			return $this->statement->affected_rows;
		}

		return $this->statement->num_rows;
	}

	public function setFetchMode(int $fetchMode, ...$args): void
	{
		$this->defaultFetchStyle = $fetchMode;
	}

	private function bindValues(array $values): bool
	{
		$params = [];
		$types  = str_repeat('s', \count($values));

		if (!empty($this->parameterKeyMapping)) {
			foreach ($values as $key => &$value) {
				$params[$this->parameterKeyMapping[$key]] = &$value;
			}

			ksort($params);
		} else {
			foreach ($values as $key => &$value) {
				$params[] = &$value;
			}
		}

		array_unshift($params, $types);

		return \call_user_func_array([$this->statement, 'bind_param'], $params);
	}

	private function convertParameterType(string|int|ParamType $type): string
	{
		if ($type instanceof ParamType) {
			$typeValue = $type->value;
		} elseif (is_string($type)) {
			$typeValue = ParamType::fromString($type)->value;
		} else {
			$typeValue = $type;
		}

		// Convert PDO integer constant to MySQLi type string
		if (!isset($this->parameterTypeMapping[$typeValue])) {
			throw new \InvalidArgumentException(sprintf('Unsupported parameter type `%s`', $type));
		}

		return $this->parameterTypeMapping[$typeValue];
	}

	private function fetchData(): array|bool|null
	{
		$return = $this->statement->fetch();

		if (true === $return) {
			$values = [];
			foreach ($this->rowBindedValues as $v) {
				$values[] = $v;
			}
			return $values;
		}

		return $return;
	}
}
