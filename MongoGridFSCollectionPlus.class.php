<?php
/**
 * Contains the single "unit" data abstraction classes, intended to be used in an OOP framework
 *
 */
/**
 * Based on mysql code by Eddie Daniels  <stratease@gmail.com>
 * @author Micah Stevens <micahstev@gmail.com>
 * @version 1.1 added more helper functions
 * @version 2.0 - extends mongo collection
 * @version 1.0 - didn't extend mongoCollection
 * @todo Add foreign key mapping for app joins-maybe mongodbref?
 * @todo add some helper methods for caching?
 * @todo add list class to deal with iteration
 *
 * @package Core
 */
require(dirname(__FILE__).DIRECTORY_SEPARATOR."MongoGridFSCursorPlus.class.php");
abstract class MongoGridFSCollectionPlus extends MongoGridFS
{
	/**
	 * Various extended data for this instance
	 * @var array
	 */
	protected $extended = array();
	/**
	 * On load event.
	 *
	 * This is fired when an associated database instance has been loaded. This includes being fired after a successful insert.
	 * @var array
	 */
	protected $onStart = array();
	/**
	 *holds the internal values for the database
	 *@var array
	 */
	protected $schemaValues = array();
	/**
	 * On load event.
	 *
	 * This is fired when an associated database instance has been loaded. This includes being fired after a successful insert.
	 * @var array
	 */
	protected $onLoad = array();
	/**
	 * On a successful insert event.
	 * @var array
	 */
	protected $onAfterInsert = array();
	/**
	 * On before an insert event.
	 * @var array
	 */
	protected $onBeforeInsert = array();
	/**
	 * On successful update event.
	 * @var array
	 * @todo Fully implement the event/subscriber methods
	 */
	protected $onAfterUpdate = array();
	/**
	 * Before database entry update event subscribers.
	 * @var array
	 */
	protected $onBeforeUpdate = array();
	/**
	 * On successful delete event.
	 * @var array
	 * @todo Fully implement the event/subscriber methods
	 */
	protected $onAfterRemove = array();
	/**
	 * On before delete event.
	 * @var array
	 * @todo Fully implement the event/subscriber methods
	 */
	protected $onBeforeRemove = array();
	/**
	 * Field on change event subscribers.
	 * @var array
	 * @todo - not implimented yet.
	 */
	protected $onFieldChange = array();
	/**
	 * Defines a custom function to do conversions on a field as it is retrieved.
	 *
	 * A multi-dimensional associative array which should be indexed by the field that is being retrieved, and an array of function(s)
	 * that will be passed the fields value, and is expected to
	 * @var array
	 * @example
	 * <code>
	 * $getField = array('serializedField' => 'unserializeMeFunc');
	 * protected function unserializeMeFunc($value)
	 * {
	 *		return unserialize($value);
	 * }
	 * </code>
	 */
	protected $getField = array();
	/**
	 * Counterpart to the getField method.
	 *
	 * This will convert the data being passed into the format that would be pushed into the database.
	 * @var array
	 */
	protected $setField = array();
	/**
	 * Called when retrieving a default value for the given field.
	 *@var array
	 */
	protected $default = array();
	/**
	 * Flag that monitors when an instance has been properly loaded.
	 * @var bool
	 */
	public $isLoaded = false;
	/**
	 * The name of the primary key. Only necessary if your primary key is different than Mongo's default.
	 * @var string
	 */
	public $primaryKey = '_id';
	/**
	 * The name of the DB to use for this object
	 * @var string
	 */
	protected $dbName = null;
	/**
	 * The name of the collection.
	 *@var string
	 */
	protected $collectionName = null;
	/**
	 * The collection object
	 * @var object
	 */
	protected $collection;
	/**
	 * Holds values of all document fields.
	 * @var array
	 */
	protected $documentValues = array();
	/**
	 * Site class
	 */
	protected $site;
	/**
	 * True if this should be a gridFS collection
	 */
	protected $gridFS = false;
	/**
	 * binary/text data for gridFS file. Required. set to '' if you want an empty file.
	 */
	public $fileData = null;
	/**
	 * Child class name
	 */
	public $__CLASS__;
	function __construct($site, $collectionOverride = null)
	{
		$this->__CLASS__ = get_class($this);
		$this->site = $site;
		$dbServer = $site->db;
		if ($this->dbName == null) {
			// user didn't set dbName, so pull default from config
			$this->dbName = $site->config->db->database;
		}

		if ($this->dbName === null) {
			trigger_error(get_class($this) . "::__construct error - no DB specified", E_USER_ERROR);
		}
		// do startup activities
		foreach ($this->onStart as $func) {
			$this->$func();
		}
		if ($collectionOverride !== null)
		{
			$this->collectionName = $collectionOverride;
		}
		// Initialise Mongo
		$this->db = $dbServer->selectDB($this->dbName);
		parent::__construct($this->db, $this->collectionName);

	}
	/**
	 * onLoad event.
	 */
	protected function onLoad()
	{
		foreach ($this->onLoad as $func) {
			$this->$func();
		}
	}
	/**
	 * onBeforeInsert event.
	 */
	protected function onBeforeInsert()
	{
		foreach ($this->onBeforeInsert as $func) {
			$this->$func();
		}
	}

