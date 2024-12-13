<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Pdo;

use JDZ\Database\Database;
use JDZ\Database\Pdo\PdoStatement;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\PrepareStatementFailureException;
// use JDZ\Database\Exception\ExecutionFailureException;

/**
 * Database
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class PdoDatabase extends Database
{
	public string $nullDate = '1000-01-01 00:00:00';
	public string $nameQuote = '`';

	/**
	 * @var    \PDO|null
	 */
	protected mixed $sqlConn;

	public function __construct(array $options)
	{
		if (empty($options['driver'])) {
			$options['driver'] = 'mysqli';
		}

		if (empty($options['port'])) {
			$options['port'] = 3306;
		}

		parent::__construct($options);
	}

	public function connect()
	{
		if (!$this->sqlConn) {
			$this->connection = new PdoConnection($this->options['host'], $this->options['dbname'], $this->options['user'], $this->options['pass']);
			$this->connection->charset = $this->options['charset'];

			if ($this->options['driver']) {
				$this->connection->driver = $this->options['driver'];
			}

			if ($this->options['port']) {
				$this->connection->port = $this->options['port'];
			}

			if ($this->options['socket']) {
				$this->connection->socket = $this->options['socket'];
			}

			$this->sqlConn = $this->connection->connect($this->options['driverOptions']);
		}
	}

	public function connected(): bool
	{
		static $checkingConnected = false;

		if ($checkingConnected) {
			$checkingConnected = false;
			throw new \LogicException('Recursion trying to check if connected.');
		}

		$sql = $this->sql;
		$limit = $this->limit;
		$offset = $this->offset;
		$statement = $this->statement;

		try {
			$checkingConnected = true;
			$this->setQuery($this->getQuery());
			$status = (bool) $this->loadResult();
		} catch (\Exception $e) {
			$status = false;
		}

		$this->sql         = $sql;
		$this->limit       = $limit;
		$this->offset      = $offset;
		$this->statement   = $statement;

		$checkingConnected = false;

		return $status;
	}

	public function insertid(): int
	{
		$this->connect();
		return $this->sqlConn->lastInsertId();
	}

	public function escape(string $text, bool $extra = false): string
	{
		if (\is_int($text)) {
			return $text;
		}

		if (\is_float($text)) {
			return str_replace(',', '.', (string)$text);
		}

		$text = str_replace("'", "''", (string)$text);
		return addcslashes($text, "\000\n\r\\\032");
	}

	public function execute()
	{
		//$this->executed = false;
		$this->connect();

		$sql = $this->replacePrefix((string)$this->sql);
		$this->lastQueryStr = $sql;

		$bounded = $this->sql->getBounded();


		foreach ($bounded as $key => $obj) {
			$this->statement->bindParam($key, $obj->value, $obj->dataType, $obj->maxLength, $obj->driverOptions);
		}

		try {

			$this->statement->execute();
			//$this->executed = true;

			return;
		} catch (\PDOException $exception) {
			$errorNum = $this->statement->errorCode();
			$errorMsg = $this->statement->errorInfo();

			throw (new DatabaseException($errorMsg, $errorNum, $exception))
				->setSql($sql);
		}
	}

	public function prepareStatement(string $query): PdoStatement
	{
		try {
			return new PdoStatement($this->sqlConn->prepare($query, $this->options['driverOptions']));
		} catch (\PDOException $e) {
			throw new PrepareStatementFailureException($e->getMessage(), $e->getCode(), $e);
		}

		return new PdoStatement($this->sqlConn, $query);
	}
}
