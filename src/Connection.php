<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

use JDZ\Database\ConnectionInterface;
use JDZ\Database\Exception\DatabaseException;

/**
 * Connection
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Connection implements ConnectionInterface
{
	public string $host = 'localhost';
	public int $port = 3306;
	public string $socket = '';
	public string $charset = 'utf8';
	public string $dbname;
	public string $user;
	public string $pass;

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
