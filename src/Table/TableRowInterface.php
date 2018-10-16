<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Table;

/**
 * TableRow interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface TableRowInterface
{
  /**
   * Set the record properties
   * 
   * @param  mixed  $properties  Either an associative array or an object
   * @return void
   */
  public function setProperties($properties);
  
  /**
   * Returns the record data as an associative array or object
   *
   * @param  bool  $object  True to return a stdClass
   * @return array|stdClass
   */
  public function all($object=true);
  
  /**
   * Modifies a property of the record
   *
   * @param  string  $key    The name of the property
   * @param  mixed   $value  The value of the property to set
   * @return void
   */
  public function set($key, $value=null);
  
  /**
   * Returns a property of the record or the default value if the property is not set
   * 
   * @param  string  $key  The name of the property
   * @param  mixed   $default   The default value
   * @return mixed   The value of the property
   */
  public function get($key, $default=null);
  
  /**
   * Is the property set in the record
   * 
   * @param  string  $key  The name of the property
   * @return bool    True if the property exists
   */
  public function has($key);
  
  /** 
   * Check if the record has been modified
   * 
   * @param  TableRow  $oldRow  Old row data
   * @return array     Associative array with the modifed fields
   */
  public function diff(TableRow $oldRow);
}
