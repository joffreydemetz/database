<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\Exception\QueryException;
use JDZ\Database\DatabaseInterface;
use JDZ\Database\ConnectionInterface;
use JDZ\Database\Query;
use JDZ\Database\FetchMode;

/**
 * Database
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Database implements DatabaseInterface
{
  public string $tablePrefix;
  public string $nullDate;
  public string $nameQuote;
  public string $dateFormat = 'Y-m-d H:i:s';
  public string $lastQueryStr = '';

  protected ?ConnectionInterface $connection;
  protected ?StatementInterface $statement = null;
  protected mixed $sql;
  protected array $options;
  protected mixed $sqlConn;
  protected int $limit = 0;
  protected int $offset = 0;

  protected bool $executed = false;
  protected int $count = 0;
  protected int $errorNum = 0;
  protected string $errorMsg;

  public function __construct(array $options)
  {
    $this->tablePrefix = $options['tblprefix'];
    unset($options['tblprefix']);

    $options['sqlModes'] = $options['sqlModes'] ?? [];
    $options['driverOptions'] = $options['driverOptions'] ?? [];

    $this->options = $options;

    $this->sqlConn = null;
    $this->connection = null;
  }

  public function __destruct()
  {
    $this->disconnect();
  }

  public function disconnect()
  {
    $this->sqlConn = null;
    $this->connection = null;
  }

  public function execute()
  {
    $this->connect();

    $this->count++;

    $bounded = $this->sql->getBounded();

    $this->executed = false;

    foreach ($bounded as $obj) {
      $this->statement->bindParam($obj->key, $obj->value, $obj->dataType);
    }

    try {

      $sql = $this->replacePrefix((string)$this->sql);
      $this->executed = $this->statement->execute();
    } catch (ExecutionFailureException $e) {
      /**
       * @TODO
       *  useful or not ??
       */
      if (false === $this->connected()) {
        try {
          $this->connection = null;
          $this->connect();
        } catch (DatabaseException $e2) {
          throw $e;
        }

        $this->execute();
        return;
      }

      throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
    }
  }

  public function query()
  {
    return $this->execute();
  }

  /**
   * @throws  \Callisto\Database\Exception\QueryException
   */
  public function getQuery(bool $new = false): Query
  {
    if (true === $new) {
      $query = new Query($this);
      return $query;
    }

    return $this->sql;
  }

  public function setQuery(Query|string $query, int $offset = 0, int $limit = 0)
  {
    $this->connect();
    $this->freeResult();

    if (\is_string($query)) {
      // Allows taking advantage of bound variables in a direct query:
      $query = $this->getQuery(true)
        ->setQuery($query);
    }

    if (0 === $limit && $query->limit > 0) {
      $limit = $query->limit;
    }

    if (0 === $offset && $query->offset > 0) {
      $offset = $query->offset;
    }

    $query->setLimit($limit, $offset);

    $sql = $this->replacePrefix((string) $query);

    $this->statement = $this->prepareStatement($sql);

    $this->sql = $query;
    $this->limit = max(0, $limit);
    $this->offset = max(0, $offset);
    return $this;
  }

  public function getNullDate(bool $dateTime = true): string
  {
    return true === $dateTime ? $this->nullDate : substr($this->nullDate, 0, 10);
  }

  public function isNullDate(string $testDate): bool
  {
    return '' === $testDate || substr($this->nullDate, 0, 10) === substr($testDate, 0, 10);
  }

  protected function replacePrefix(string $sql): string
  {
    $escaped   = false;
    $startPos  = 0;
    $quoteChar = '';
    $literal   = '';

    $sql = trim($sql);
    $n = strlen($sql);

    while ($startPos < $n) {
      $ip = strpos($sql, '#__', $startPos);
      if ($ip === false) {
        break;
      }

      $j = strpos($sql, "'", $startPos);
      $k = strpos($sql, '"', $startPos);
      if (($k !== false) && (($k < $j) || ($j === false))) {
        $quoteChar = '"';
        $j = $k;
      } else {
        $quoteChar = "'";
      }

      if ($j === false) {
        $j = $n;
      }

      $literal .= str_replace('#__', $this->tablePrefix, substr($sql, $startPos, $j - $startPos));
      $startPos = $j;

      $j = $startPos + 1;

      if ($j >= $n) {
        break;
      }

      // quote comes first, find end of quote
      while (true) {
        $k = strpos($sql, $quoteChar, $j);
        $escaped = false;
        if ($k === false) {
          break;
        }
        $l = $k - 1;
        while ($l >= 0 && $sql[$l] == '\\') {
          $l--;
          $escaped = !$escaped;
        }
        if ($escaped) {
          $j = $k + 1;
          continue;
        }
        break;
      }

      if ($k === false) {
        // error in the query - no end quote; ignore it
        break;
      }

      $literal .= substr($sql, $startPos, $k - $startPos + 1);
      $startPos = $k + 1;
    }

    if ($startPos < $n) {
      $literal .= substr($sql, $startPos, $n - $startPos);
    }

    return $literal;
  }

  /******************************* 
   * FETCH
   *******************************/

  protected function fetchRow(): array|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch(FetchMode::MIXED);
    }
    return null;
  }

  protected function fetchArray(): array|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch(FetchMode::NUMERIC);
    }
    return null;
  }

  protected function fetchAssoc(): array|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch(FetchMode::ASSOCIATIVE);
    }
    return null;
  }

  protected function fetchObject(): object|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch();
    }
    return null;
  }

  /******************************* 
   * LOAD QUERY RESULTS
   *******************************/

  public function loadAssoc(): array|null
  {
    $this->connect();

    $result = null;

    $this->execute();

    $row = $this->fetchAssoc();

    if ($row) {
      $result = $row;
    }

    $this->freeResult();

    return $result;
  }

  public function loadRow(): array|null
  {
    $this->connect();

    $result = null;

    $this->execute();

    $row = $this->fetchArray();

    if ($row) {
      $result = $row;
    }

    $this->freeResult();

    return $result;
  }

  public function loadColumn(string|int $column = 0): array
  {
    $this->connect();

    $results = [];

    $this->execute();

    while ($row = $this->fetchRow()) {
      $results[] = $row[$column];
    }

    $this->freeResult();

    return $results;
  }

  public function loadResult()
  {
    $this->connect();

    $result = null;

    $this->execute();

    if ($rows = $this->loadColumn()) {
      $result = $rows[0];
    }

    $this->freeResult();

    return $result;
  }

  public function loadObject(string $class = \stdClass::class): object|null
  {
    $this->connect();

    $result = null;

    if ($this->statement) {
      $fetchMode = $class === \stdClass::class ? FetchMode::STANDARD_OBJECT : FetchMode::CUSTOM_OBJECT;

      if ($fetchMode === FetchMode::STANDARD_OBJECT) {
        $this->statement->setFetchMode($fetchMode);
      } else {
        $this->statement->setFetchMode($fetchMode, $class);
      }
    }

    $this->execute();

    $object = $this->fetchObject();

    if ($object) {
      $result = $object;
    }

    $this->freeResult();

    return $result;
  }

  public function loadAssocList(string $key = '', string $column = ''): array
  {
    $this->connect();

    $results = [];

    $this->execute();

    while ($row = $this->fetchAssoc()) {
      $value = $column ? ($row[$column] ?? $row) : $row;

      if ($key) {
        $results[$row[$key]] = $value;
      } else {
        $results[] = $value;
      }
    }

    $this->freeResult();

    return $results;
  }

  public function loadRowList(string $key = ''): array
  {
    $this->connect();

    $results = [];

    $this->execute();

    while ($row = $this->fetchArray()) {
      if ($key) {
        $results[$row[$key]] = $row;
      } else {
        $results[] = $row;
      }
    }

    $this->freeResult();

    return $results;
  }

  public function loadObjectList(string $key = '', string $class = \stdClass::class): array
  {
    $this->connect();

    $results = [];

    if ($this->statement) {
      $fetchMode = $class === \stdClass::class ? FetchMode::STANDARD_OBJECT : FetchMode::CUSTOM_OBJECT;

      if ($fetchMode === FetchMode::STANDARD_OBJECT) {
        $this->statement->setFetchMode($fetchMode);
      } else {
        $this->statement->setFetchMode($fetchMode, $class);
      }
    }

    $this->execute();

    while ($row = $this->fetchObject()) {
      if ($key) {
        $results[$row->$key] = $row;
      } else {
        $results[] = $row;
      }
    }

    $this->freeResult();
    // debug((string)$this->sql);
    // debug($results);
    return $results;
  }

  public function getNumRows(): int
  {
    $this->connect();

    if ($this->statement) {
      return $this->statement->rowCount();
    }

    return 0;
  }

  public function getAffectedRows(): int
  {
    $this->connect();

    if ($this->statement) {
      return $this->statement->rowCount();
    }

    return 0;
  }

  protected function freeResult()
  {
    $this->executed = false;

    if ($this->statement) {
      $this->statement->closeCursor();
    }
  }


  /******************************* 
   * RECORDS QUERY SHORTCUTS
   *******************************/

  public function recordExists(string $tblName, array $properties, string $selectField = 'id'): bool
  {
    if (empty($properties)) {
      return false;
    }

    $where = [];
    foreach ($properties as $key => $value) {
      $where[] = $this->quoteName($key) . '=' . $this->quote($value);
    }

    $query = $this->getQuery(true)
      ->select($selectField)
      ->from($tblName)
      ->where($where);

    $this->setQuery($query);

    $id = (int) $this->loadResult();

    return $id > 0;
  }


  /******************************* 
   * SANITIZE
   *******************************/

  public function escape(string $text, bool $extra = false): string
  {
    if (true === $extra) {
      $text = \addcslashes($text, '%_');
    }

    return $text;
  }

  public function quote(string $text, bool $escape = true): string
  {
    if (is_int($text) || ctype_digit($text)) {
      if (!preg_match("/^[0]+/", $text)) {
        return $text;
      }
    }

    if (true === $escape) {
      $text = $this->escape($text);
    }

    return '\'' . $text . '\'';
  }

  public function valuesToString(array $values): string
  {
    $in = [];
    foreach ($values as $value) {
      $in[] = $this->quote($value);
    }
    return implode(', ', $in);
  }

  public function quoteName(string $name, string|null $as = null): string
  {
    $quotedName = $this->quoteNameStr(explode('.', $name));
    $quotedAs = $as ? ' AS ' . $this->quoteNameStr([$as]) : '';

    return $quotedName . $quotedAs;
  }

  protected function quoteNameStr(array $strArr): string
  {
    $parts = [];
    foreach ($strArr as $part) {
      if (is_null($part)) {
        continue;
      }

      $parts[] = $this->nameQuote . $part . $this->nameQuote;
    }

    return implode('.', $parts);
  }
}