	/**
	 * public getter for the db collection name
	 * @return string The collection name
	 */
	public function getCollectionName()
	{
		return $this->collectionName;
	}

	public function getDefaultFields()
	{
		return $this->default;
	}

	/**
	 * Atomic method to increment a value by the specified amount.
	 */
	public function inc($field, $amount = 1)
	{
		$this->update(array('_id'=>$this->schemaValues['_id']),
					  array('$inc'=>array($field=>$amount))
				  );
		// update internal data.
		$this->schemaValues[$field] +=  $amount;
	}

	/**
	 * Atomic method to decrement a value by the specified amount.
	 */
	public function dec($field, $amount=1)
	{
		$amount = $amount * -1;
		$this->update(array('_id'=>$this->schemaValues['_id']),
					  array('$dec'=>array($field=>$amount))
				  );
		// update internal data.
		$this->schemaValues[$field] +=  $amount;
	}


	/**
	 * Inserts a new entry.
	 * @return mixed Returns insert id on a successful insert, false on a failure due to failed custom validation or an unkown insertion error.
	 * @todo Do we need safe/fsync? Maybe have a class setting.
	 * @todo make this work better with filenames/gridfs, rather than using php ram for file xfer.
	 */
	public function insert($data=null, $bytes, $options = array())
	{
		$this->storeBytes($bytes, $data, $options);
	}
	
	
	/**
	 * Hook to put insert validations
	 *@return true if insert is allowed, false if denied
	 */
	protected function validateInsert()
	{
		return true;
	}
	/**
	 * onAfterInsert event.
	 */
	protected function onAfterInsert()
	{
		foreach ($this->onAfterInsert as $func) {
			$this->$func();
		}
	}
	/**
	 * onBeforeUpdate event.
	 */
	protected function onBeforeUpdate()
	{
		foreach ($this->onBeforeUpdate as $func) {
			$this->$func();
		}
	}
	/**
	 * inserts current object, using defaults if set.
	 *
	 * @return boolean    false if there's a problem
	 * @todo safe mode?
	 */
	public function update($criteria=null, $new_object=null, $options=array())
	{
		if ($criteria !== null)
		{
			if ($new_object === null)
			{
				// no object specified, so default to use internal
				// no need to check for loaded, since it's a general query.
				$data = $this->schemaValues;
				unset($data['_id']);
				$r = parent::update($criteria, array('$set' => $data), $options);
				return $r;
			}
			else
			{
				// if specified, do a normal update.
				return parent::update($criteria, $new_object, $options);
			}
		}
		// no parameters, so do an update on single object.
		if (!$this->isLoaded)
		{
			return false;
		}
		$this->onBeforeUpdate();
		$data = $this->schemaValues;
		unset($data['_id']);
		$return = parent::update(array(
				$this->primaryKey => $this->get($this->primaryKey)
				) , array('$set' => $data), array('safe'=>true));
		$this->onAfterUpdate();
		return $return;
	}

	/**
	 * Delete document
	 */
	public function delete($id = null)
	{
		if($id)
		{
			if(!is_object($id))
			{
				$id = new MongoId($id);
			}
			$result = $this->remove(array('_id' => $id), array('safe' => true));
		}
		else
		{
			$result = $this->remove(array('_id' => new MongoId($this->get('_id')->{'$id'})), array('safe' => true));
		}
		return ($result['ok'] == 1 && $result['n'] == 1);
	}

