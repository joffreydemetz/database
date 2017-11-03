<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Connector\Mysqli;

use JDZ\Database\Query\Query;

/**
 * Mysqli format adapter for the Query Class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MysqliQuery extends Query
{
	/**
	 * Concatenates an array of column names or values
	 *
	 * @param 	array   $values     An array of values to concatenate
	 * @param 	string  $separator  As separator to place between each value
	 * @return 	string  The concatenated values
	 */
	public function concatenate($values, $separator = null)
	{
		if ( $separator ){
			$concat_string = 'CONCAT_WS('.$this->q($separator);

			foreach($values as $value){
				$concat_string .= ', ' . $value;
			}

			return $concat_string.')';
		}
		else {
			return 'CONCAT(' . implode(',', $values) . ')';
		}
	}
}
