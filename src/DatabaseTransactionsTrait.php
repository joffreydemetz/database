<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;

/**
 * Database Table Queries
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
trait DatabaseTransactionsTrait
{
  public function transaction(array $queries = [])
  {
    if (empty($queries)) {
      throw new DatabaseException('No queries for transaction');
    }

    $sql = 'START TRANSACTION;';
    foreach ($queries as $query) {
      if (\is_string($query)) {
        // Allows taking advantage of bound variables in a direct query:
        $query = $this->getQuery(true)
          ->setQuery($query);
      }
      $sql .= (string)$query;
    }
    $sql .= 'COMMIT;';

    $this->setQuery($sql);
    $this->execute();

    return $this;
  }

  public function transactionStart()
  {
    $this->setQuery('START TRANSACTION');
    $this->execute();
  }

  public function transactionCommit()
  {
    $this->setQuery('COMMIT');
    $this->execute();
  }

  public function transactionRollback()
  {
    $this->setQuery('ROLLBACK');
    $this->execute();
  }
}
