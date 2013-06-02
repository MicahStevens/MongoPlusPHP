<?php
/**
 * Provides tighter integration for lists of mongocollectionplus objects. This is returned by mongocollectionplus instead of a standard cursor if you call findObjects
 * @author Micah Stevens <micahstev@gmail.com>
 * @version 1.0 - initial release
 *
 * @package MongoPlusPHP
 */

class MongoCursorPlus extends MongoCursor
{
	var $db; // db object
	var $collectionType;
	
	function __construct($db, $className, $collectionName, $query)
	{
		$this->db = $db;
		$this->collectionType = $className;
		parent::__construct( $this->db, $collectionName, $query, array());
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
			$object = new $this->collectionType($this->db, $collectionName);
		}
		else
		{
			$object = new $this->collectionType($this->db);
		}
		$object->loadRow($current);
		return $object;
	}
	
}
