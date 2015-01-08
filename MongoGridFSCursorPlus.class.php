<?php
/**
 * Contains the single "unit" data abstraction classes, intended to be used in an OOP framework
 *
 */
/**
 * Provides tighter integration for lists of mongocollectionplus objects. This is returned by mongocollectionplus instead of a standard cursor if you call findObjects
 * @author Micah Stevens <micahstev@gmail.com>
 * @version 1.0 - initial release
 *
 * @package Core
 */

class MongoGridFSCursorPlus extends MongoGridFSCursor
{
	var $site; // site object
	var $collectionType;
	
	function __construct($site, $gridFS, $className, $collectionName, $query)
	{
		$this->site = $site;
		$this->collectionType = $className;
		parent::__construct( $gridFS, $this->site->db, $collectionName, $query, array());
	
		
	}
	
	/**
	 * grabs the array from the parent, and creates an object that matches the passed collection and returns it populated with the data. 
	 * 
	 * @return object    instance of the populated collection object
	 */
	function current()
	{
		$current = parent::current();
		if ($current === null)
		{
			return null; // nothing is nothing
		}
		
		// if it's a temp collection, we need to pass the generated name over. 
		if ($this->collectionType == 'MongoTempCollection')
		{
			$object = new $this->collectionType($this->site, $collectionName);
		}
		else
		{
			$object = new $this->collectionType($this->site);
		}
		$object->loadRow($current);
		return $object;
	}
	
}