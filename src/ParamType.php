<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

/**
 * Parameter types for prepared statements
 *
 * The values match the `PDO::PARAM_*` constants.
 */
enum ParamType: int
{
    /**
     * @see    \PDO::PARAM_BOOL
     */
    case BOOL = 5;

    /**
     * @see    \PDO::PARAM_NULL
     */
    case NULL = 0;

    /**
     * @see    \PDO::PARAM_INT
     */
    case INT = 1;

    /**
     * @see    \PDO::PARAM_STR
     */
    case STR = 2;

    /**
     * @see    \PDO::PARAM_LOB
     */
    case LOB = 3;

    /**
     * Create from string representation
     */
    public static function fromString(string $type): self
    {
        return match (strtolower($type)) {
            'bool', 'boolean' => self::BOOL,
            'null' => self::NULL,
            'int', 'integer', 'i' => self::INT,
            'blob', 'lob', 'b' => self::LOB,
            'string', 'str', 's' => self::STR,
            default => self::STR,
        };
    }
}
