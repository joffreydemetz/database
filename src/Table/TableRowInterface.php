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
   * Set the object properties
   * 
   * @param   mixed  $properties  Either an associative array or another object
   * @return  void
   */
  public function setProperties($properties);
  
  /**
   * Returns an associative array of object properties
   *
   * @param   bool  $object  True to return a stdClass
   * @return  array|stdClass
   */
  public function getProperties($object=true);
  
  /**
   * Modifies a property of the object, creating it if it does not already exist
   *
   * @param   string  $key    The name of the property
   * @param   mixed   $value  The value of the property to set
   * @return  void
   */
  public function set($key, $value=null);
  
  /**
   * Returns a property of the object or the default value if the property is not set
   * 
   * @param   string  $key  The name of the property
   * @param   mixed   $default   The default value
   * @return  mixed   The value of the property
   */
  public function get($key, $default=null);
  
  /**
   * Is the property set in the object
   * 
   * @param   string  $key  The name of the property
   * @return  bool    True if the property exists
   */
  public function has($key)
  {
    return property_exists($this, $key);
  }
  
  /** 
   * Check if the record has been modified
   * 
   * @param   TableRow  $old  Another table row object to compare
   * @return  bool      True for a new record or if no modifications were found
   */
  public function diff(TableRow $old);
}