	/**
	 * onAfterUpdate event.
	 */
	protected function onAfterUpdate()
	{
		foreach ($this->onAfterUpdate as $func) {
			$this->$func();
		}
	}
	/**
	 * onBeforeDelete event.
	 */
	protected function onBeforeRemove()
	{
		foreach ($this->onBeforeRemove as $func) {
			$this->$func(array(
				$this->primaryKey => $this->get($this->primaryKey)
			) , $options = array());
		}
	}
	/**
	 * Removed current object. Skips if nothing is loaded.
	 *
	 * @return Type    Description
	 * @todo use safe mode?
	 */
	public function remove($criteria=null, $options=array())
	{
		if ($criteria !== null)
		{
			// do a normal mongo remove
			return parent::remove($criteria, $options);
		}

		if (!$this->isLoaded) {
			return false;
		}
		$this->onBeforeRemove();
		$return = parent::remove(array($this->primaryKey => $this->get($this->primaryKey)));
		$this->onAfterRemove();
		// clean current instance.
		$this->schemaValues = array();
		$this->file = null;
		$this->isLoaded = false;
		return $return;
	}
	/**
	 * onAfterDelete event.
	 */
	protected function onAfterRemove()
	{
		foreach ($this->onAfterRemove as $func) {
			$this->$func();
		}
	}
	/**
	 * Sets all the properties - expects a GridFSFile object!! 
	 * @param array $set Associative array of fields => values
	 * @param bool $overWrite Will overwrite any registered data for this particular instance, true to merge instead with existing taking precidence.
	 * @return bool Returns true for a successful load, false if nothing was loaded
	 */
	public function loadRow($set, $overWrite = false)
	{
		$this->isLoaded = true;
		if ($overWrite) {
			$this->schemaValues = $set->file;
		} else {
			// merge with anything that already exists.
			$this->schemaValues = $this->valueMerge($set->file, $this->schemaValues);
		}
		$this->file = $set;
		$this->onLoad();
	}

	/**
	 * Runs the custom code listeners in the 'onFieldChange' var.
	 * @param string $field The field that changed
	 */
	private function onFieldChange($field)
	{
		if (isset($this->onFieldChange[$field])) {
			foreach ($this->onFieldChange[$field] as $function) {
				$this->$function($this->get($field));
			}
		}
	}
	/**
	 * Runs the custom code listeners in the 'getDefault' var.
	 * @param string $field The field that changed
	 * @todo this should probably be updated to allow for the overwriteable method in addition to pulling the databases defined 'default' value.
	 * @return mixed
	 */
	private function getDefault($field)
	{
		if (isset($this->default[$field]) === true) {
			return $this->default[$field];
		} else {
			return null;
		}
	}

	/**
	 * Loads an instance from the DB based on query. Note: This uses findOne, so don't plan on getting a list.
	 *
	 * @param mixed $query A mongo query array, or a string/MongoId if you just want to grab by PK.
	 * @param array $fields use this to select a subset of data.
	 * @return boolean    True if successful
	 * 
	 */
	public function findOne($query = array(), $fields = array())
	{
		$document = null;
		$args = func_get_args();
		if (is_array($query)) {
			// looks like it's a normal mongo style query
			$document = parent::findOne($query, $fields);
		}
		else
		{
			// assume if just a string is passed, it's a lookup by PK
			try {
				$mongoid = new MongoId($query);
			} catch (Exception $e) {
				$mongoid = false;
			}
			if($mongoid)
			{
				$query = array($this->primaryKey => $mongoid);
			}
			elseif (is_a($query, 'MongoId'))
			{
				// need to lookup by object if we're doing this by _id, so create it if need be.
				$query = array($this->primaryKey => $query);
			}
			$document = parent::findOne($query, $fields);
		}

		if ($document !== null) {
			$this->schemaValues = $document->file;
			$this->file = $document;
			// @todo should we provide a pointer to the binary data now?
			$this->isLoaded = true;
			$this->onLoad();
			return $this->get();
		}
		else
		{
			return false;
		}
	}

