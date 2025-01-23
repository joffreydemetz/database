<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

use JDZ\Database\Database;
use JDZ\Database\Element;
use JDZ\Database\Parameter;

/**
 * Query
 * 
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Query
{
  public Database $db;

  protected string $type = '';
  protected string|null $alias = null;
  protected ?Element $element = null;

  protected ?Element $select = null;
  protected ?Element $delete = null;
  protected ?Element $update = null;
  protected ?Element $insert = null;

  protected ?Element $from = null;
  protected array $join = [];

  protected ?Element $set = null;
  protected ?Element $where = null;
  protected ?Element $group = null;
  protected ?Element $having = null;
  protected ?Element $columns = null;
  protected ?Element $values = null;
  protected ?Element $order = null;
  protected ?Element $union = null;

  protected Query|string $sql = '';
  protected int $limit = 0;
  protected int $offset = 0;
  protected array $bounded = [];

  public function __construct(Database $db)
  {
    $this->db = $db;
  }

  public function __toString(): string
  {
    if ($this->sql) {
      return $this->processLimit($this->sql, $this->limit, $this->offset);
    }

    $query = '';

    switch ($this->type) {
      case 'element':
        $query .= (string) $this->element;
        break;

      case 'select':
        $query .= (string) $this->select;
        $query .= (string) $this->from;

        if ($this->join) {
          foreach ($this->join as $join) {
            $query .= (string) $join;
          }
        }

        if ($this->where) {
          $query .= (string) $this->where;
        }

        if ($this->group) {
          $query .= (string) $this->group;
        }

        if ($this->having) {
          $query .= (string) $this->having;
        }

        if ($this->order) {
          $query .= (string) $this->order;
        }

        break;

      case 'union':
        $query .= (string) $this->union;
        break;

      case 'delete':
        $query .= (string) $this->delete;
        $query .= (string) $this->from;

        if ($this->join) {
          // special case for joins
          foreach ($this->join as $join) {
            $query .= (string) $join;
          }
        }

        if ($this->where) {
          $query .= (string) $this->where;
        }

        break;

      case 'update':
        $query .= (string) $this->update;

        if ($this->join) {
          // special case for joins
          foreach ($this->join as $join) {
            $query .= (string) $join;
          }
        }

        $query .= (string) $this->set;

        if ($this->where) {
          $query .= (string) $this->where;
        }

        break;

      case 'insert':
        $query .= (string) $this->insert;

        // Set method
        if ($this->set) {
          $query .= (string) $this->set;
        }

        // Columns-Values method
        elseif ($this->values) {
          if ($this->columns) {
            $query .= (string) $this->columns;
          }

          $query .= ' VALUES ';
          $query .= (string) $this->values;
        }

        break;
    }

    $query = $this->processLimit($query, $this->limit, $this->offset);

    return $query;
  }

  public function __get(string $name)
  {
    return isset($this->$name) ? $this->$name : null;
  }

  public function setQuery(Query|string $sql)
  {
    $this->sql = $sql;
    return $this;
  }

  public function dump(): string
  {
    return str_replace('#__', $this->db->tablePrefix, (string)$this);
  }

  public function e(string $text, bool $extra = false): string
  {
    return $this->escape($text, $extra);
  }

  public function q(string $text, bool $escape = true)
  {
    return $this->quote($text, $escape);
  }

  public function qn(string $name, string|null $as = null): string
  {
    return $this->quoteName($name, $as);
  }

  public function clear(string|null $clause = null)
  {
    $this->sql = null;

    switch ($clause) {
      case 'select':
        $this->select = null;
        $this->type   = null;
        break;

      case 'delete':
        $this->delete = null;
        $this->type   = null;
        break;

      case 'update':
        $this->update = null;
        $this->type   = null;
        break;

      case 'insert':
        $this->insert = null;
        $this->type   = null;
        break;

      case 'from':
        $this->from = null;
        break;

      case 'join':
        $this->join = null;
        break;

      case 'set':
        $this->set = null;
        break;

      case 'where':
        $this->where = null;
        break;

      case 'group':
        $this->group = null;
        break;

      case 'having':
        $this->having = null;
        break;

      case 'order':
        $this->order = null;
        break;

      case 'columns':
        $this->columns = null;
        break;

      case 'values':
        $this->values = null;
        break;

      case 'limit':
        $this->offset = 0;
        $this->limit  = 0;
        break;

      case 'alias':
        $this->alias = null;
        break;

      case 'offset':
        $this->offset = 0;
        break;

      case 'bounded':
        $this->bounded = [];
        break;

      default:
        $this->type    = null;
        $this->select  = null;
        $this->delete  = null;
        $this->update  = null;
        $this->insert  = null;
        $this->from    = null;
        $this->join    = null;
        $this->set     = null;
        $this->where   = null;
        $this->group   = null;
        $this->having  = null;
        $this->order   = null;
        $this->columns = null;
        $this->values  = null;
        $this->offset  = 0;
        $this->limit   = 0;
        $this->alias   = null;
        $this->bounded = [];
        break;
    }

    return $this;
  }

  public function escape(string $text, bool $extra = false): string
  {
    return $this->db->escape($text, $extra);
  }

  public function quote(string $text, bool $escape = true): string
  {
    return $this->db->quote($text, $escape);
  }

  public function quoteName(string $name): string
  {
    return $this->db->quoteName($name);
  }

  public function dateFormat(): string
  {
    return $this->db->dateFormat;
  }

  public function nullDate(bool $quoted = true): string
  {
    if (true === $quoted) {
      $result = $this->db->quote($this->db->getNullDate());
    }
    return $this->db->getNullDate();
  }

  public function concatenate(array $values, string $separator = ''): string
  {
    if ($separator) {
      $concat_string = 'CONCAT_WS(' . $this->q($separator);

      foreach ($values as $value) {
        $concat_string .= ', ' . $value;
      }

      return $concat_string . ')';
    } else {
      return 'CONCAT(' . implode(',', $values) . ')';
    }
  }

  public function currentTimestamp(): string
  {
    return 'CURRENT_TIMESTAMP()';
  }

  public function castAsChar(string $value): string
  {
    return $value;
  }

  public function charLength(string $field): string
  {
    return 'CHAR_LENGTH(' . $field . ')';
  }

  public function columns(array|string $columns)
  {
    if (null === $this->columns) {
      $this->columns = new Element('()', $columns);
    } else {
      $this->columns->append($columns);
    }

    return $this;
  }

  public function delete(string $table)
  {
    $this->type = 'delete';
    $this->delete = new Element('DELETE');
    $this->from($table);
    return $this;
  }

  public function from(array|string $tables)
  {
    if (null === $this->from) {
      $this->from = new Element('FROM', $tables);
    } else {
      $this->from->append($tables);
    }

    return $this;
  }

  public function alias(string $alias)
  {
    $this->alias = $alias;
    return $this;
  }

  public function group(array|string $columns)
  {
    if (null === $this->group) {
      $this->group = new Element('GROUP BY', $columns);
    } else {
      $this->group->append($columns);
    }

    return $this;
  }

  public function having(array|string $conditions, string $glue = 'AND')
  {
    if (null === $this->having) {
      $glue = strtoupper($glue);
      $this->having = new Element('HAVING', $conditions, " $glue ");
    } else {
      $this->having->append($conditions);
    }

    return $this;
  }

  public function insert(string $table, bool $ignore = false)
  {
    $this->type = 'insert';
    $this->insert = new Element('INSERT' . (true === $ignore ? ' IGNORE' : '') . ' INTO', $table);
    return $this;
  }

  public function join(string $type, string $condition)
  {
    if (null === $this->join) {
      $this->join = [];
    }
    $this->join[] = new Element(strtoupper($type) . ' JOIN', $condition);
    return $this;
  }

  public function leftJoin(string $condition)
  {
    $this->join('LEFT', $condition);
    return $this;
  }

  public function rightJoin(string $condition)
  {
    $this->join('RIGHT', $condition);
    return $this;
  }

  public function innerJoin(string $condition)
  {
    $this->join('INNER', $condition);
    return $this;
  }

  public function outerJoin(string $condition)
  {
    $this->join('OUTER', $condition);
    return $this;
  }

  public function length(string $value)
  {
    return 'LENGTH(' . $value . ')';
  }

  public function order(array|string $columns)
  {
    if (null === $this->order) {
      $this->order = new Element('ORDER BY', $columns);
    } else {
      $this->order->append($columns);
    }

    return $this;
  }

  public function select(array|string $columns)
  {
    $this->type = 'select';

    if (null === $this->select) {
      $this->select = new Element('SELECT', $columns);
    } else {
      $this->select->append($columns);
    }

    return $this;
  }

  public function set(array|string $conditions, string $glue = ',')
  {
    if (null === $this->set) {
      $glue = strtoupper($glue);
      $this->set = new Element('SET', $conditions, "\n\t$glue ");
    } else {
      $this->set->append($conditions);
    }

    return $this;
  }

  public function setLimit(int $limit = 0, int $offset = 0)
  {
    $this->limit  = (int) $limit;
    $this->offset = (int) $offset;

    return $this;
  }

  public function processLimit(string $query, int $limit, int $offset = 0)
  {
    if ($limit > 0 && $offset > 0) {
      $query .= ' LIMIT ' . $offset . ', ' . $limit;
    } elseif ($limit > 0) {
      $query .= ' LIMIT ' . $limit;
    }

    return $query;
  }

  public function update(string $table, bool $ignore = false)
  {
    $this->type = 'update';
    $this->update = new Element('UPDATE' . (true === $ignore ? ' IGNORE' : ''), $table);

    return $this;
  }

  public function values(array|string $values)
  {
    if (null === $this->values) {
      $this->values = new Element('()', $values, '), (');
    } else {
      $this->values->append($values);
    }

    return $this;
  }

  public function where(array|string $conditions, string $glue = 'AND')
  {
    if (null === $this->where) {
      $glue = strtoupper($glue);
      $this->where = new Element('WHERE', $conditions, " $glue ");
    } else {
      $this->where->append($conditions);
    }

    return $this;
  }

  public function union(array|string $query, bool $distinct = false, string $glue = '')
  {
    if ($this->order) {
      $this->clear('order');
    }

    if (true === $distinct) {
      $name = 'UNION DISTINCT ()';
      $glue = ')' . PHP_EOL . 'UNION DISTINCT (';
    } else {
      $glue = ')' . PHP_EOL . 'UNION (';
      $name = 'UNION ()';
    }

    if (null === $this->union) {
      $this->union = new Element($name, $query, "$glue");
    } else {
      $glue = '';
      $this->union->append($query);
    }

    return $this;
  }

  public function unionDistinct(array|string $query, string $glue = '')
  {
    return $this->union($query, true, $glue);
  }

  public function bindParam(string|int $key, mixed $value, string|int $dataType = 'string', int $maxLength = 0, array $driverOptions = [])
  {
    $bind = new Parameter($key, $value);
    $bind->setDataType($dataType);
    $bind->maxLength = $maxLength;
    $bind->driverOptions = $driverOptions;

    $this->bounded[$key] = $bind;

    return $this;
  }

  public function unbind(string|int $key)
  {
    if (\is_array($key)) {
      foreach ($key as $k) {
        unset($this->bounded[$k]);
      }
    } else {
      unset($this->bounded[$key]);
    }

    return $this;
  }

  public function bindArray(array $data, string $dataType = 'string')
  {
    foreach ($data as $key => $value) {
      $this->bindParam($key, $value, $dataType);
    }

    return $this;
  }

  public function getBounded()
  {
    return $this->bounded;
  }
}
