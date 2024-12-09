<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Database;

/**
 * Fetch modes
 *
 * The values of the constants in this class match the `PDO::FETCH_*` constants.
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