	/** don't use this! old style.. blech. */
	public function select()
	{
		return (is_array(call_user_func_array(array($this, 'findOne'), func_get_args())));
	}

	/**
	 * pretty much the same as the mongo find method at this point.
	 * 
	 * @return mongoCursor    mongo cursor returned
	 */
	public function find()
	{
		$args = func_get_args();
		if(func_num_args() !== 0)
		{
			if(!is_array($args[0]))
			{
				if($this->primaryKey === '_id' && !is_a($args[0], 'MongoId'))
				{
					$args[0] = array('_id' => new MongoId($args[0]));
				}
				else
				{
					$args[0] = array($this->primaryKey => $args[0]);
				}
			}
		}
		return call_user_func_array(array('parent', __FUNCTION__), $args);
	}

	/**
	 * similar to find, but returns a MongoCursorPlus object instead of mongocursor, which will return typed objects instead of arrays.
	 *
	 * @param array $filter filter
	 *
	 * @return object    MongoCursorPlus object.
	 * @todo This doesn't seem to work with gridfs cursor? Not getting any results. :P 
	 */
	public function findObjects($query=[])
	{
		return new MongoGridFSCursorPlus($this->site, $this, get_class($this), $this->dbName.'.'.$this->collectionName, $query);
	}

	/**
	 * Unloads object instance data and resets it to default.
	 *
	 * @return boolean    true always.
	 */
	public function unLoad()
	{
		$this->schemaValues = array();
		$this->file = null;
		$this->isLoaded = false;
		return true;
	}

	/**
	 * Gives an estimate of the total count of records given the query provided.
	 * This is cached in session (maybe memcache later?), so will only be updated periodically and is not unique to the user.
	 * The purpose is to give a faster alternative to the find()->count() method which can be slow.
	 * THIS MAY NOT BE ACCURATE
	 */
	public function total($query = [])
	{
		// @todo
		return $this->find($query)->count();
	}
	/**
	 * property setter
	 *
	 * @return boolean    true if successful
	 * @todo add setter method calls?
	 * @todo make setter path aware?
	 */
	public function set()
	{
		$args = func_get_args();
		if (func_num_args() == 1 && is_array(func_get_args())) {
			$args = $args[0];
			// perform any built-in mods
			foreach ($this->setField as $field => $method) {
				if (isset($args[$field])) {
					// it's being set, so perform mod
					$args[$field] = $this->$method($args[$field]);
				}
			}
			$this->schemaValues = array_merge($this->schemaValues, $args); // new values should override old... we may be removing items etc...
		}
		if (func_num_args() == 2) {
			if (isset($this->setField[$args[0]])) {
				$args[1] = $this->{$this->setField[$args[0]]}($args[1]); // run modifier
			}
			$this->schemaValues[(string)$args[0]] = $args[1];
		}
		return true;
	}

	/**
	 * retrieve value by using dot notation.
	 *
	 * @param string $field dot notation path to var
	 *
	 * @return Type    mixed variable
	 */
	public function getByPath($field)
	{
		$value = $this->getNestedVar($this->schemaValues, $field);
		if (array_key_exists($field, $this->getField))
		{
			$funcs = $this->getField[$field];
			if (is_array($funcs)) {
				foreach ($funcs as $func) {
					$value = $this->$func($value);
				}
			} else {
				$value = $this->$funcs($value);
			}
		}
		return $value;
	}

	/**
	 * Set by dotted path notation
	 *
	 * @param string $field dotted path fieldname
	 * @param mixed $value value to set the fieldname to
	 *
	 * @return true
	 */
	public function setByPath($field, $value)
	{
		if (isset($this->setField[$field]))
		{
			$value = $this->{$this->setField[$field]}($value); // run modifier
		}
		$pointer = &$this->getNestedVar($this->schemaValues, $field, true); // we're creating it, so ensure the path exists.

		$pointer = $value;
		return true;
	}

