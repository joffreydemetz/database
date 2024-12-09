<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

use \JDZ\Database\Exception\DatabaseException;
use \JDZ\Database\StatementInterface;

/**
 * Database Interface
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface DatabaseInterface
{
  /**
   * @throws  DatabaseException
   */
  public function connect();

  public function connected(): bool;

  public function insertid(): int;

  public function escape(string $text, bool $extra = false): string;

  public function prepareStatement(string $query): StatementInterface;
}
