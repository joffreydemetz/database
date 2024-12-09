<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Database Infos
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
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