	/**
	 * Converts dotted syntax into a pointer to an array position. i.e. 'a.b.c' = $a['b']['c']
	 *
	 * @param array $context Associative array to point to
	 * @param string $name    path to point to
	 *
	 * @return reference    pointer to position
	 */
	private function &getNestedVar(&$context, $name, $create=false) {
		$pieces = explode('.', $name);
		foreach ($pieces as $piece) {
			if (!is_array($context) || !array_key_exists($piece, $context)) {
				if ($create)
				{
					// doesn't exist, create and continue
					$context[$piece] = array();
				}
				else
				{
					return null; // doesn't exist.
				}
			}
			$context = &$context[$piece];
		}
		return $context;
	}
	
	
	/**
	 * property getter
	 *
	 * @return mixed    property requested.
	 * @todo needs a way to specify paths to particular sub-structures. dotted paths maybe? (JS style)
	 */
	public function get()
	{
		switch (func_num_args())
		{
			case 0:
				if (count($this->getField) !== 0) {
					$vals = $this->schemaValues;
					// run defined get methods
					foreach ($this->getField as $field => $funcs) {
						if (array_key_exists($field, $vals) === true) {
							if (is_array($funcs)) {
								foreach ($funcs as $func) {
									$vals[$field] = $this->$func($vals[$field]);
								}
							} else {
								$vals[$field] = $this->$funcs($vals[$field]);
							}
						}
					}
					return $this->valueMerge($this->default, $vals);
				} else {
					return $this->valueMerge($this->default, $this->schemaValues);
				}
				break;

			case 1:
				$args = func_get_args();
				if (array_key_exists($args[0], $this->getField))
				{
					$funcs = $this->getField[$args[0]];
					if (is_array($funcs)) {
						$d = $this->schemaValues[$args[0]];
						foreach ($funcs as $func) {
							$d = $this->$func($d);
						}
						return $d;
					} else {
						return $this->$funcs($this->schemaValues[$args[0]]);
					}
				}
				else
				{
					if (isset($this->schemaValues[$args[0]]))
					{
						return $this->schemaValues[$args[0]];
					}
					if (isset($this->default[$args[0]]))
					{
						return $this->default[$args[0]];
					}
					return null;
				}
				break;
			default:
				return array();
		}
	}

	/**
	 * lets you know if a key exists or not
	 *
	 * @param string $key key to check for.
	 *
	 * @return Boolean    true if it exists
	 * @todo should be path aware
	 */
	public function hasKey($key)
	{
		if (!$this->isLoaded)
		{
			return false;
		}
		return isset($this->schemaValues[$key]);
	}
	/**
	 * This sets any default values the object should have, but only if they're not already in schemaValues.
	 *
	 * @return mixed    current Schema Values after merging defaults.
	 */
	protected function createDefaults()
	{
		$this->schemaValues = $this->valueMerge($this->default, $this->schemaValues);
		return $this->schemaValues;
	}
	/**
	 * takes provided array and strips all variables that don't match those defined as default for
	 * this object. Helpful way to filter POST variables, etc. Just returns the filtered value.
	 *
	 * @todo doesn't filter by type. That could be helpful too?
	 * @param mixed value to filter
	 * @return filtered value
	 */
	public function filterByDefault($values)
	{
		$out = [];
		foreach ($this->default as $key=>$value) {
			if (isset($values[$key])) {
				$out[$key] = $values[$key];
			}
		}
		return $out;
	}

