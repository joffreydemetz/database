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
use Callisto\Utils\Component\Component as ComponentObject;

use RuntimeException;

/**
 * Table connector class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Table implements TableInterface
{
  use \JDZ\Utilities\Traits\Error,
      \JDZ\Utilities\Traits\Get,
      \JDZ\Utilities\Traits\Set, 
      \JDZ\Utilities\Traits\Ns;
  
  /**
   * Database instance
   * 
   * @var    DatabaseInterface
   */
  protected $db;
  
  /**
   * Table name
   * 
   * @var    string
   */
  protected $tbl;
  
  /**
   * Table short name (with no prefix)
   * 
   * @var    string
   */
  protected $tbl_name;
  
  /**
   * Table primary key
   * 
   * @var    string
   */
  protected $tbl_key;
  
  /**
   * Instances
   * 
   * @var    array
   */
  protected static $instances;
  
  /**
   * Get a table instance
   * 
   * @param   string              $name  The table name
   * @param   DatabaseInterface   $dbo   The database object
   * @return   Table               Table instance clone
   * @throws   TableException
   */
  public static function getInstance($name, DatabaseInterface &$dbo)
  {
    if ( empty($name) ){
      throw new TableException('Missing table type');
    }
    
    if ( !isset(self::$NS) ){
      self::$NS = '\\Database\\Table\\';
    }
    
    if ( !isset(self::$instances) ){
      self::$instances = [];
    }
    
    if ( !isset(self::$instances[$name]) ){
      $Class = self::$NS.ucfirst($name);
      
      if ( !class_exists($Class) ){
        throw new TableException('Unrecognized table :: '.$name);
      }
      
      self::$instances[$name] = new $Class($name, $dbo);
    }
    
    return clone self::$instances[$name];
  }  
  
  /** 
   * Select list options
   * 
   * @param   string  $tblName    The table name
   * @param   string  $valueKey   value field name
   * @param   string  $textKey    text field name
   * @return   array   The options array of objects
   */
  public static function getFilterOptions($tblName, $valueKey='id', $textKey='title')
  {
    $table = Table($tblName);
    $query = $table->db->getQuery(true);
    $query->select($valueKey.' AS value, '.$textKey.' AS text');
    $query->from($table->tbl);
    $query->order('text ASC');
    
    $table->db->setQuery($query);
    $options = (array)$table->db->loadObjectList();
    
    return $options;
  }
  
  /**
   * Constructor
   * 
   * @param   string              $name  Table name
   * @param   DatabaseInterface   $dbo   Database object
   */
  public function __construct($name, DatabaseInterface &$dbo)
  {
    $this->db =& $dbo;
    
    $this->tbl_name = $name;
    
    if ( !isset($this->tbl) ){
      $this->tbl = $this->db->tablePrefix.'_'.$this->tbl_name;
    }
    
    if ( !isset($this->tbl_key) ){
      $this->tbl_key = 'id';
    }
    
    if ( $fields = $this->getFields() ){ 
      foreach($fields as $field => $v){
        if ( !$this->hasField($field) ){
          $this->{$field} = null;
        }
      }
    }
  }
  
  /**
   * Magic method to return some protected property values
   *
   * Returns null if the property is not set.
   * 
   * @param   string            $name  The name of the property to return
   * @return   string|null       Value or null if the property is not set
   * @throw   RuntimeException  If property is not set
   */
  public function __get($name)
  {
    switch($name){
      case 'db':
      case 'tbl':
      case 'tbl_name':
      case 'tbl_key':
        return isset($this->{$name}) ? $this->{$name} : null;
      
      default:
        throw new RuntimeException('Cannot access/get property ' . __CLASS__ . '::' . $name);
    }
  }
  
  /**
   * {@inheritDoc}
    */
  public function getTblName()
  {
    return $this->tbl_name;
  }
  
  /**
   * {@inheritDoc}
    */
  public function getTbl()
  {
    return $this->tbl;
  }
  
  /**
   * {@inheritDoc}
    */
  public function getTblKey()
  {
    return $this->tbl_key;
  }
  
  /**
   * {@inheritDoc}
    */
  public function filterGetProperties(array $properties=[])
  {
    return array_merge($properties, ['db', 'tbl', 'tbl_name', 'tbl_key']);
  }
  
  /**
   * {@inheritDoc}
    */
  public function rowIsDisabled($id)
  {
    return false;
  }
  
  /**
   * {@inheritDoc}
    */
  public function getPreviousVersion()
  {
    $this->versionAble(false);
    
    $vTable = Table('version');
    
    $query = $this->db->getQuery(true);
    $query->select('id');
    $query->from($vTable->getTbl());
    $query->where($this->db->qn('table_name').' = '.$this->db->q($this->tbl));
    $query->where($this->db->qn('row_id').' = '.(int)$this->id);
    $query->order($this->db->qn('versionNum').' DESC');
    $this->db->setQuery($query);
    $versionNum = (int)$this->db->loadResult();
    return $versionNum;
  }
  
  /**
   * {@inheritDoc}
    */
  public function saveAssociations($right, $left_id, array $right_items, $clear=true, $order=false)
  {
    $query = $this->db->getQuery(true);
    
    if ( $clear ){
      // remove previous associations
      $query->clear();
      $query->delete($this->db->qn($this->tbl.'_'.$right));
      $query->where($this->db->qn('id_'.$this->tbl_name).'='.$left_id);
      $this->db->setQuery($query);
      $this->db->execute();
    }
    
    if ( !empty($right_items) ){
      if ( $order && !$clear ){
        // get the last set ordering value
        $query->clear();
        $query->select('MAX(ordering)');
        $query->from($this->db->qn($this->tbl.'_'.$right));
        $query->where('id_'.$this->tbl_name.'='.$left_id);
        $this->db->setQuery($query);
        $ordering = (int)$this->db->loadResult();
      }
      else {
        $ordering = 0;
      }
      
      // add the associated items
      $query->clear();
      $query->insert($this->db->qn($this->tbl.'_'.$right));
      
      if ( $order ){
        $query->columns($this->db->qn('id_'.$this->tbl_name).', '.$this->db->qn('id_'.$right).', '.$this->db->qn('ordering'));
        foreach($right_items as $right_id){
          $query->values($left_id.','.(int)$right_id.','.++$ordering);
        }
      }
      else {
        $query->columns($this->db->qn('id_'.$this->tbl_name).', '.$this->db->qn('id_'.$right));
        foreach($right_items as $right_id){
          $query->values($left_id.','.(int)$right_id);
        }
      }
      
      $this->db->setQuery($query);
      $this->db->execute();
    }
  }
  
  /**
   * {@inheritDoc}
    */
  public function reset()
  {
    // Get the default values for the class from the table.
    foreach ($this->getFields() as $k => $v){
      // If the property is not the primary key or private, reset it.
      if ( $k != $this->tbl_key && (strpos($k, '_') !== 0) ){
        $this->$k = $v->Default;
      }
    }
  }
  
  /**
   * {@inheritDoc}
    */
  public function load($keys=null, $reset=true, $clean=true)
  {
    if ( empty($keys) ){
      $keyName  = $this->tbl_key;
      $keyValue = $this->$keyName;

      if ( empty($keyValue) ){
        return true;
      }
      
      $keys = [ $keyName => $keyValue ];
    }
    elseif ( !is_array($keys) ){
      $keys = [ $this->tbl_key => $keys ];
    }

    if ( $reset ){
      $this->reset();
    }
    
    $query = $this->db->getQuery(true);
    $query->select('*');
    $query->from($this->tbl);
    $fields = array_keys($this->getProperties());
    
    foreach($keys as $field => $value){
      if ( !in_array($field, $fields) ){
        throw new TableException(sprintf('Missing field %s in %s', $field, get_class($this)));
      }
      
      $query->where($this->db->qn($field).'='.$this->db->q($value));
    }

    $this->db->setQuery($query);
    
    $row = $this->db->loadAssoc();
    
    if ( empty($row) ){
      $this->setError(DatabaseHelper::getTranslation('ERROR_EMPTY_ROW'));
      return false;
    }
    
    if ( $this->bind($row) ){
      if ( $clean ){
        $this->setFieldsTypeByName();
      }
      return true;
    }
    
    return false;
  }
  
  /**
   * {@inheritDoc}
    */
  public function loadBySlug($slug)
  {
    $this->slugAble(false);
    
    return $this->load(['slug'=>$slug]);
  }
  
  /**
   * {@inheritDoc}
    */
  public function getByCategory($id_category)
  {
    $this->categorizeAble(false);
    
    $query = $this->db->getQuery(true);
    $query->select('id');
    $query->from($this->tbl);
    $query->where('id_category='.(int)$id_category);
    $this->db->setQuery($query);
    $items = (array)$this->db->loadColumn();
    
    return $items;
  }
  
  /**
   * {@inheritDoc}
    */
  public function save(array $src, array $ignore=[])
  {
    $oldValues=[];
    
    if ( !$this->bind($src, $ignore, $oldValues) ){
      return false;
    }
    
    if ( !$this->hasBeenModified($oldValues) ){
      // $this->setError(DatabaseHelper::getTranslation('ERROR_RECORD_UNCHANGED'));
      return true;
    }
    
    if ( !$this->check() ){
      return false;
    }
    
    return ( $this->store() );
  }
  
  /**
   * {@inheritDoc}
    */
  public function bind(array $src, array $ignore=[], array &$oldValues=[])
  {
    if ( !is_object($src) && !is_array($src) ){
      throw new TableException(sprintf('Row bind failed in %', get_class($this)));
    }
    
    if ( is_object($src) ){
      $src = get_object_vars($src);
    }

    if ( !is_array($ignore) ){
      $ignore = explode(' ', $ignore);
    }
    
    foreach($this->getProperties() as $k => $v){
      if ( intval($this->id) > 0 ){
        $oldValues[$k] = $this->$k;
      }
      
      if ( in_array($k, $ignore) || !isset($src[$k]) ){
        continue;
      }
      
      $this->$k = $src[$k];
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function delete($pk=null)
  {
    $k = $this->tbl_key;
    
    if ( $pk === null ){
      $pk = $this->{$k};
    }
    
    if ( $pk === null ){
      $this->setError(DatabaseHelper::getTranslation('NO_ITEM_SELECTED'));
      return false;
    }
    
    if ( $this->statesAble() ){
      $this->load($pk);
      
      if ( $this->deleted !== '' && $this->deleted !== $this->db->nullDate ){
        $this->setError(DatabaseHelper::getTranslation('ERROR_ITEM_ALREADY_DELETED'));
        return false;
      }
      
      if ( $this->modified === '' || $this->modified === $this->db->nullDate ){
        $this->modified    = $this->db->now;
        $this->modified_by = $this->db->Uid;
      }
      
      $this->deleted    = $this->db->now; 
      $this->deleted_by = $this->db->Uid;
      
      if ( $this->publishingAble() ){
        $this->published = 0; 
      }
      
      return ( $this->store() );
    }
    
    $query = $this->db->getQuery(true);
    $query->delete();
    $query->from($this->tbl);
    $query->where($this->tbl_key.' = '.$this->db->q($pk));
    
    $this->db->setQuery($query);
    $this->db->execute();
    
    if ( $this->orderingAble() ){
      $this->reorder($this->getReorderConditions());
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function untrash($pk=null)
  {
    $k = $this->tbl_key;
    
    if ( $pk === null ){
      $pk = $this->{$k};
    }
    
    if ( $pk === null ){
      $this->setError(DatabaseHelper::getTranslation('NO_ITEM_SELECTED'));
      return false;
    }
    
    if ( $this->statesAble() ){
      $this->load($pk);
      
      if ( $this->deleted === $this->db->nullDate ){
        return false;
      }
      
      $this->deleted    = $this->db->nullDate;
      $this->deleted_by = 0;
      
      $this->store();
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function shred($pk=null)
  {
    $k = $this->tbl_key;
    
    if ( $pk === null ){
      $pk = $this->{$k};
    }
    
    if ( $pk === null ){
      $this->setError(DatabaseHelper::getTranslation('NO_ITEM_SELECTED'));
      return false;
    }
    
    if ( $this->statesAble() ){
      $this->load($pk);
      
      $query = $this->db->getQuery(true);
      $query->delete();
      $query->from($this->tbl);
      $query->where($this->tbl_key.' = '.$this->db->q($pk));
      
      $this->db->setQuery($query);
      $this->db->execute();
    
      if ( $this->orderingAble() ){
        $this->reorder($this->getReorderConditions());
      }
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function revert($pk, array $data)
  {
    if ( !$this->load($pk, true, false) ){
      return false;
    }
    
    foreach($data as $key => $value){
      $this->{$key} = $value;
    }
    
    $this->id = (int)$pk;
    
    return $this->store();
  }
  
  /**
   * {@inheritDoc}
    */
  public function publish($pk=null, $state=1)
  {
    $this->publishingAble(false);
    
    $k = $this->tbl_key;
    
    if ( empty($pk) ){
      $pk = $this->{$k};
    }
    
    if ( $pk === null ){
      $this->setError(DatabaseHelper::getTranslation('NO_ITEM_SELECTED'));
      return false;
    }
    
    $state = (int)$state;
    
    if ( $this->statesAble() ){
      if ( $this->deleted !== '' && $this->deleted !== $this->db->nullDate ){
        $this->setError(DatabaseHelper::getTranslation('ERROR_DELETED_SO_CANNOT_PUBLISH'));
        return false;
      }
    }
    
    $query = $this->db->getQuery(true);
    $query->update($this->tbl);
    $query->set('published='.(int)$state);
    $query->where($this->tbl_key.' = '.$this->db->q($pk));
    
    if ( $this->statesAble() ){
      $query->set('modified='.$this->db->q($this->db->now));
      $query->set('modified_by='.$this->db->q($this->db->Uid));
    }
    
    $this->db->setQuery($query);
    $this->db->execute();
    
    if ( $this->$k === $pk ){
      $this->published = $state;
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function changeorder($pk, $to, $minOrder=1, $maxOrder=0)
  {
    $this->orderingAble(false);
    
    $k = $this->tbl_key;
    
    if ( $pk === null ){
      $pk = $this->{$k};
    }
    
    if ( !$this->load($pk) ){
      return false;
    }
    
    $to        = (int)$to;
    $from      = (int)$this->ordering;
    $direction = $from > $to ? 'down' : 'up';
    
    if ( $from === $to ){
      return true;
    }
    
    $where = $this->getReorderConditions();
    $where[] = $k.' != '.$pk;
    $where[] = 'ordering <> 0';
    if ( $minOrder && $maxOrder ){
      $where[] = 'ordering >= '.$minOrder;
      $where[] = 'ordering <= '.$maxOrder;
    }
    
    $whereBefore = $whereAfter = $where;
    
    if ( $direction === 'down' ){
      $whereBefore[] = 'ordering < '.$to;
      $whereAfter[]  = 'ordering >= '.$to;
    }
    else {
      $whereBefore[] = 'ordering <= '.$to;
      $whereAfter[]  = 'ordering > '.$to;
    }
    
    $itemsBefore = [];
    $itemsAfter  = [];
    
    // get items before the moved element
    $query = $this->db->getQuery(true);
    $query->select($k.', ordering');
    $query->from($this->tbl);
    $query->where($whereBefore);
    $query->order('ordering ASC');
    $this->db->setQuery($query);
    $itemsBefore = $this->db->loadObjectList();
    
    // get items after the moved element
    $query = $this->db->getQuery(true);
    $query->select($k.', ordering');
    $query->from($this->tbl);
    $query->where($whereAfter);
    $query->order('ordering ASC');
    $this->db->setQuery($query);
    $itemsAfter = $this->db->loadObjectList();
    
    // reorder
    $i=$minOrder;
    
    foreach($itemsBefore as $row){
      $query = $this->db->getQuery(true);
      $query->update($this->tbl);
      $query->set('ordering='.$i);
      $query->where($k.'='.$row->{$k});
      $this->db->setQuery($query);
      $this->db->execute();
      $i++;
    }
    
    // here is the reordered element
    $query = $this->db->getQuery(true);
    $query->update($this->tbl);
    $query->set('ordering='.$i);
    $query->where($k.'='.$pk);
    $this->db->setQuery($query);
    $this->db->execute();
    $i++;
    
    foreach($itemsAfter as $row){
      $query = $this->db->getQuery(true);
      $query->update($this->tbl);
      $query->set('ordering='.$i);
      $query->where($k.'='.$row->{$k});
      $this->db->setQuery($query);
      $this->db->execute();
      $i++;
    }
    
    // set the new item order
    $this->ordering = $to;
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function reorder($where='')
  {
    $this->orderingAble(false);
    
    $k = $this->tbl_key;
    
    $query = $this->db->getQuery(true);
    $query->select($this->tbl_key . ', ordering');
    $query->from($this->tbl);
    $query->where('ordering >= 0');
    $query->order('ordering');
    
    if ( $where ){
      $query->where($where);
    }
    
    $this->db->setQuery($query);
    $rows = $this->db->loadObjectList();
    
    foreach($rows as $i => $row){
      if ( $row->ordering >= 0 ){
        if ( $row->ordering != $i+1 ){
          $query = $this->db->getQuery(true);
          $query->update($this->tbl);
          $query->set('ordering='.($i+1));
          $query->where($this->tbl_key.' = '.$this->db->q($row->$k));
          $this->db->setQuery($query);
          $this->db->execute();
        }
      }
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function trashedItems(array $conditions=[])
  {
    $conditions[] = $this->db->qn('deleted').' != '.$this->db->q($this->db->nullDate);
    // $conditions[] = $this->db->qn('deleted_by').' <> 0';
    
    $query = $this->db->getQuery(true);
    $query->select($this->db->qn('id'));
    $query->from($this->db->qn($this->tbl));
    $query->where($conditions);
    
    $this->db->setQuery($query);
    return (array)$this->db->loadColumn();
  }
  
  /**
   * {@inheritDoc}
    */
  public function getNextOrder($where = '')
  {
    $this->orderingAble(false);
    
    $query = $this->db->getQuery(true);
    $query->select('MAX(ordering)');
    $query->from($this->tbl);

    if ( $where ){
      $query->where($where);
    }
    
    $this->db->setQuery($query);
    $max = (int) $this->db->loadResult();
    
    return ($max + 1);
  }

  /**
   * {@inheritDoc}
    */
  public function getReorderConditions()
  {
    $conditions = [];
      if ( $this->categorizeAble() ){
      $conditions[] = 'id_category='.$this->db->q($this->id_category);
    }
    return $conditions;
  }
  
  /**
   * {@inheritDoc}
    */
  public function getDefaultOrdering($prefix='a.')
  {
    if ( $this->orderingAble() ){
      if ( $this->categorizeAble() ){
        return 'category ASC, '.$prefix.'ordering';
      }
      
      return $prefix.'ordering';
    }
    return $prefix.$this->tbl_key;
  }
  
  /**
   * {@inheritDoc}
    */
  public function checkTitleUnique($id, $title, array $conditions=[])
  {
    $conditions[] = $this->db->qn('title').'='.$this->db->q($title);
    
    $query = $this->db->getQuery(true);
    $query->select($this->db->qn('id'));
    $query->from($this->db->qn($this->tbl));
    $query->where($conditions);
    
    // updating
    if ( $id > 0 ){
      // id different from the current one
      $query->where($this->db->qn('id').'<>'.$this->db->q($id));
    }
    
    $this->db->setQuery($query);
    $id = (int)$this->db->loadResult();
    if ( $id > 0 ){
      $this->setError(DatabaseHelper::getTranslation('ERROR_TITLE_EXISTS'));
      return false;
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function checkSlugUnique($id, $slug, array $conditions=[])
  {
    $this->slugAble();
    
    $conditions[] = $this->db->qn('slug').'='.$this->db->q($slug);
    
    $query = $this->db->getQuery(true);
    $query->select($this->db->qn('id'));
    $query->from($this->db->qn($this->tbl));
    $query->where($conditions);
    
    // updating
    if ( $id > 0 ){
      // id different from the current one
      $query->where($this->db->qn('id').'<>'.$this->db->q($id));
    }
    
    $this->db->setQuery($query);
    $id = (int)$this->db->loadResult();
    if ( $id > 0 ){
      $this->setError(DatabaseHelper::getTranslation('ERROR_SLUG_EXISTS'));
      return false;
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function hasField($fields)
  {
    if ( !is_array($fields) ){
      $fields = [ $fields ];
    }
    
    foreach($fields as $field){
      if ( !property_exists($this, $field) ){
        return false;
      }
    }
    
    return true;
  }
  
  /**
   * {@inheritDoc}
    */
  public function slugAble($return=true)
  {  
    // $able = $this->hasField('slug') && $this->hasField('title') );
    $able = $this->hasField(['slug', 'title']);
    
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support slug ['.get_class($this).']');
    }
    
    return $able;
  }
  
  /**
   * {@inheritDoc}
    */
  public function categorizeAble($return=true)
  {  
    $able = $this->hasField(['id_category']);
    
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support categories ['.get_class($this).']');
    }
    
    return $able;
  }
  
  /**
   * {@inheritDoc}
    */
  public function statesAble($return=true)
  {  
    $able = $this->hasField('created');
    
    if ( $able === true ){
      if ( !$this->hasField('created_by') || !$this->hasField('modified') || !$this->hasField('modified_by') || !$this->hasField('deleted') || !$this->hasField('deleted_by') ){
        throw new TableException('Table should support states but all 6 fields are not available ['.get_class($this).']');
      }      
    }
    
    if ( $return === false && $able === false ){
      throw new TableException('Table doesn\'t support states ['.get_class($this).']');
    }
    
    return $able;
  }
  
  /**
   * {@inheritDoc}
    */
  public function publishingAble($return=true)
  {
    $able = $this->hasField('published');
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support publishing ['.get_class($this).']');
    }
    return $able;
  }
  
  /**
   * {@inheritDoc}
    */
  public function orderingAble($return=true)
  {
    $able = $this->hasField('ordering');
    
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support ordering ['.get_class($this).']');
    }
    
    return $able;
  }
  
  /**
   * {@inheritDoc}
    */
  public function versionAble($return=true)
  {  
    $able = $this->hasField('version');
    
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support version ['.get_class($this).']');
    }
    
    return $able;
  }
  
  /** 
   * Chec if the record has been modified.
   * 
   * @param   array     $oldValues  Key/Value pairs of old property values 
   * @return   bool      True for a new record or if no modifications were found.
   */
  protected function hasBeenModified(array $oldValues)
  {
    if ( intval($this->id) === 0 || empty($oldValues) ){
      return true;
    }
    
    $props = $this->getProperties();
    
    foreach($props as $k => $v){
      if ( isset($oldValues[$k]) && $oldValues[$k] == $v ){
        unset($props[$k]);
        continue;
      }
    }
    
    return ( !empty($props) > 0 ); 
  }
  
  /** 
   * Perform needed checkups 
   * 
   * @param   bool  $beenModified Has the record been modified ?
   * @return   bool  True if the record was modified and must be stored.
   */
  protected function check()
  {
    if ( $this->slugAble() ){
      if ( !$this->checkTitleUnique($this->id, $this->title) ){
        return false;
      }
      
      if ( !$this->checkSlugUnique($this->id, $this->slug) ){
        return false;
      }
    }
    
    if ( $this->orderingAble() && intval($this->ordering) === 0 ){
      $conditions = $this->getReorderConditions();
      $this->ordering = $this->getNextOrder($conditions);
    }
    
    if ( $this->publishingAble() ){
      $this->published = (int)$this->published;
    }
    
    if ( $this->statesAble() ){
      $user = $this->db->Uid;
      
      if ( empty($this->id) ){
        $this->created    = $this->db->now; 
        $this->created_by = $this->db->Uid; 
      }
      else {
        $this->created     = null; 
        $this->created_by  = null;
        
        $this->modified    = $this->db->now; 
        $this->modified_by = $this->db->Uid; 
      }
      
      if ( empty($this->modified) ){
        $this->modified = $this->db->nullDate;
      }
      
      if ( empty($this->deleted) ){
        $this->deleted = $this->db->nullDate;
      }
    }
    
    if ( $this->versionAble() ){
      $this->version++;
    }
    
    return true;
  }
  
  /** 
   * Store the record
   * 
   * @param   bool  $updateNulls    True to update properties that are null
   * @return   bool  True if the record was successfully stored.
   */
  protected function store($updateNulls=false)
  {
    $k = $this->tbl_key;
    
    $ret = false;
    
    if ( $this->$k ){
      $ret = $this->db->updateObject($this->tbl, $this, $this->tbl_key, $updateNulls);
    }
    else {
      $ret = $this->db->insertObject($this->tbl, $this, $this->tbl_key);
    }
    
    if ( $ret ){
      if ( $this->orderingAble() ){
        $this->reorder( $this->getReorderConditions() );
      }
      
      return true;
    }
    
    return false;
  }
  
  /** 
   * Get the object fields from database table columns.
   * 
   * @return   array       The list of table properties.
   */
  protected function getFields()
  {
    static $cache;

    if ( !isset($cache) ){
      $cache = [];
    }
    
    if ( !isset($cache[$this->tbl]) ){
      $fields = $this->db->getTableColumns($this->tbl, false);
      
      if ( empty($fields) ){
        throw new TableException('Table columns not found');
      }
      
      $cache[$this->tbl] = $fields;
    }
    
    return $cache[$this->tbl];
  }
  
  /**
   * Set data type for known fields.
   * 
   * @return   The typed value.
   */
  protected function setFieldsTypeByName()
  {
    foreach($this->getProperties() as $k => $v){
      switch($k){
        case 'id':
        case 'id_category':
        case 'parent_id':
        case 'version':
        case 'ordering':
        case 'created_by':
        case 'modified_by':
        case 'deleted_by':
          $v = (int)$v; 
          break;
        
        case 'created':
        case 'modified':
        case 'deleted':
          $v = $this->db->nullDate === $v ? '' : $v; 
          break;
        
        case 'published': 
          $v = (bool)$v;
          break;
      }
      
      $this->{$k} = $v;
    }  
  }  
  
  
  /** @deprecated */
  public function move($delta, $where='')
  {
    $this->orderingAble(false);
    
    if ( empty($delta) ){
      return true;
    }

    $k = $this->tbl_key;
    
    $query = $this->db->getQuery(true);
    
    $query->select($this->tbl_key . ', ordering');
    $query->from($this->tbl);
    
    if ( $delta < 0 ){
      $query->where('ordering < ' . (int) $this->ordering);
      $query->order('ordering DESC');
    }
    elseif ( $delta > 0 ){
      $query->where('ordering > ' . (int) $this->ordering);
      $query->order('ordering ASC');
    }

    if ( $where ){
      $query->where($where);
    }

    $this->db->setQuery($query, 0, 1);
    $row = $this->db->loadObject();
    
    if ( !empty($row) ){
      $query = $this->db->getQuery(true);
      $query->update($this->tbl);
      $query->set('ordering = ' . (int) $row->ordering);
      $query->where($this->tbl_key . ' = ' . $this->db->q($this->$k));
      $this->db->setQuery($query);
      $this->db->execute();
      
      $query = $this->db->getQuery(true);
      $query->update($this->tbl);
      $query->set('ordering = ' . (int) $this->ordering);
      $query->where($this->tbl_key . ' = ' . $this->db->q($row->$k));
      $this->db->setQuery($query);
      $this->db->execute();

      $this->ordering = $row->ordering;
    }
    else {
      $query = $this->db->getQuery(true);
      $query->update($this->tbl);
      $query->set('ordering = ' . (int) $this->ordering);
      $query->where($this->tbl_key . ' = ' . $this->db->q($this->$k));
      $this->db->setQuery($query);
      $this->db->execute();
    }
    
    return true;
  }
}
