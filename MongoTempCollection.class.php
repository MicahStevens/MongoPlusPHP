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
	function __construct($site, $exists = null)
	{
		$this->site = $site;
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

		parent::__construct( $this->site);
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
	
	/**
	 * Imports rows of a spreadsheet into the temp collection.
	 * Header names are lower case!
	 * THIS REQUIRES INSTALLATION OF PHPEXCEL LIBRARY.
	 * @todo should maybe put this somewhere else so that this can be standalone. 
	**/
	public function loadSpreadsheet( $filename )
	{
		if( $objPHPExcel = PHPExcel_IOFactory::load($filename))
		{
			$objWorksheet = $objPHPExcel->getActiveSheet();
			$rows = $objWorksheet->getRowIterator();
			$data = array();
			$columns = array(); // store x location and column name
			$findHeader = true;
			
			// convert file to assoc array...
			foreach($rows as $y => $row)
			{
				$cells = $row->getCellIterator();
				$cells->setIterateOnlyExistingCells(false); // we want empty values too, so we can track relative locations
				foreach($cells as $x => $cell)
				{
					// find columns...
					if($findHeader)
					{
						$col = trim(strtolower($cell->getValue()));// trim whitespace, lower case for simplicty below
						if($col !== '') // don't get empty cols...
						{
							$columns[$x] = str_replace( ".", "", $col);
						}
					}
					else // load data...
					{
						if($columns[$x] === 'gam id')
						{
							$data[$y]['gam id'] = (int)trim($cell->getValue());
							$this->set('gam id', (int)trim($cell->getValue()));
						}
						elseif( false !== strpos( $columns[$x], 'date' ) ) // date
						{
							$date = $cell->getValue(); // 37231 (Excel date)
							$date = PHPExcel_Shared_Date::ExcelToPHP($date); // 1007596800 (Unix time)
							$date = date('m/d/Y', $date); // PHP formatted date
							$this->set($columns[$x], $date);
						}
						else // user group
						{							
							if(isset($data[$y][$columns[$x]]) === false)
							{
								$data[$y][$columns[$x]] = array();
								$this->set($columns[$x], array());
							}
							$d = trim($cell->getValue());
							if($d !== '')
							{
								$data[$y][$columns[$x]][] = $d;
								$this->set($columns[$x], $d);
							}
							else
							{
								$this->set($columns[$x], '');
							}
						}
					}
				}
				
				if( !$findHeader )
				{
					$this->insert();
					$this->unload();
					unset($data);
					$data = array();
				}
				$findHeader = false; // first row should have header...
			}
		}
		else
		{
			return false;
		}
	}
}