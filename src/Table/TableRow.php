<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Table;

use JDZ\Database\DatabaseInterface;
use JDZ\Database\DatabaseHelper;
use JDZ\Database\Exception\TableException;
use RuntimeException;
use Exception;

/**
 * Table Row
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class TableRow implements TableRowInterface
{
  /**
   * Constructor 
   * 
   * @param  array|object|null  $properties  Key/Value pairs
   */
  public function __construct($properties=null)
  {
    if ( null !== $properties ){
      $this->setProperties($properties);
    }
  }
  
  /**
   * Set the object properties
   * 
   * @param   mixed  $properties  Either an associative array or another object
   * @return  void
   */
  public function setProperties($properties)
  {
    if ( is_array($properties) || is_object($properties) ){
      foreach((array)$properties as $k => $v){
        $this->set($k, $v);
      }
    }
  }
  
  /**
   * Returns an associative array of object properties
   *
   * @param   bool  $object  True to return a stdClass
   * @return  array|stdClass
   */
  public function getProperties($object=true)
  {
    $properties = get_object_vars($this);
    
    if ( $object ){
      return (object)$properties;
    }
    
    return $properties;
  }
  
  /**
   * Modifies a property of the object, creating it if it does not already exist
   *
   * @param   string  $key    The name of the property
   * @param   mixed   $value  The value of the property to set
   * @return  void
   */
  public function set($key, $value=null)
  {
    $this->{$key} = $value;
  }
  
  /**
   * Set a default value
   *
   * @param   string  $key    The name of the property
   * @param   mixed   $value  The default value of the property
   * @return  void
   */
  public function def($key, $value=null)
  {
    if ( !$this->has($key) ){
      $this->set($key, $value);
    }
  }
  
  /**
   * Returns a property of the object or the default value if the property is not set
   * 
   * @param   string  $key  The name of the property
   * @param   mixed   $default   The default value
   * @return  mixed   The value of the property
   */
  public function get($key, $default=null)
  {
    if ( isset($this->{$property}) ){
      return $this->{$property};
    }
    return $default;
  }
  
  /**
   * {@inheritDoc}
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
  public function diff(TableRow $old)
  {
    if ( intval($this->get('id')) === 0 || intval($old->get('id')) === 0 ){
      return true;
    }
    
    $props = $this->getProperties(false);
    
    foreach($props as $k => $v){
      if ( $this->get($k) === $old->get($k) ){
        unset($props[$k]);
        continue;
      }
    }
    
    return ( !empty($props) > 0 );
  }
}
