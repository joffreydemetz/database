<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database;

/**
 * Database helper
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class DatabaseHelper
{
  use \JDZ\Utilities\Traits\Translatable;
	
  /**
	 * Splits a string of multiple queries into an array of individual queries.
	 *
	 * @param 	string  $sql  Input SQL string with which to split into individual queries.
	 * @return 	array  The queries from the input string separated into an array.
	 */
	public static function splitSql($sql)
	{
		$start = 0;
		$open = false;
		$char = '';
		$end = strlen($sql);
		$queries = [];

		for($i=0; $i<$end; $i++){
			$current = substr($sql, $i, 1);
			if ( $current == '"' || $current == '\'' ){
				$n = 2;

				while(substr($sql, $i - $n + 1, 1) == '\\' && $n < $i){
					$n++;
				}

				if ( $n % 2 == 0 ){
					if ( $open ){
						if ( $current == $char ){
							$open = false;
							$char = '';
						}
					}
					else {
						$open = true;
						$char = $current;
					}
				}
			}

			if ( ($current == ';' && !$open) || $i == $end - 1 ){
				$queries[] = substr($sql, $start, ($i - $start + 1));
				$start = $i + 1;
			}
		}

		return $queries;
	}
  
	/**
	 * This function replaces a string identifier <var>$prefix</var> with the string held in the
	 * <var>$tablePrefix</var> argument.
	 *
	 * @param 	string  $sql          The SQL statement to prepare
	 * @param 	string  $tablePrefix  The given table prefix
	 * @param 	string  $prefix       The common table prefix
	 * @return 	string  The processed SQL statement.
	 */
	public static function replacePrefix($sql, $tablePrefix, $prefix='#__')
	{
		$escaped   = false;
		$startPos  = 0;
		$quoteChar = '';
		$literal   = '';

		$sql = trim($sql);
		$n = strlen($sql);
    
		while ($startPos < $n){
			$ip = strpos($sql, $prefix, $startPos);
			if ( $ip === false ){
				break;
			}
      
			$j = strpos($sql, "'", $startPos);
			$k = strpos($sql, '"', $startPos);
			if ( ($k !== false) && (($k < $j) || ($j === false)) ){
				$quoteChar = '"';
				$j = $k;
			}
			else {
				$quoteChar = "'";
			}

			if ( $j === false ){
				$j = $n;
			}

			$literal .= str_replace($prefix, $tablePrefix, substr($sql, $startPos, $j - $startPos));
			$startPos = $j;

			$j = $startPos + 1;

			if ( $j >= $n ){
				break;
			}

			// quote comes first, find end of quote
			while(true){
				$k = strpos($sql, $quoteChar, $j);
				$escaped = false;
				if ( $k === false ){
					break;
				}
				$l = $k - 1;
				while($l >= 0 && $sql{$l} == '\\'){
					$l--;
					$escaped = !$escaped;
				}
				if ( $escaped ){
					$j = $k + 1;
					continue;
				}
				break;
			}
      
			if ( $k === false ){
				// error in the query - no end quote; ignore it
				break;
      }
      
			$literal .= substr($sql, $startPos, $k - $startPos + 1);
			$startPos = $k + 1;
		}
    
		if ( $startPos < $n ){
			$literal .= substr($sql, $startPos, $n - $startPos);
		}

		return $literal;
	}
}
