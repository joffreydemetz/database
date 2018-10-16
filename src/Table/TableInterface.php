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
   * Set the database instance
   *
   * @param  DatabaseInterface  $db  
   * @return $this
   */
  public function setDb(DatabaseInterface $db);
  
  /**
   * Set the Table name
   *
   * @param  string  $tbl_name  
   * @return $this
   */
  public function setTblName(string $tbl_name);
  
  /**
   * Set the Table primary key
   *
   * @param  string  $tbl_key  
   * @return $this
   */
  public function setTblKey(string $tbl_key);
  
  /**
   * Set the object properties based on a named array/hash.
   *
   * @param  mixed  $properties  Either an associative array or another object.
   * @return $this
   */
  public function setProperties($properties);
  
  /**
   * Return the database object
   * 
   * @return DatabaseInterface   The database instance
   */
  public function getDb();
  
  /**
   * Return the table short name
   * 
   * @return string   The table short name
   */
  public function getTblName();
  
  /**
   * Return the table name
   * 
   * @return string   The table name
   */
  public function getTbl();
  
  /**
   * Return the table primary key
   * 
   * @return string   The primary key
   */
  public function getTblKey();
  
  /**
   * Return the current table row data
   * 
   * @return TableRow
   */
  public function getRow();
  
  /**
   * Returns an associative array of the table row properties
   *
   * @param  bool  $object  True to return data a stdClass object
   * @return array
   */
  public function getProperties($object=false);
  
  /** 
   * Check for more recent versions 
   * 
   * @return int  The previous version record ID (in table::version)
   */
  public function getPreviousVersion();
  
  /** 
   * Load category siblings
   * 
   * @param  int     $id_category   The category ID
   * @return array   List of sibling IDs
   */
  public function getByCategory($id_category);
  
  /** 
   * Check if table includes specified field(s)
   * 
   * @param  array|string  $fields  An array or a string with the field name
   * @return bool  True if the field is found
   */
  public function hasField($fields);
  
  /** 
   * Check if table row has children
   * 
   * @param  int     $pk     The parent row ID
   * @param  string  $field  The parent id field name
   * @return bool  True if the field is found
   */
  public function hasChildren($pk=null, $field='parent_id');
  
  /** 
   * Count row children
   * 
   * @param  int     $pk     The parent row ID
   * @param  string  $field  The parent id field name
   * @return bool  True if the field is found
   */
  public function countChildren($pk=null, $field='parent_id');
  
  /**
   * Has the current record been modified
   * 
   * @return bool  True if modified
   */
  public function recordWasModified();
  
  /** 
   * Disable the row for checkbox selection
   * 
   * @param  int    $id  The item id
   * @return bool   True if row should be disabled
   */
  public function rowIsDisabled($id);
  
  /** 
   * Get the list of trashed items meeting specified conditions
   * 
   * @param  array    $conditions     The filter conditions
   * @return bool     True on success.
   */
  public function trashedItems(array $conditions=[]);
  
  public function isDuplicate(array $properties, $id=0);
  
  public function init();
  
  /** 
   * Reset the current table object properties to their default value (null)
   * 
   * @return void
   */
  public function reset();
  
  /** 
   * Load an item by slug name
   * 
   * @param  array|int   $keys    The item id or an array of conditions
   * @return bool        $reset   True to reset the table object before loading the new item.
   * @return bool        $clean   True to clean fields by type
   * @return bool        True if item was loaded successfully.
   */
  public function load($keys=null, $reset=true, $clean=true);
  
  /** 
   * Load an item by slug name
   * 
   * @param  string  $slug   The item slug
   * @return bool    True if found.
   * @see     load()
   */
  public function loadBySlug($slug);
  
  /** 
   * Save/update the record.
   * 
   * @param  array     $src        Key/Value pairs
   * @param  array     $ignore     Key/Value pairs of properties to ignore
   * @return bool      True if successfully saved or no modifications were found.
   */
  public function save(array $src, array $ignore);
  
  /** 
   * Delete a record
   * 
   * If the table is statesAble, the record will not be removed from database.
   * 
   * @param  int|null    $pk     The record PrimaryKey value 
   *                              current loaded id will be used if not set.
   * @return bool        True on success.
   */
  public function delete($pk=null);
  
  /** 
   * Untrash a record
   * 
   * @param  int|null    $pk     The record PrimaryKey value 
   *                              current loaded id will be used if not set.
   * @return bool        True on success.
   */
  public function untrash($pk=null);
  
  /** 
   * Shred a record
   * 
   * When a statesAble table record should be remove from database.
   * 
   * @param  int|null    $pk     The record PrimaryKey value current loaded id will be used if not set
   * @return bool        True on success.
   */
  public function shred($pk=null);
  
  /** 
   * Return to previous version
   * 
   * @param  int     $pk             The record PrimaryKey value
   * @param  array   $data           The previous version data
   * @return bool    True on success
   */
  public function revert($pk, array $data);
  
  /** 
   * Publish/unpublish a record
   * 
   * @param  int|null    $pk     The record PrimaryKey value current loaded id will be used if not set
   * @param  int         $state  0 to unpublish, 1 to publish
   * @return bool        True on success.
   */
  public function publish($pk=null, $state=1);
  
  
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
   * @param  bool  $return   False to throw an exception if the functionnality
   *                          is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function statesAble($return=true);
  
  /** 
   * Does table support publishing
   * 
   * @param  bool  $return   False to throw an exception if the functionnality
   *                          is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function publishingAble($return=true);
  
  /** 
   * Does table use version
   * 
   * @param  bool  $return   False to throw an exception if the functionnality 
   *                          is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function versionAble($return=true);
  
  /*==========
   ASSOCIATION UTILITIES
   ===========*/
  
  /**
   * Get associations
   * 
   * @return int            $pk        Left association table record ID
   * @return array          $assocTbl  Associated table short name
   * @return callable|null  $callback  True to clear previous records
   * @return bool           $ordered   True if the associated elements are ordered
   * @return array Associations
   */
  public function getAssociations($pk, $assocTbl, $callback=null, $ordered=true);
  
  /**
   * Save associations
   * 
   * @return string  $right_tbl   Right associated table short name
   * @return int     $pk          Left association table record ID
   * @return array   $right_items Right association table record IDs
   * @return bool    $clear       True to clear previous records
   * @return bool    $ordered     True if the associated elements should be ordered
   * @return bool    True if successfull
   */
  public function saveAssociations($right, $pk, array $right_items, $clear=true, $ordered=false);
  
  /*==========
   ERROR UTILITIES
   ===========*/
  
  /**
   * Add an error message
   *
   * @param  mixed  $error  Error message or exception instance
   * @return $this
   */
  public function setError($error);
  
  /**
   * Clear errors
   * 
   * @return $this
   */
  public function clearErrors();
  
  /**
   * Return all errors
   * 
   * @return array  Array of error messages
   */
  public function getErrors();
  
  /**
   * Get an error message
   *
   * @param  int      $i         Option error index
   * @param  boolean  $toString  Indicates if Exception instances should return the error message or the exception object
   * @return string   Error message
   */
  public function getError($i=null, $toString=true);
  
  /**
   * Return all errors, if any, as a unique string.
   * 
   * @param  string   $separator     The separator.
   * @return string   String containing all the errors separated by the specified sequence.
   */
  public function getErrorsAsString($glue='<br />');
  
  /*==========
   ORDERING UTILITIES
   ===========*/
  
  /** 
   * Get the next ordering value
   * 
   * @param  array|string    $where     A valid query where clause
   * @return int             The next table ordering value.
   */
  public function getNextOrder($where='');
  
  /** 
   * Reorder conditions
   * 
   * @return array    $conditions     The filter conditions
   */
  public function getReorderConditions();
  
  /** 
   * Change ordering value of a record
   * 
   * @param  int|null    $pk        The record PrimaryKey value current loaded id will be used if not set 
   * @param  int         $to        The ordering value to set
   * @param  int         $minOrder  Min order value (to reorder only displayed records)
   * @param  int         $maxOrder  Max order value (to reorder only displayed records)
   * @return bool       True on success
   */
  public function changeorder($pk, $to, $minOrder=0, $maxOrder=0);
  
  /** 
   * Record records meeting specified conditions
   * 
   * @param  array|string    $where     A valid query where clause
   * @return bool            True on success.
   */
  public function reorder($where='');
  
  /** 
   * Does table support ordering
   * 
   * @param  bool  $return   False to throw an exception if the functionnality 
   *                          is not available for the current table object.
   * @return bool  True if the functionnality is supported.
   */
  public function orderingAble($return=true);
}
