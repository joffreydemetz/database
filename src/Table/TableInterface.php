<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Database\Table;

use JDZ\Database\DatabaseInterface;

/**
 * Table interface
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface TableInterface
{
  /**
   * Return the database object
   * 
   * @return  DatabaseInterface   The database instance
   */
  public function getDb();
  
  /**
   * Return the table short name
   * 
   * @return  string   The table short name
   */
  public function getTblName();
  
  /**
   * Return the table name
   * 
   * @return  string   The table name
   */
  public function getTbl();
  
  /**
   * Return the table promary key
   * 
   * @return  string   The primary key
   */
  public function getTblKey();
  
  /**
   * Set the object properties based on a named array/hash.
   *
   * @param   mixed  $properties  Either an associative array or another object.
   * @return   boolean
   */
  public function setProperties($properties);
  
  /**
   * Returns an associative array of object properties
   *
   * @return  array
   */
  public function getProperties();
  
  /**
   * Sets a property of the object
   * 
   * @param   string  $field  The name of the property
   * @param   mixed   $value  The value of the property to set
   * @return  void
   */
  public function set($field, $value=null);
  
  /**
   * Returns a property of the object or the default value if the property is not set
   * 
   * @param   string  $field     The name of the property
   * @param   mixed   $default   The default value
   * @return  mixed   The value of the property
   */
  public function get($field, $default=null);

  /**
   * Add an error message
   *
   * @param   mixed  $error  Error message or exception instance
   * @return  void
   */
  public function setError($error);
  
  /**
   * Return all errors, if any.
   * 
   * @return   array  Array of error messages or Exception instances.
   */
  public function getErrors();
  
  /**
   * Get an error message
   *
   * @param   int      $i         Option error index
   * @param   boolean  $toString  Indicates if Exception instances should return the error message or the exception object
   * @return  string   Error message
   */
  public function getError($i=null, $toString=true);
  
  /**
   * Return all errors, if any, as a unique string.
   * 
   * @param   string   $separator     The separator.
   * @return   string   String containing all the errors separated by the specified sequence.
   */
  public function getErrorsAsString($glue='<br />');
  
  /**
   * Has the current record been modified
   * 
   * @return  bool  True if modified
   */
  public function recordWasModified();
  
  /** 
   * Disable the row for checkbox selection
   * 
   * @param   int    $id  The item id
   * @return  bool   True if row should be disabled
   */
  public function rowIsDisabled($id);
  
  
  /**
   * Save associations
   * 
   * @return  string  $right_tbl   Right associated table short name
   * @return  int     $pk          Left association table record ID
   * @return  array   $right_items Right association table record IDs
   * @return  bool    $clear       True to clear previous records
   * @return  bool    $order       True if the associated elements should be ordered
   * @return  bool    True if successfull
   */
  public function saveAssociations($right, $pk, array $right_items, $clear=true, $order=false);
  
  /** 
   * Reset the current table object properties to their default value (null)
   * 
   * @return   void
   */
  public function reset();
  
  /** 
   * Load an item by slug name
   * 
   * @param   array|int   $keys    The item id or an array of conditions
   * @return   bool        $reset   True to reset the table object before loading the new item.
   * @return   bool        $clean   True to clean fields by type
   * @return   bool        True if item was loaded successfully.
   */
  public function load($keys=null, $reset=true, $clean=true);
  
  /** 
   * Load an item by slug name
   * 
   * @param   string  $slug   The item slug
   * @return   bool    True if found.
   * @see     load()
   */
  public function loadBySlug($slug);
  
  /** 
   * Load category siblings
   * 
   * @param   int     $id_category   The category ID
   * @return   array   List of sibling IDs
   */
  public function getByCategory($id_category);
  
  /** 
   * Save/update the record.
   * 
   * @param   array     $src        Key/Value pairs
   * @param   array     $ignore     Key/Value pairs of properties to ignore
   * @return   bool      True if successfully saved or no modifications were found.
   */
  public function save(array $src, array $ignore);
  
  /** 
   * Bind data to the table object.
   * 
   * @param   array     $src        Key/Value pairs
   * @param   array     $ignore     Key/Value pairs of properties to ignore
   * @param   array     $oldValues  Key/Value pairs of old property values 
   *                                used to check if record was really modified.
   * @return   bool      True if binding ws successfull.
   */
  public function bind(array $src, array $ignore=[], array &$oldValues=[]);
  
  /** 
   * Delete a record
   * 
   * If the table is statesAble, the record will not be removed from database.
   * 
   * @param   int|null    $pk     The record PrimaryKey value 
   *                              current loaded id will be used if not set.
   * @return   bool        True on success.
   */
  public function delete($pk=null);
  
  /** 
   * Untrash a record
   * 
   * @param   int|null    $pk     The record PrimaryKey value 
   *                              current loaded id will be used if not set.
   * @return   bool        True on success.
   */
  public function untrash($pk=null);
  
  /** 
   * Shred a record
   * 
   * When a statesAble table record should be remove from database.
   * 
   * @param   int|null    $pk     The record PrimaryKey value current loaded id will be used if not set
   * @return   bool        True on success.
   */
  public function shred($pk=null);
  
  /** 
   * Return to previous version
   * 
   * @param   int     $pk             The record PrimaryKey value
   * @param   array   $data           The previous version data
   * @return   bool    True on success
   */
  public function revert($pk, array $data);
  
  /** 
   * Publish/unpublish a record
   * 
   * @param   int|null    $pk     The record PrimaryKey value current loaded id will be used if not set
   * @param   int         $state  0 to unpublish, 1 to publish
   * @return   bool        True on success.
   */
  public function publish($pk=null, $state=1);
  
  /** 
   * Change ordering value of a record
   * 
   * @param   int|null    $pk        The record PrimaryKey value current loaded id will be used if not set 
   * @param   int         $to        The ordering value to set
   * @param   int         $minOrder  Min order value (to reorder only displayed records)
   * @param   int         $maxOrder  Max order value (to reorder only displayed records)
   * @return   bool       True on success
   */
  public function changeorder($pk, $to, $minOrder=0, $maxOrder=0);
  
  /** 
   * Record records meeting specified conditions
   * 
   * @param   array|string    $where     A valid query where clause
   * @return   bool            True on success.
   */
  public function reorder($where='');
  
  /** 
   * Get the list of trashed items meeting specified conditions
   * 
   * @param   array    $conditions     The filter conditions
   * @return   bool     True on success.
   */
  public function trashedItems(array $conditions=[]);
  
  /** 
   * Get the next ordering value
   * 
   * @param  array|string    $where     A valid query where clause
   * @return int             The next table ordering value.
   */
  public function getNextOrder($where = '');
  
  /** 
   * Reorder conditions
   * 
   * @return array    $conditions     The filter conditions
   */
  public function getReorderConditions();
  
  /** 
   * Check for more recent versions 
   * 
   * @return int  The previous version record ID (in table::version)
   */
  public function getPreviousVersion();
  
  /** 
   * Check if a title is unique according to specified conditions.
   * 
   * @param  int     $id             The loaded object id (to exclude it from results) 
   * @param  string  $title          The title to check
   * @param  array   $conditions     The filter conditions
   * @return string  The default ordering clause
   */
  public function checkTitleUnique($id, $title, array $conditions=[]);
  
  /** 
   * Check if a slug is unique according to specified conditions.
   * 
   * @param  int       $id             The loaded object id (to exclude it from results) 
   * @param  string    $slug           The slug to check
   * @param  array     $conditions     The filter conditions
   * @return string    The default ordering clause
   */
  public function checkSlugUnique($id, $slug, array $conditions=[]);
  
  /** 
   * Check if table includes specified field(s)
   * 
   * @param   array|string  An array or a string with the field name
   * @return   bool         True if the field is found
   */
  public function hasField($fields);
  
  /** 
   * Does table include a title and a slug 
   * 
   * @param  bool  $return   False to throw an exception if the functionnality
   *                         is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function slugAble($return=true);
  
  /** 
   * Supports categories
   * 
   * @param  bool  $return   False to throw an exception if the functionnality
   *                         is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function categorizeAble($return=true);
  
  /** 
   * Does table support dates & user states
   * 
   * @param   bool  $return   False to throw an exception if the functionnality
   *                          is not available for the current table object.
   * @return   bool  True if the functionnality is supported.
   */
  public function statesAble($return=true);
  
  /** 
   * Does table support publishing
   * 
   * @param   bool  $return   False to throw an exception if the functionnality
   *                          is not available for the current table object.
   * @return   bool  True if the functionnality is supported.
   */
  public function publishingAble($return=true);
  
  /** 
   * Does table support ordering
   * 
   * @param   bool  $return   False to throw an exception if the functionnality 
   *                          is not available for the current table object.
   * @return   bool  True if the functionnality is supported.
   */
  public function orderingAble($return=true);
  
  /** 
   * Does table use version
   * 
   * @param   bool  $return   False to throw an exception if the functionnality 
   *                          is not available for the current table object.
   * @return   bool  True if the functionnality is supported.
   */
  public function versionAble($return=true);
}
