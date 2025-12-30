<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\ConnectionInterface;
use JDZ\Database\Exception\DatabaseException;

abstract class Connection implements ConnectionInterface
{
	public string $host = 'localhost';
	public string $dbname;
	public string $user;
	public string $pass;
	public int $port = 0;
	public string $socket = '';
	public string $charset = 'utf8';

	protected mixed $connection = null;

	public function __construct(string $host, string $dbname, string $user, string $pass)
	{
		$this->checkIfDriverAvailable();

		$this->host = $host;
		$this->dbname = $dbname;
		$this->user = $user;
		$this->pass = $pass;

		if ('' === $this->host) {
			throw new DatabaseException('Missing host');
		}

		if ('' === $this->dbname) {
			throw new DatabaseException('Missing dbname');
		}

		if ('' === $this->user) {
			throw new DatabaseException('Missing user');
		}

		if ('' === $this->pass) {
			throw new DatabaseException('Missing pass');
		}
	}
}
