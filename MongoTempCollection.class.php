<?php
/**
 * Provides a means to store temporary data in a collection, more like MySQL temp tables.
 * Handles cleanup, and naming for you. No defaults unless you name them after it's created by specifying the defaults array.
 * Extends mongocollectionplus
 * @author Micah Stevens <micahstev@gmail.com>
 * @version 1.0 - initial release (2012-12-17)
 *
 * @package MongoPlusPHP
 */

class MongoTempCollection extends MongoCollectionPlus
{
	var $site; // site object
	var $default;
	static $TempCollections = array();
	
	/**
	 * constructor generates collection name starting with temp_
	 * 
	 * @param object $site Site object as per core requirements
	 * 
	 * @return null    
	 */
	function __construct($db, $exists = null)
	{
		$this->db = $db;
		if ($exists === null)
		{
			$this->collectionName = uniqid('temp_', true);
		}
		else
		{
			$this->collectionName = $exists;
		}
		
		// okay, because the user could clone this, or use this::findObjects() to create multiple instances of the object referring to
		// the same collection, we want to record how many pointers we have so we don't drop it if we don't need to. 
		if (!isset(self::$TempCollections[$this->collectionName]))
		{
			self::$TempCollections[$this->collectionName] = 1;
		}
		else
		{
			self::$TempCollections[$this->collectionName]++;
		}

		parent::__construct( $this->db);
	}
	
	/**
	 * This is the only special thing, just cleans up when it's done. 
	 * 
	 * @return     
	 */	
	function __destruct()
	{
		if ((isset(self::$TempCollections[$this->collectionName]) && self::$TempCollections[$this->collectionName] < 2) || !isset(self::$TempCollections[$this->collectionName]))
		{
			$this->drop();
		}
		else
		{
			self::$TempCollections[$this->collectionName]--; // decrement the number of instances so we don't drop the collection if other stuff is still using it. 
		}
	}
	
	/**
	 * similar to find, but returns a MongoCursorPlus object instead of mongocursor, which will return typed objects instead of arrays.
	 * Had to customize over the stock mongocollectionplus 
	 *
	 * @param array $filter filter
	 *
	 * @return object    MongoCursorPlus object.
	 */
	public function findObjects($query)
	{
		return new MongoCursorPlus($this->site, get_class($this), $this->dbName.'.'.$this->collectionName, $query);
	}
}
