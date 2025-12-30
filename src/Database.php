<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\Exception\ExecutionFailureException;
use JDZ\Database\DatabaseInterface;
use JDZ\Database\ConnectionInterface;
use JDZ\Database\StatementInterface;
use JDZ\Database\FetchMode;
use JDZ\Database\Query\QueryInterface;
use JDZ\Database\Query\Query;
use JDZ\Database\Query\StringQuery;
use JDZ\Database\Query\SelectQuery;

abstract class Database implements DatabaseInterface
{
  public string $tablePrefix;
  public string $nullDate;
  public string $nameQuote;
  public string $dateFormat = 'Y-m-d H:i:s';
  public string $lastQueryStr = '';

  protected ?ConnectionInterface $connection;
  protected ?StatementInterface $statement = null;

  protected array $options;
  protected mixed $sqlConn;

  protected ?Query $query = null;
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

    $bounded = $this->query->getBounded();

    $this->executed = false;

    foreach ($bounded as $obj) {
      $this->statement->bindParam($obj->param, $obj->value, $obj->dataType);
    }

    try {
      $sql = $this->replacePrefix((string)$this->query);
      $this->lastQueryStr = $sql;

      $this->executed = $this->statement->execute();
    } catch (ExecutionFailureException $e) {
      // Attempt reconnection if connection was lost
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

  public function getQuery(): ?QueryInterface
  {
    return $this->query;
  }

  public function setQuery(QueryInterface|string $query): QueryInterface
  {
    $this->connect();
    $this->freeResult();

    if (\is_string($query)) {
      $sQuery = new StringQuery();
      $sQuery->setQuery($query);
      $query = $sQuery;
    }

    $this->query = $query;

    $sql = $this->replacePrefix((string) $this->query);

    $this->statement = $this->prepareStatement($sql);

    return $this->query;
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
      return $this->statement->fetch(FetchMode::MIXED->value);
    }
    return null;
  }

  protected function fetchArray(): array|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch(FetchMode::NUMERIC->value);
    }
    return null;
  }

  protected function fetchAssoc(): array|null|false
  {
    if ($this->statement) {
      return $this->statement->fetch(FetchMode::ASSOCIATIVE->value);
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
    $this->execute();

    $result = null;
    if ($row = $this->fetchAssoc()) {
      $result = $row;
    }

    $this->freeResult();

    return $result;
  }

  public function loadRow(): array|null
  {
    $this->execute();

    $result = null;
    if ($row = $this->fetchArray()) {
      $result = $row;
    }

    $this->freeResult();

    return $result;
  }

  public function loadColumn(string|int $column = 0): array
  {
    $this->execute();

    $results = [];
    while ($row = $this->fetchRow()) {
      $results[] = $row[$column];
    }

    $this->freeResult();

    return $results;
  }

  public function loadResult()
  {
    $this->execute();

    $result = null;
    if ($rows = $this->loadColumn()) {
      $result = $rows[0];
    }

    $this->freeResult();

    return $result;
  }

  public function loadObject(string $class = \stdClass::class): object|null
  {
    if ($this->statement) {
      $fetchMode = $class === \stdClass::class ? FetchMode::STANDARD_OBJECT->value : FetchMode::CUSTOM_OBJECT->value;

      if ($fetchMode === FetchMode::STANDARD_OBJECT->value) {
        $this->statement->setFetchMode($fetchMode);
      } else {
        $this->statement->setFetchMode($fetchMode, $class);
      }
    }

    $this->execute();

    $result = null;
    if ($object = $this->fetchObject()) {
      $result = $object;
    }

    $this->freeResult();

    return $result;
  }

  public function loadAssocList(string $key = '', string $column = ''): array
  {
    $this->execute();

    $results = [];
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
    $this->execute();

    $results = [];
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
    if ($this->statement) {
      $fetchMode = $class === \stdClass::class ? FetchMode::STANDARD_OBJECT->value : FetchMode::CUSTOM_OBJECT->value;

      if ($fetchMode === FetchMode::STANDARD_OBJECT->value) {
        $this->statement->setFetchMode($fetchMode);
      } else {
        $this->statement->setFetchMode($fetchMode, $class);
      }
    }

    $this->execute();

    $results = [];
    while ($row = $this->fetchObject()) {
      if ($key) {
        $results[$row->$key] = $row;
      } else {
        $results[] = $row;
      }
    }

    $this->freeResult();

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

    $this->setQuery(' SELECT ' . $selectField . ' FROM ' . $tblName . ' WHERE ' . implode(' AND ', $where));

    $id = (int) $this->loadResult();

    return $id > 0;
  }

  /******************************* 
   * QUOTES
   *******************************/

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

  /******************************* 
   * SANITIZE
   *******************************/

  public function escape(string $text, bool $extra = false): string
  {
    // Note: Driver-specific implementations should override this
    // This base implementation provides basic escaping
    if (true === $extra) {
      $text = \addcslashes($text, '%_');
    }

    return $text;
  }

  public function getNullDate(bool $dateTime = true): string
  {
    return true === $dateTime ? $this->nullDate : substr($this->nullDate, 0, 10);
  }

  public function isNullDate(string $testDate): bool
  {
    return '' === $testDate || substr($this->nullDate, 0, 10) === substr($testDate, 0, 10);
  }

  public function valuesToString(array $values): string
  {
    $in = [];
    foreach ($values as $value) {
      $in[] = $this->quote($value);
    }
    return implode(', ', $in);
  }

  public function currentTimestamp(): string
  {
    return 'CURRENT_TIMESTAMP';
  }

  public function charLength(string $field): string
  {
    return 'CHAR_LENGTH(' . $field . ')';
  }

  public function concatenate(array $values, string $separator = ''): string
  {
    if ($separator) {
      return 'CONCAT_WS(' . $this->quote($separator) . ', ' . implode(', ', $values) . ')';
    }
    return 'CONCAT(' . implode(', ', $values) . ')';
  }
}
