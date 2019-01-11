<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Table;

/**
 * Table Row
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class TableRow implements TableRowInterface
{
  /**
   * Create an return a new TableRow instance 
   * 
   * @return TableRow
   */
  public static function create()
  {
    return new self();
  }
  
  public function __construct($properties=null)
  {
    if ( null !== $properties ){
      $this->setProperties($properties);
    }
  }
  
  public function all($object=false)
  {
    $properties = get_object_vars($this);
    
    if ( $object ){
      return (object)$properties;
    }
    
    return $properties;
  }  
  
  public function setProperties($properties)
  {
    if ( is_array($properties) || is_object($properties) ){
      foreach((array)$properties as $k => $v){
        $this->set($k, $v);
      }
    }
    return $this;
  }
  
  public function set($key, $value=null)
  {
    $this->{$key} = $value;
    return $this;
  }
  
  public function def($key, $value=null)
  {
    if ( !$this->has($key) ){
      $this->set($key, $value);
    }
    return $this;
  }
  
  public function get($key, $default=null)
  {
    if ( isset($this->{$key}) ){
      return $this->{$key};
    }
    return $default;
  }
  
  public function has($key)
  {
    return property_exists($this, $key);
  }
  
  public function diff(TableRow $oldRow)
  {
    $props = [];
    
    // if ( intval($this->get('id')) === 0 || intval($oldRow->get('id')) === 0 ){
      // debugMe('FUCK');
      // return $props;
    // }
    
    foreach(array_keys(get_object_vars($this)) as $field){
      if ( $this->get($field) == $oldRow->get($field) ){
        continue;
      }
      
      // ignore fields that are not set in the current record
      if ( null === $this->{$field} ){
        continue;
      }
      
      // property was modified
      $props[$field] = $this->get($field);
    }
    
    // debugMe($props);
    return $props;
  }
  
  protected function getFields()
  {
    return array_keys( get_object_vars($this) );
  }
}
