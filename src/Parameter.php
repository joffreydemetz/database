<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

class Parameter
{
  public string|int $param;
  public mixed $value;
  public int $dataType = ParamType::STR->value;
  public int $maxLength = 0;
  public mixed $driverOptions = null;

  public function __construct(string|int $param, mixed $value)
  {
    $this->param = $param;
    $this->value = $value;
  }

  public function setDataType(string|int|ParamType $dataType): void
  {
    if ($dataType instanceof ParamType) {
      $this->dataType = $dataType->value;
      return;
    }

    if (is_int($dataType)) {
      $this->dataType = $dataType;
      return;
    }

    // Handle legacy string constants
    $this->dataType = ParamType::fromString($dataType)->value;
  }
}
