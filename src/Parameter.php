<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Query Parameter
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Parameter
{
  const PARAM_BOOL = 'bool';
  const PARAM_NULL = 'null';
  const PARAM_INT = 'int';
  const PARAM_INT_S = 'i';
  const PARAM_STR = 'string';
  const PARAM_STR_S = 's';
  // const PARAM_STR_NATL = 'natl';
  // const PARAM_STR_CHAR = 'char';
  const PARAM_LOB = 'blob';
  const PARAM_LOB_S = 'b';

  public string|int $param;
  public mixed $value;
  public int $dataType = \PDO::PARAM_STR;
  public int $maxLength = 0;
  public mixed $driverOptions = null;

  public function __construct(string|int $param, mixed $value)
  {
    $this->param = $param;
    $this->value = $value;
  }

  public function setDataType(string|int $dataType)
  {
    switch ($dataType) {
      case \PDO::PARAM_BOOL:
      case self::PARAM_BOOL:
        $this->dataType = \PDO::PARAM_BOOL;
        break;

      case \PDO::PARAM_NULL:
      case self::PARAM_NULL:
        $this->dataType = \PDO::PARAM_NULL;
        break;

      case \PDO::PARAM_INT:
      case self::PARAM_INT:
      case self::PARAM_INT_S:
        $this->dataType = \PDO::PARAM_INT;
        break;

      case \PDO::PARAM_LOB:
      case self::PARAM_LOB:
      case self::PARAM_LOB_S:
        $this->dataType = \PDO::PARAM_LOB;
        break;

      case \PDO::PARAM_STR:
      case self::PARAM_STR_S:
      default:
        $this->dataType = \PDO::PARAM_STR;
        break;
    }
  }
}
