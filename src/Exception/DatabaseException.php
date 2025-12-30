<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Exception;

class DatabaseException extends \RuntimeException
{
	protected string $sql = '';

	public function setSql(string $sql)
	{
		$this->sql = $sql;
		return $this;
	}

	public function getSql(): string
	{
		return $this->sql;
	}
}
