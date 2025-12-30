<?php

/**
 * @author    Joffrey Demetz <joffrey.demetz@gmail.com>
 * @license   MIT License; <https://opensource.org/licenses/MIT>
 */

namespace JDZ\Database;

/**
 * Fetch modes
 *
 * The values match the `PDO::FETCH_*` constants.
 */
enum FetchMode: int
{
	/**
	 * @see    \PDO::FETCH_ASSOC
	 */
	case ASSOCIATIVE = 2;

	/**
	 * @see    \PDO::FETCH_NUM
	 */
	case NUMERIC = 3;

	/**
	 * @see    \PDO::FETCH_BOTH
	 */
	case MIXED = 4;

	/**
	 * @see    \PDO::FETCH_OBJ
	 */
	case STANDARD_OBJECT = 5;

	/**
	 * @see    \PDO::FETCH_COLUMN
	 */
	case COLUMN = 7;

	/**
	 * @see    \PDO::FETCH_CLASS
	 */
	case CUSTOM_OBJECT = 8;
}
