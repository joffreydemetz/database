<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use \JDZ\Database\Exception\DatabaseException;
use \JDZ\Database\StatementInterface;

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
