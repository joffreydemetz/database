<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

class Element
{
  protected string $name;
  protected array $elements;
  protected string $glue;

  public function __construct(string $name, array|string $elements = [], string $glue = ',')
  {
    $this->name = $name;
    $this->elements = [];
    $this->glue = $glue;

    if ($elements) {
      $this->append($elements);
    }
  }

  public function __toString(): string
  {
    if (substr($this->name, -2) == '()') {
      return PHP_EOL . substr($this->name, 0, -2) . '(' . implode($this->glue, $this->elements) . ')';
    }

    return PHP_EOL . $this->name . ' ' . implode($this->glue, $this->elements);
  }

  public function __clone()
  {
    foreach ($this as $k => $v) {
      if (is_object($v) || is_array($v)) {
        $this->{$k} = unserialize(serialize($v));
      }
    }
  }

  public function append(array|string $elements)
  {
    if (is_array($elements)) {
      $this->elements = array_merge($this->elements, $elements);
    } else {
      $this->elements = array_merge($this->elements, [$elements]);
    }
  }

  public function getElements(): array
  {
    return $this->elements;
  }
}
