<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;

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