	/**
	 * takes a structure and filters out any data that normally we wouldn't want to update.
	 * Extend this if you want, normally we just dump the _id.
	 */
	public function filterSensitive($data)
	{
		unset($data['_id']);
		return $data;
	}
	/**
	 * Merges any number of arrays / parameters recursively, replacing
	 * entries with string keys with values from latter arrays.
	 * If the entry or the next value to be assigned is an array, then it
	 * automagically treats both arguments as an array.
	 * Numeric entries are appended, not replaced, but only if they are
	 * unique
	 *
	 * calling: result = array_merge_recursive_distinct(a1, a2, ... aN)
	 *
	 * @return array    resulting array is returned after merge.
	 */
	function valueMerge()
	{
		$arrays = func_get_args();
		$base = array_shift($arrays);
		if (!is_array($base)) $base = empty($base) ? array() : array(
			$base
		);
		foreach ($arrays as $append) {
			if (!is_array($append)) $append = array(
				$append
			);
			foreach ($append as $key => $value) {
				if (!array_key_exists($key, $base) and !is_numeric($key)) {
					$base[$key] = $append[$key];
					continue;
				}
				if (is_array($value) || (array_key_exists($key, $base) && is_array($base[$key]))) {
					if(isset($base[$key]) === false)
					{
						$base[$key] = null;
					}
					$base[$key] = $this->valueMerge($base[$key], $append[$key]);
				} else if (is_numeric($key)) {
					if (!in_array($value, $base)) $base[] = $value;
				} else {
					$base[$key] = $value;
				}
			}
		}
		return $base;
	}
	/**
	 * Does a search sorting by relevancy, also returning a relevancy field on each item.
	 * @param array $search The search array
	 * @param array $sort The sort array
	 * @param int $limit
	 * @param bool $returnMatches True if a list of matching fields should be added to each item
	 */
	public function relevancySearch($search, $sort = null, $limit = null, $returnMatches = false)
	{
		$results = array();
		if($sort && $limit)
		{
			$cursor = $this->find($search)->sort($sort)->limit($limit);
		}
		elseif($sort)
		{
			$cursor = $this->find($search)->sort($sort);
		}
		elseif($limit)
		{
			$cursor = $this->find($search)->limit($limit);
		}
		else
		{
			$cursor = $this->find($search);
		}
		if($returnMatches)
		{
			foreach($cursor as $item)
			{
				$matches = array();
				$item['relevancy'] = $this->getRelevancy($search, $item, $matches);
				$item['matches'] = $matches;
				$results[] = $item;
			}
		}
		else
		{
			foreach($cursor as $item)
			{
				$item['relevancy'] = $this->getRelevancy($search, $item);
				$results[] = $item;
			}
		}
		usort($results, array($this, 'mongoRelevancySort'));
		return $results;
	}

	protected function getRelevancy($searchFields, $item, &$matches = array())
	{
		$n = 0;
		foreach($searchFields as $key => $data)
		{
			if($key === '$or')
			{
				foreach($data as $eval)
				{
					$n += $this->getRelevancy($eval, $item, $matches);
				}
			}
			elseif(is_array($data) === true && (isset($data['$gt']) === true ||
																isset($data['$lt']) === true))
			{
				if($num = $this->isRelMatch($key, $data, $item))
				{
					$matches[] = $key;
				}
				$n += $num;
			}
			elseif(is_array($data) === true && isset($data['$in']) === true)
			{
				foreach($data['$in'] as $v)
				{
					if($num = $this->isRelMatch($key, $v, $item))
					{
						$matches[] = $key;
					}
					$n += $num;
				}
			}
			else
			{
				if($num = $this->isRelMatch($key, $data, $item))
				{
					$matches[] = $key;
				}
				$n += $num;
			}
		}
		return $n;
	}

	protected function isRelMatch($field, $evaluation, $item)
	{
		if(isset($item[$field]))
		{
			if(is_array($evaluation) === true)
			{
				if(array_key_exists('$regex', $evaluation) === true)
				{
					$regex = '/'.$evaluation['$regex'].'/';
					if(isset($evaluation['$options']))
					{
						$regex .= $evaluation['$options'];
					}
					if(preg_match($regex, $item[$field]))
					{
						return 1;
					}
				}
				elseif(array_key_exists('$gt', $evaluation) === true &&
					   array_key_exists('$lt', $evaluation) === true)
				{
					if($item[$field] > $evaluation['$gt'] && $item[$field] < $evaluation['$lt'])
					{
						return 1;
					}
				}
				elseif(array_key_exists('$gt', $evaluation) === true)
				{
					if($item[$field] > $evaluation['$gt'])
					{
						return 1;
					}
				}
				elseif(array_key_exists('$lt', $evaluation) === true)
				{
					if($item[$field] < $evaluation['$lt'])
					{
						return 1;
					}
				}
				elseif(array_key_exists('$gte', $evaluation) === true &&
					   array_key_exists('$lte', $evaluation) === true)
				{
					if($item[$field] >= $evaluation['$gte'] && $item[$field] <= $evaluation['$lte'])
					{
						return 1;
					}
				}
				elseif(array_key_exists('$gte', $evaluation) === true)
				{
					if($item[$field] >= $evaluation['$gte'])
					{
						return 1;
					}
				}
				elseif(array_key_exists('$lte', $evaluation) === true)
				{
					if($item[$field] <= $evaluation['$lte'])
					{
						return 1;
					}
				}
			}
			else
			{
				if($item[$field] == $evaluation)
				{
					return 1;
				}
			}
		}
		return 0;
	}

