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
use JDZ\Utilities\Date as DateObject;
use RuntimeException;
use Exception;

/**
 * Table connector class
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Table implements TableInterface
{
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
   * Table Row data instance
   * 
   * @var    TableRow
   */
  protected $row;
  
  /**
   * Table primary key
   * 
   * @var    string
   */
  protected $tbl_key = 'id';
  
  /**
   * Has been modified
   * 
   * @var    bool
   */
  protected $hasBeenModified;
  
  /**
   * Reorder conditions
   * 
   * @var    array
   */
  protected $reorderConditions = [];
  
  /** 
   * An array of error messages or Exception objects
   * 
   * @var   array 
   */ 
  protected $errors = [];
  
  // public static function create($name)
  // {
    // $Class = get_called_class();
    // return new $Class();
  // }
  
  /**
   * Magic method to get a row field value
   *
   * @param  string  $name  Field name
   * @return mixed   The value or null if the property is not set
   */
  public function __get($name)
  {
    debugMe('Still using '.$this->tbl.'::__get for '.$name);
    return $this->row->get($name);
  }
  
  /**
   * Magic method to set a row field value
   *
   * @param  string  $name  Field name
   * @return mixed   The value or null if the property is not set
   */
  public function __set($name, $value)
  {
    debugMe('Still using '.$this->tbl.'::__set for '.$name);
    return $this->row->set($name, $value);
  }
  
  public function setDb(DatabaseInterface $db)
  {
    $this->db = $db;
    return $this;
  }
  
  public function setTblName(string $tbl_name)
  {
    $this->tbl_name = $tbl_name;
    return $this;
  }
  
  public function setTblKey(string $tbl_key)
  {
    $this->tbl_key = $tbl_key;
    return $this;
  }
  
  public function setProperties($properties)
  {
    $this->row->setProperties($properties);
    return $this;
  }
  
  public function getDb()
  {
    return $this->db;
  }
  
  public function getTblName()
  {
    return $this->tbl_name;
  }
  
  public function getTbl()
  {
    return $this->tbl;
  }
  
  public function getTblKey()
  {
    return $this->tbl_key;
  }
  
  public function getRow()
  {
    return $this->row;
  }
  
  public function getProperties($object=false)
  {
    return $this->row->all($object);
  }
  
  public function getPreviousVersion()
  {
    $this->versionAble(false);
    
    $vTable = Table('version');
    $db = $vTable->getDb();
    
    $db->setQuery(
      $db->getQuery(true)
        ->select('id')
        ->from($vTable->getTbl())
        ->where($db->qn('table_name').' = '.$db->q($this->tbl))
        ->where($db->qn('row_id').' = '.(int)$this->row->get($this->tbl_key))
        ->order($db->qn('versionNum').' DESC')
    );
    $versionNum = (int)$db->loadResult();
    return $versionNum;
  }
  
  public function getByCategory($id_category)
  {
    $this->categorizeAble(false);
    
    $this->db->setQuery(
      $this->db->getQuery(true)
        ->select('id')
        ->from($this->tbl)
        ->where('id_category='.(int)$id_category)
    );
    
    $items = (array)$this->db->loadColumn();
    
    return $items;
  }
  
  public function hasField($fields)
  {
    if ( !is_array($fields) ){
      $fields = [ $fields ];
    }
    
    foreach($fields as $field){
      if ( !in_array($field, $this->getFieldNames()) ){
        return false;
      }
    }
    
    return true;
  }
  
  public function hasChildren($pk=null, $field='parent_id')
  {
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    return ( $this->countChildren($pk, $field) > 0 );
  }
  
  public function countChildren($pk=null, $field='parent_id')
  {
    if ( !$this->hasField($field) ){
      throw new TableException('Table doesn\'t support parenting ['.$this->tbl.']');
    }
    
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    $this->db->setQuery(
      $this->db->getQuery(true)
        // ->select('COUNT(DISTINCT('.$this->db->qn($this->tbl_key).'))')
        ->select('COUNT('.$this->tbl_key.')')
        ->from($this->tbl)
        ->where($field.'='.$this->db->q($pk))
    );
    
    $count = (int)$this->db->loadResult();
    
    return $count;
  }
  
  public function recordWasModified()
  {
    return $this->hasBeenModified;
  }
  
  public function rowIsDisabled($id)
  {
    return false;
  }
  
  public function trashedItems(array $conditions=[])
  {
    $conditions[] = $this->db->qn('deleted').' != '.$this->db->q($this->db->getNullDate());
    // $conditions[] = $this->db->qn('deleted_by').' <> 0';
    
    $query = $this->db->getQuery(true);
    $query->select($this->db->qn('id'));
    $query->from($this->db->qn($this->tbl));
    $query->where($conditions);
    
    $this->db->setQuery($query);
    return (array)$this->db->loadColumn();
  }
  
  public function isDuplicate(array $properties, $id=0)
  {
    $where = [];
    foreach($properties as $key => $value){
      $where[] = $this->db->qn($key).'='.$this->db->q($value);
    }
    
    $query = $this->db->getQuery(true)
      ->select('id')
      ->from($this->tbl)
      ->where($where);
      
    if ( $id ){
      $query->where('id <> '.(int)$id);
    }
    
    $this->db->setQuery($query);
    
    $id = (int) $this->db->loadResult();
    
    return $id;
  }
  
  
  public function init()
  {
    $this->tbl = '#__'.$this->tbl_name;
    
    $this->row = TableRow::create();
    
    foreach($this->getFieldNames() as $field){
      $this->row->set($field, null);
    }
    
    return $this;
  }
  
  public function reset()
  {
    $this->row->set($this->tbl_key, 0);
    
    foreach($this->getFields() as $fieldName => $fieldInfos){
      if ( $fieldName != $this->tbl_key ){
        $this->row->set($fieldName, $fieldInfos->Default);
      }
    }
  }
  
  public function load($keys=null, $reset=true, $clean=true)
  {
    if ( empty($keys) ){
      $keyValue = $this->row->get($this->tbl_key);

      if ( empty($keyValue) ){
        return true;
      }
      
      $keys = [ $this->tbl_key => $keyValue ];
    }
    elseif ( !is_array($keys) ){
      $keys = [ $this->tbl_key => $keys ];
    }
    
    if ( $reset ){
      $this->reset();
    }
    
    // $fields = array_keys($this->row->all());
    $fields = $this->getFieldNames();
    
    $query = $this->db->getQuery(true)
      // ->select('*')
      ->select(implode(',', $fields))
      ->from($this->tbl);
    
    foreach($keys as $field => $value){
      if ( !in_array($field, $fields) ){
        throw new TableException(sprintf('Field %s doesn\'t exist in %s', $field, get_class($this)));
      }
      $query->where($this->db->qn($field).'='.$this->db->q($value));
    }
    
    $this->db->setQuery($query);
    $row = $this->db->loadAssoc();
    
    if ( empty($row) ){
      $this->setError(DatabaseHelper::getTranslation('EMPTY_ROW').' in '.$this->tbl);
      return false;
    }
    
    if ( $this->bind($row) ){
      // if ( $clean ){
        $this->setFieldsTypeByName();
      // }
      return true;
    }
    
    return false;
  }
  
  public function loadBySlug($slug)
  {
    $this->slugAble(false);
    return $this->load(['slug'=>$slug]);
  }
  
  
  public function save(array $src, array $ignore=[])
  {
    $oldRow = clone $this->row;
    
    if ( $pk = $this->row->get($this->tbl_key) ){
      $src[$this->tbl_key] = $pk;
    }
    
    $this->bind($src, $ignore);
    
    if ( !$this->hasBeenModified($oldRow) ){
      // $this->setError(DatabaseHelper::getTranslation('RECORD_UNCHANGED'));
      return true;
    }
    
    if ( !$this->check() ){
      return false;
    }
    
    if ( !$this->store() ){
      return false;
    }
    
    return true;
  }
  
  protected function bind(array $src, array $ignore=[])
  {
    foreach($this->getFieldNames() as $fieldName){
      if ( in_array($fieldName, $ignore) || !isset($src[$fieldName]) ){
        continue;
      }
      
      $this->row->set($fieldName, $src[$fieldName]);
    }
    
    $this->setFieldsTypeByName();
    
    return true;
  }
  
  /** 
   * Perform needed checkups 
   * 
   * @return bool  True if the record was modified and must be stored.
   */
  protected function check()
  {
    if ( $this->slugAble() ){
      if ( !$this->checkTitleUnique($this->row->get('id'), $this->row->get('title')) ){
        return false;
      }
      
      if ( !$this->checkSlugUnique($this->row->get('id'), $this->row->get('slug')) ){
        return false;
      }
    }
    
    if ( $this->orderingAble() && intval($this->row->get('ordering')) === 0 ){
      $conditions = $this->getReorderConditions();
      $this->row->set('ordering', $this->getNextOrder($conditions));
    }
    
    // if ( $this->publishingAble() ){
      // $this->row->set('published', (int)$this->row->get('published')); 
    // }
    
    if ( $this->statesAble() ){
      $userId   = $this->db->getUid();
      $nullDate = $this->db->getNullDate();
      $nowDate  = DateObject::getInstance()->format($this->db->getDateFormat());
      
      if ( !$this->row->get($this->tbl_key) ){
        $this->row->set('created', $nowDate);
        $this->row->set('created_by', $userId); 
        
        // $this->row->set('modified', $nullDate);
        // $this->row->set('deleted',  $nullDate);
      }
      else {
        $this->row->set('modified', $nowDate);
        $this->row->set('modified_by', $userId); 
      }
      
      // if ( !$this->row->get('modified') ){
        // $this->row->set('modified', $nullDate);
      // }
      
      // if ( !$this->row->get('deleted') ){
        // $this->row->set('deleted', $nullDate);
      // }
    }
    
    if ( $this->versionAble() ){
      $version = (int)$this->row->get('version');
      $this->row->set('version', ++$version);
    }
    
    return true;
  }
  
  /** 
   * Store the record
   * 
   * @param  bool  $updateNulls    True to update properties that are null
   * @return bool  True if the record was successfully stored.
   */
  protected function store($updateNulls=false)
  {
    $row = TableRow::create();
    
    foreach($this->getFields() as $fieldName => $fieldInfos){
      $fieldValue = $this->row->get($fieldName);
      
      switch($fieldInfos->Type){
        case 'bigint':
        case 'mediumint':
        case 'smallint':
        case 'tinyint':
          $fieldValue = (int)$fieldValue;
          break;
        
        case 'datetime':
        case 'timestamp':
          if ( !$fieldValue ){
            $fieldValue = $this->db->getNullDate(true);
          }
          break;
        
        case 'date':
          if ( !$fieldValue ){
            $fieldValue = $this->db->getNullDate(false);
          }
          break;
        
        case 'time':
          if ( !$fieldValue ){
            $fieldValue = '00:00:00';
          }
          break;
      }
      
      $row->set($fieldName, $fieldValue);
    }
    
    // debugMe($row->all(), 'STORE')->end();
    
    if ( $row->get($this->tbl_key) ){
      $ret = $this->db->updateObject($this->tbl, $row, $this->tbl_key, $updateNulls);
    }
    else {
      $ret = $this->db->insertObject($this->tbl, $row, $this->tbl_key);
      $this->row->set($this->tbl_key, (int)$row->get($this->tbl_key));      
    }
    
    if ( $ret ){
      if ( $this->orderingAble() ){
        $this->reorder( $this->getReorderConditions() );
      }
      return true;
    }
    
    return false;
  }
  
  
  public function delete($pk=null)
  {
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    $this->load($pk);
    
    if ( $this->statesAble() ){
      if ( $this->row->get('deleted') && $this->row->get('deleted') !== $this->db->getNullDate() ){
        $this->setError(DatabaseHelper::getTranslation('ITEM_ALREADY_DELETED'));
        return false;
      }
      
      if ( $this->row->get('modified') && $this->row->get('modified') === $this->db->getNullDate() ){
        $this->row->set('modified', DateObject::getInstance()->format($this->db->getDateFormat()));
        $this->row->set('modified_by', $this->db->getUid());
      }
      
      $this->row->set('deleted', DateObject::getInstance()->format($this->db->getDateFormat()));
      $this->row->set('deleted_by', $this->db->getUid());
      
      if ( $this->publishingAble() ){
        $this->row->set('published', 0); 
      }
      
      return ( $this->store() );
    }
    
    if ( $this->orderingAble() ){
      $reorderConditions = $this->getReorderConditions();
    }
    else {
      $reorderConditions = [];
    }
    
    $this->db->setQuery(
      $this->db->getQuery(true)
        ->delete()
        ->from($this->tbl)
        ->where($this->tbl_key.' = '.$pk)
    );
    $this->db->execute();
    
    // debugMe($this->orderingAble());
    // debugMe($this->getReorderConditions())->end();
    
    if ( $this->orderingAble() ){
      $this->reorder($reorderConditions);
    }
    
    return true;
  }
  
  public function untrash($pk=null)
  {
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    if ( $this->statesAble() ){
      $this->load($pk);
      
      if ( $this->deleted === $this->db->getNullDate() ){
        return false;
      }
      
      $this->deleted    = $this->db->getNullDate();
      $this->deleted_by = 0;
      
      $this->store();
    }
    
    return true;
  }
  
  public function shred($pk=null)
  {
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    if ( $this->statesAble() ){
      $this->load($pk);
      
      $this->db->setQuery(
        $this->db->getQuery(true)
          ->delete()
          ->from($this->tbl)
          ->where($this->tbl_key.' = '.$this->db->q($pk))
      );
      $this->db->execute();
      
      if ( $this->orderingAble() ){
        $this->reorder($this->getReorderConditions());
      }
    }
    
    return true;
  }
  
  public function revert($pk, array $data)
  {
    if ( !$this->load($pk, true, false) ){
      return false;
    }
    
    foreach($data as $key => $value){
      $this->row->set($key, $value);
    }
    
    $this->row->set($this->tbl_key, (int)$pk);
    
    return $this->store();
  }
  
  public function publish($pk=null, $state=1)
  {
    $this->publishingAble(false);
    
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    $state = (int)$state;
    
    if ( $this->statesAble() ){
      if ( $this->deleted !== '' && $this->deleted !== $this->db->getNullDate() ){
        $this->setError(DatabaseHelper::getTranslation('DELETED_SO_CANNOT_PUBLISH'));
        return false;
      }
    }
    
    $query = $this->db->getQuery(true)
      ->update($this->tbl)
      ->set('published='.(int)$state)
      ->where($this->tbl_key.' = '.$this->db->q($pk));
    
    if ( $this->statesAble() ){
      $query
        ->set('modified='.$this->db->q(DateObject::getInstance()->format($this->db->getDateFormat())))
        ->set('modified_by='.$this->db->q($this->db->getUid()));
    }
    
    $this->db->setQuery($query);
    $this->db->execute();
    
    if ( (int)$this->row->get($this->tbl_key) === (int)$pk ){
      $this->published = $state;
    }
    
    return true;
  }
  
  
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
      $this->setError(DatabaseHelper::getTranslation('TITLE_EXISTS'));
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
      $this->setError(DatabaseHelper::getTranslation('SLUG_EXISTS'));
      return false;
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
      throw new TableException('Table doesn\'t support slug ['.$this->tbl.']');
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
      throw new TableException('Table doesn\'t support categories ['.$this->tbl.']');
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
        throw new TableException('Table should support states but all 6 fields are not available ['.$this->tbl.']');
      }      
    }
    
    if ( $return === false && $able === false ){
      throw new TableException('Table doesn\'t support states ['.$this->tbl.']');
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
      throw new TableException('Table doesn\'t support publishing ['.$this->tbl.']');
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
      throw new TableException('Table doesn\'t support versioning ['.$this->tbl.']');
    }
    
    return $able;
  }
  
  /** 
   * Chec if the record has been modified.
   * 
   * @param  array     $oldValues  Key/Value pairs of old property values 
   * @return bool      True for a new record or if no modifications were found.
   */
  protected function hasBeenModified(TableRow $oldRow)
  {
    $diff = $this->row->diff($oldRow);
    $this->hasBeenModified = ( count($diff) > 0 );
    return $this->hasBeenModified; 
  }
  
  /** 
   * Get the object fields from database table columns
   * 
   * @return array       The list of table properties
   */
  protected function getFields()
  {
    $fields = $this->db->getTableColumns($this->tbl);
    
    if ( empty($fields) ){
      throw new TableException('Table columns not found');
    }
    
    return $fields;
  }
  
  /** 
   * Get the list of available field names
   * 
   * @return array  The list of field names
   */
  protected function getFieldNames()
  {
    return array_keys($this->getFields());
  }
  
  /**
   * Set data type for known fields
   * 
   * @return The typed value
   */
  protected function setFieldsTypeByName()
  {
    foreach($this->getFields() as $fieldName => $fieldInfos){
      $v = $this->row->get($fieldName);
      
      switch($fieldInfos->Type){
        case 'bigint':
        case 'mediumint':
        case 'smallint':
        case 'tinyint':
          $this->row->set($fieldName, (int)$v); 
          break;
        
        case 'datetime':
        case 'timestamp':
          if ( $v === $this->db->getNullDate(true) ){
            $this->row->set($fieldName, ''); 
          }
          break;
        
        case 'date':
          if ( $v === $this->db->getNullDate(false) ){
            $this->row->set($fieldName, ''); 
          }
          break;
        
        case 'time':
          if ( $v === '00:00:00' ){
            $this->row->set($fieldName, ''); 
          }
          break;
      }
      
      if ( $fieldName === 'published' ){
        $this->row->set($fieldName, (bool)$v); 
      }
    }
  }  
  
  /**
   * Set the real pk request value
   * 
   * If no pk was requested it check if a row is currently loaded
   * 
   * @return bool
   */
  protected function realPkSelection(&$pk)
  {
    if ( null === $pk ){
      $pk = $this->row->get($this->tbl_key);
    }
    
    if ( null === $pk ){
      $this->setError( DatabaseHelper::getTranslation('NO_ITEM_SELECTED') );
      return false;
    }
    
    return true;
  }
  
  /*==========
   ASSOCIATION UTILITIES
   ===========*/
  
  public function getAssociations($pk, $assocTbl, $callback=null, $ordered=true)
  {
    $associations = [];
    
    if ( $this->realPkSelection($pk) ){
      if ( $this->load($pk) ){
        $fkParts = explode('_', $this->tbl_name);
        $fk = array_pop($fkParts);
        
        $query = $this->db->getQuery(true)
          ->select('*')
          ->from($this->tbl.'_'.$assocTbl)
          ->where('id_'.$fk.' = '.$pk);
        
        if ( $ordered ){
          if ( is_bool($ordered) ){
            $query->order('ordering ASC');
          }
          else {
            $query->order($ordered);
          }
        }
        
        $this->db->setQuery($query);
        
        $associations = $this->db->loadObjectList();
        
        if ( is_callable($callback) ){
          foreach($associations as $row){
            $callback($row, $this);
          }
        }
      }
    }
    
    $this->errors = [];
    return $associations;
  }
  
  public function saveAssociations($right, $left_id, array $right_items, $clear=true, $ordered=false)
  {
    if ( $clear ){
      // remove previous associations
      $this->db->setQuery(
        $this->db->getQuery(true)
          ->delete($this->tbl.'_'.$right)
          ->where('id_'.$this->tbl_name.' = '.$left_id)
      );
      $this->db->execute();
    }
    
    if ( !empty($right_items) ){
      if ( $ordered && !$clear ){
        // get the last set ordering value
        $this->db->setQuery(
          $this->db->getQuery(true)
            ->select('MAX(ordering)')
            ->from($this->tbl.'_'.$right)
            ->where('id_'.$this->tbl_name.'='.$left_id)
        );
        $ordering = (int)$this->db->loadResult();
      }
      else {
        $ordering = 0;
      }
      
      // add the associated items
      $query = $this->db->getQuery(true)
        ->insert($this->tbl.'_'.$right);
      
      if ( $ordered ){
        $query->columns('id_'.$this->tbl_name.', id_'.$right.', ordering');
        foreach($right_items as $right_id){
          $query->values($left_id.', '.(int)$right_id.', '.++$ordering);
        }
      }
      else {
        $query->columns('id_'.$this->tbl_name.', id_'.$right);
        foreach($right_items as $right_id){
          $query->values($left_id.', '.(int)$right_id);
        }
      }
      
      $this->db->setQuery($query);
      $this->db->execute();
    }
  }
  
  /*==========
   ERROR UTILITIES
   ===========*/
  
  public function setError($error)
  {
    if ( !($error instanceof Exception) && is_string($error) ){
      $error = new Exception($error);
    }
    
    array_push($this->errors, $error);
    
    return $this;
  }
  
  public function clearErrors()
  {
    $this->errors = [];
    return $this;
  }
  
  public function getErrors()
  {
    return $this->errors;
  }
  
  public function getError($i=null, $toString=true)
  {
    if ( $i === null ){
      $error = end($this->errors);
    }
    else {
      if ( !array_key_exists($i, $this->errors) ){
        return false;
      }
      
      $error = $this->errors[$i];
    }
    
    if ( $error instanceof Exception ){
      return $error->getMessage();
    }
    
    return $error;
  }
  
  public function getErrorsAsString($glue='<br />')
  {
    $errors = $this->errors;
    
    foreach($errors as &$error){
      $error = $error->getMessage();
    }
    
    return implode($glue, $errors);
  }
  
  /*==========
   ORDERING UTILITIES
   ===========*/
  
  public function setReorderConditions(array $conditions)
  {
    $this->reorderConditions = $conditions;
    return $this;
  }
  
  public function getNextOrder($where='')
  {
    $this->orderingAble(false);
    
    $query = $this->db->getQuery(true)
      ->select('MAX(ordering)')
      ->from($this->tbl);
    
    if ( $where ){
      $query->where($where);
    }
    
    $this->db->setQuery($query);
    $max = (int) $this->db->loadResult();
    
    return ($max + 1);
  }

  public function getReorderConditions()
  {
    $conditions = [];
    
    if ( $this->categorizeAble() ){
      $conditions[] = 'id_category='.$this->db->q($this->row->get('id_category'));
    }
    
    foreach($this->reorderConditions as $field){
      $conditions[] = $field.'='.$this->db->q($this->row->get($field));
    }
    
    return $conditions;
  }
  
  public function changeorder($pk, $to, $minOrder=1, $maxOrder=0)
  {
    $this->orderingAble(false);
    
    if ( !$this->realPkSelection($pk) ){
      return false;
    }
    
    if ( !$this->load($pk) ){
      return false;
    }
    
    $k         = $this->tbl_key;
    $to        = (int)$to;
    $from      = (int)$this->row->get('ordering');
    $direction = $from > $to ? 'down' : 'up';
    
    if ( $from === $to ){
      return true;
    }
    
    $where = $this->getReorderConditions();
    
    $where[] = $this->tbl_key.' != '.$pk;
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
    $this->db->setQuery(
      $this->db->getQuery(true)
        ->select($k.', ordering')
        ->from($this->tbl)
        ->where($whereBefore)
        ->order('ordering ASC')
    );
    $itemsBefore = $this->db->loadObjectList();
    
    // get items after the moved element
    $this->db->setQuery(
      $this->db->getQuery(true)
        ->select($k.', ordering')
        ->from($this->tbl)
        ->where($whereAfter)
        ->order('ordering ASC')
    );
    $itemsAfter = $this->db->loadObjectList();
    
    // reorder
    $i=$minOrder;
    
    foreach($itemsBefore as $row){
      $this->db->setQuery(
        $this->db->getQuery(true)
          ->update($this->tbl)
          ->set('ordering='.$i)
          ->where($k.'='.$row->{$k})
      );
      $this->db->execute();
      $i++;
    }
    
    // here is the reordered element
    $this->db->setQuery(
      $this->db->getQuery(true)
        ->update($this->tbl)
        ->set('ordering='.$i)
        ->where($k.'='.$pk)
    );
    $this->db->execute();
    $i++;
    
    foreach($itemsAfter as $row){
      $this->db->setQuery(
        $this->db->getQuery(true)
          ->update($this->tbl)
          ->set('ordering='.$i)
          ->where($k.' = '.$row->{$k})
      );
      $this->db->execute();
      $i++;
    }
    
    // set the new item order
    $this->row->set('ordering', $to);
    
    return true;
  }
  
  public function reorder($where='')
  {
    $this->orderingAble(false);
    
    $k = $this->tbl_key;
    
    $query = $this->db->getQuery(true)
      ->select($k.', ordering')
      ->from($this->tbl)
      ->where('ordering >= 0')
      ->order('ordering');
    
    if ( $where ){
      $query->where($where);
    }
    
    $this->db->setQuery($query);
    $rows = $this->db->loadObjectList();
    // debugMe($rows);
    
    $current = 1;
    foreach($rows as &$row){
      $row->ordering = (int)$row->ordering;
      
      if ( $row->ordering <> $current ){
        $row->ordering = $current;
        
        $this->db->setQuery(
          $this->db->getQuery(true)
            ->update($this->tbl)
            ->set('ordering='.$current)
            ->where($k.'='.$row->{$k})
        );
        $this->db->execute();
      }
      
      $current++;
    }
    // debugMe($rows)->end();
    
    return true;
  }
  
  public function orderingAble($return=true)
  {
    $able = $this->hasField('ordering');
    
    if ( $return === false && !$able ){
      throw new TableException('Table doesn\'t support ordering ['.$this->tbl.']');
    }
    
    return $able;
  }
}
