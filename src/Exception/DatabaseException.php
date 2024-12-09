<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database\Exception;

/**
 * Database exception
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
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