	function mongoRelevancySort($a, $b)
	{
		if($a['relevancy'] > $b['relevancy'])
		{
			return -1;
		}
		elseif($a['relevancy'] < $b['relevancy'])
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}

	/** helper cast functions - can be used with the setField array to ensure a particular data type */

	public function castBool($value)
    {
        if (is_bool($value))
        {
            return $value;
        }
        if (strtolower($value) == 'true')
        {
            return true;
        }
        if (strtolower($value) == 'false')
        {
            return false;
        }
        return (boolean)$value;
    }

	public function castMongoId($value)
	{
		if (is_string($value) && $this->checkId($value))
		{
			return new MongoId($value);
		}
		elseif (get_class($value) == 'MongoId')
		{
			return $value;
		}
		return null; // if we can't force a value
	}
	
	public function capitalize($value)
	{
		return ucfirst($value);
	}

	public function castInt($value)
	{
		return (int)$value;
	}

	public function checkId($_id)
	{
		try {
			$_id = new MongoId($_id);
		} catch (MongoException $ex) {
			return false;
		}
		return true;
	}
	
	/**** GRID FS COMPATIBILITY METHODS ****/
	/**
	 * Equiv of MongoGridFS::get() - necessary due to naming conflict with our get() method.
	 */
	public function getById($id)
	{
		$file = parent::get($id);
		if ($file)
		{
			$this->schemaValues = $file->file;
			$this->file = $file;
		}
	}
	
	/**
	 * Provides getBytes() compatibility.
	 */
	public function getBytes()
	{
		if ($this->isLoaded)
		{
			return $this->file->getBytes();
		}
		return null;
	}
	
	/**
	 * Provides getBytes() compatibility.
	 */
	public function getFilename()
	{
		if ($this->isLoaded)
		{
			return $this->file->getFilename();
		}
		return null;
	}
	
	/**
	 * Provides getResource() compatibility.
	 */
	public function getResource()
	{
		if ($this->isLoaded)
		{
			return $this->file->getResource();
		}
		return null;
	}
	
	/**
	 * Provides getBytes() compatibility.
	 */
	public function getSize()
	{
		if ($this->isLoaded)
		{
			return $this->file->getSize();
		}
		return null;
	}
	
	/**
	 * Provides write() compatibility.
	 */
	public function write()
	{
		if ($this->isLoaded)
		{
			return $this->file->write();
		}
		return null;
	}
	/** 
	 * storeBytes compatibility
	 */
	public function storeBytes($bytes, $metadata = null, $options = array())
	{
		if ($metadata !== null) 
		{
			// call parent
			return parent::storeBytes($bytes, $metadata, $options);
		}
		if($this->validateInsert() === false)
		{
			return false;
		}
		$this->createDefaults();
		$this->onBeforeInsert();
		$id = parent::storeBytes($bytes, $this->schemaValues);
		$this->schemaValues['_id'] = $id;
		$this->isLoaded = true;
		$this->onAfterInsert();
		return true;
	}
	
	/** 
	 * storeFile compatibility
	 */
	public function storeFile($filename, $metadata = null, $options = array())
	{
		if ($metadata !== null) 
		{
			// call parent
			return parent::storeBytes($filename, $metadata, $options);
		}
		if($this->validateInsert() === false)
		{
			return false;
		}
		$this->createDefaults();
		$this->onBeforeInsert();
		$id = parent::storeFile($filename, $this->schemaValues);
		$this->schemaValues['_id'] = $id;
		$this->isLoaded = true;
		$this->onAfterInsert();
		return true;
	}
	
	/** 
	 * storeUpload compatibility
	 */
	public function storeUpload($name, $metadata = null, $options = array())
	{
		if ($metadata !== null) 
		{
			// call parent
			return parent::storeUpload($name, $metadata, $options);
		}
		if($this->validateInsert() === false)
		{
			return false;
		}
		$this->createDefaults();
		$this->onBeforeInsert();
		$id = parent::storeUpload($name, $this->schemaValues);
		$this->schemaValues['_id'] = $id;
		$this->isLoaded = true;
		$this->onAfterInsert();
		return true;
	}

	
}