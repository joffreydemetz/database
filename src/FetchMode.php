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
final class FetchMode
{
	/**
	 * @var    int
	 * @see    \PDO::FETCH_ASSOC
	 */
	public const ASSOCIATIVE = 2;

	/**
	 * @var    int
	 * @see    \PDO::FETCH_NUM
	 */
	public const NUMERIC = 3;

	/**
	 * @var    int
	 * @see    \PDO::FETCH_BOTH
	 */
	public const MIXED = 4;

	/**
	 * @var    int
	 * @see    \PDO::FETCH_OBJ
	 */
	public const STANDARD_OBJECT = 5;

	/**
	 * @var    int
	 * @see    \PDO::FETCH_COLUMN
	 */
	public const COLUMN = 7;

	/**
	 * @var    int
	 * @see    \PDO::FETCH_CLASS
	 */
	public const CUSTOM_OBJECT = 8;

	private function __construct() {}
}
