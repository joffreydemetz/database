<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database\Query;

use JDZ\Database\Parameter;

/**
 * Base abstract Query class
 * 
 * Provides common functionality for all query types:
 * - Parameter binding
 * - Limit/offset handling
 * - Database reference
 */
abstract class Query implements QueryInterface
{
  protected array $bounded = [];

  /**
   * Convert the query to a SQL string
   * 
   * @return string The SQL query string
   */
  abstract public function toString(): string;

  /**
   * Bind a parameter to the query
   */
  public function bindParam(string|int $key, mixed $value, string|int $dataType = 'string', int $maxLength = 0, array $driverOptions = []): static
  {
    $bind = new Parameter($key, $value);
    $bind->setDataType($dataType);
    $bind->maxLength = $maxLength;
    $bind->driverOptions = $driverOptions;

    $this->bounded[$key] = $bind;

    return $this;
  }

  /**
   * Bind a value to the query
   */
  public function bindValue(string|int $key, mixed $value, string|int $dataType = 'string'): static
  {
    $bind = new Parameter($key, $value);
    $bind->setDataType($dataType);

    $this->bounded[$key] = $bind;

    return $this;
  }

  /**
   * Unbind one or more parameters
   */
  public function unbind(string|int|array $key): static
  {
    if (is_array($key)) {
      foreach ($key as $k) {
        unset($this->bounded[$k]);
      }
    } else {
      unset($this->bounded[$key]);
    }

    return $this;
  }

  /**
   * Bind an array of values
   */
  public function bindArray(array $data, string $dataType = 'string'): static
  {
    foreach ($data as $key => $value) {
      $this->bindValue($key, $value, $dataType);
    }

    return $this;
  }

  /**
   * Get all bound parameters
   */
  public function getBounded(): array
  {
    return $this->bounded;
  }
}
