<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Database Table Queries
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
trait DatabaseTableQueriesTrait
{
  public function getTableCreate(array $tables): array
  {
    $results = [];

    foreach ($tables as $table) {
      $this->setQuery('SHOW CREATE table ' . $this->qn($this->escape($table)));

      if ($row = $this->loadRow()) {
        $results[$table] = $row[1];
      }
    }

    return $results;
  }

  public function getTableColumns(string $table, bool $full = false): array
  {
    static $results;

    if (!isset($results)) {
      $results = [];
    }

    $table = str_replace('#__', $this->tablePrefix, $table);

    if (!isset($results[$table])) {
      $results[$table] = [];

      $this->setQuery('SHOW ' . ($full ? 'FULL ' : '') . 'COLUMNS FROM ' . $table);

      if ($fields = $this->loadObjectList()) {
        foreach ($fields as $field) {
          $field->Type = preg_replace("/^([^\(]+).*$/", "$1", $field->Type);

          $results[$table][$field->Field] = $field;
        }
      }
    }

    return $results[$table];
  }

  public function getTableKeys(string $table): array
  {
    static $results;

    if (!isset($results)) {
      $results = [];
    }

    $table = str_replace('#__', $this->tablePrefix, $table);

    if (!isset($results[$table])) {
      $results[$table] = [];

      $this->setQuery('SHOW KEYS FROM ' . $table);
      if ($keys = $this->loadObjectList()) {
        $results[$table] = $keys;
      }
    }

    return $results[$table];
  }

  public function getTableList(): array
  {
    static $tables;

    if (!isset($tables)) {
      $this->setQuery('SHOW TABLES');

      if ($results = $this->loadColumn()) {
        $tables = $results;
      }
    }

    return $tables;
  }

  public function tableExists(string $table): bool
  {
    $table = str_replace('#__', $this->tablePrefix, $table);

    $tables = $this->getTableList();

    return in_array($table, $tables);
  }

  public function dropTable(string $table)
  {
    if (false === $this->tableExists($table)) {
      return;
    }

    $table = str_replace('#__', $this->tablePrefix, $table);

    $this->setQuery('DROP TABLE ' . $table);
    $this->execute();
  }

  public function renameTable(string $oldTable, string $newTable)
  {
    if (false === $this->tableExists($oldTable)) {
      return;
    }

    if (true === $this->tableExists($newTable)) {
      return;
    }

    $oldTable = str_replace('#__', $this->tablePrefix, $oldTable);
    $newTable = str_replace('#__', $this->tablePrefix, $newTable);

    $this->setQuery('RENAME TABLE ' . $oldTable . ' TO ' . $newTable);
    $this->execute();
  }

  public function truncateTable(string $table)
  {
    if (false === $this->tableExists($table)) {
      return;
    }

    $table = str_replace('#__', $this->tablePrefix, $table);

    $this->setQuery('TRUNCATE TABLE ' . $table);
    $this->execute();
  }

  public function lockTable($table)
  {
    $table = str_replace('#__', $this->tablePrefix, $table);

    $this->setQuery('LOCK TABLES ' . $table . ' WRITE');
    $this->execute();
  }

  public function unlockTables()
  {
    $this->setQuery('UNLOCK TABLES');
    $this->execute();
  }
}
