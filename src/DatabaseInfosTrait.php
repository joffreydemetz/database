<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

trait DatabaseInfosTrait
{
  public function getCollation(): string|null
  {
    $collation = null;

    $this->setQuery('SELECT @@collation_database;');

    if ($result = $this->loadResult()) {
      $collation = $result;
    }

    return $collation;
  }
}
