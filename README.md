MongoPlus
=========

A couple of PHP classes to enhance the functionality of MongoDB.

MongoCollectionPlus - extends PHP's Mongo collection, adding events, default structure, casting, and other neat stuff.

MongoCursorPlus - extends PHP's MongoCursor class to allow the cursor to return instances of MongoCollectionPlus instead of associative arrays. Accessed via MongoCollectionPlus::findObjects()

MongoTempCollection - creates a temporary collection which destroys itself at the end of the script run. Has some hooks to feed CSV files into it automatically. Good for searching and processing flat files.

Features
--------

- Mongo collections with default structure and values.
- Operate on a document objectively with setters and getters.
- load/insert/update/delete pre and post events to attach methods to, so that you can make your object functionaly smarter.
- per-property setter and getter events, for operating on particular properties, casting, modification, logging, etc..
- findObjects() method that operates just like MongoCollection::find(), but returns instances of MongoCollectionPlus instead of associative arrays. This utilizes MongoCursor, so it's memory efficient.
-


Tutorial
---------

Basic use is very easy, you just need to extend MongoCollectionPlus, and specify the name of the collection you want to use:

	class pets extends MongoCollectionPlus {
		protected $collectionName = 'pets';
	}


This gives you all the base flexibility from MCP:

	$example = new BasicExample($mongoDBObject);
	// create a new pet
	$example->set(array(
		'species'=>'dog',
		'name'=>'fido',
		'age'=>1
	));
	// you can also set a particular property
	$example->set('color', 'brown');
	// now insert it
	$example->insert();
	unset($example); // okay, it inserted. let's try and get it back.
	$dog = new pets($mongoDBObject);
	$dog->findOne(array('species'=>'Dog'));
	echo $dog->get('name');
	// let's update the name
	$dog->set('name', 'Rawrf');
	$dog->update();

Okay - simple enough, but this isn't that much better than just doing it normally. Here's where MCP starts to show it's usefulness:

	class pets extends MongoCollectionPlus {
		protected $collectionName = 'pets';
		protected $default = array(
			'species'=>'cat',
			'name'=>'',
			'age'=0,
			'color'=>'not specified'
		);
		protected $onBeforeInsert = array(
			'setLastUpdate'
		);
		protected $onBeforeUpdate = array(
			'setLastUpdate'
		);
		/** make sure we always know when the data was updated */
		protected function setLastUpdate()
		{
			$this->set('lastUpdate', new MongoDate());
		}
	}

Now, we have a default structure, and an automatic property that is updated every time you update the object in the collection.

	$pets = new pets($mongoDBObject);
	var_dump($pets->get()); // should show the default structure.
	$pets->set('age', 121);
	$pets->set('name', 'Smokey'); // now we have a 121 year old cat named smokey.
	$pets->insert();
	echo date('r', $pets->get('lastUpdate')->sec); // this should read when you inserted the record.
	$pets->set('color', 'black');
	$pets->update();
	echo date('r', $pets->get('lastUpdate')->sec); // this should read when you updated the record.

You can also use the per-property events to cast or update properties

	class pets extends MongoCollectionPlus {
		protected $collectionName = 'pets';
		protected $default = array(
			'species'=>'cat',
			'name'=>'',
			'age'=0,
			'color'=>'not specified'
		);
		private $speciesList = array('dog', 'cat', 'bird', 'fish');
		protected $onBeforeInsert = array(
			'setLastUpdate'
		);
		protected $onBeforeUpdate = array(
			'setLastUpdate'
		);

		protected $setField = array('age'=>'setDogYears', 'species'=>'enumSpecies');
		protected $getField = array('name'=>'capitalizeIt');

		/** maintains a dog years property */
		protected function setDogYears($value)
		{
			$this->set('dogYears', $value * 7);
			return $value; // don't need to manipulate age value directly, so just return it.
		}

		/** validates species property according to list */
		protected function enumSpecies($value)
		{
			$value = strtolower($value);
			if (in_array($value, $this->speciesList))
			{
				return $value;
			}
			return 'Unknown';
		}

		/** names deserve capitalization! */
		protected function capitalizeIt($value)
		{
			return ucfirst($value);
		}

		/** make sure we always know when the data was updated */
		protected function setLastUpdate()
		{
			$this->set('lastUpdate', new MongoDate());
		}
	}

Keep in mind, none of this functionality interferes with the exiting functions available in MongoCollection. You can still find(), update(), and insert() normally.
Just leave out the parameters to reference the 'special' functionality of the class.

You can think of this as a mongo driver with split personality. In one sense you can use it like you would normally use the Mongo driver. But you can also refer to the
instance as a document in the collection.

I'll post more examples as I have time since I think that's the best way to learn, but below are the specifics.


Usage
=====

To use, just extend MongoCollectionPlus. To instantiate, it expects you to pass a valid instance of MongoDB class to the constructor:

	$instance = new yourClassName(MongoDB);

Required properties
-------------------

- MongoCollectionPlus::collectionName - a string name of the collection referenced.

Optional properties
-------------------

- MongoCollectionPlus::default - an array, containing any default values you want for the object.

Read Only properties
--------------------
- MongoCollectionPlus::isLoaded - true if there's a document loaded in the instance.

Event Hooks
-----------
All even hooks are arrays, for general events, this is just a list of internal functions that are called when that event happens. For MongoCollectionPlus::setProperties
and MongoCollectionPlus::getProperties this is an associative array of referenced property for the key, and the method name for the value.

- MongoCollectionPlus::setField - array of the form array('property'=>'methodName', ...); to describe a method that will be called when the referenced property is set. Methods must accept a single parameter which is the new value of the property, and if you $this->get('propertyName') you can retrieve the existing value. This is called prior to setting the property in the internal hash.
- MongoCollectionPlus::getField - like setField, but instead called when the getter is fired. Return value of the method will be returned to the calling code.
- MongoCollectionPlus::onStart - array of method names to be called when class is constructed. This happens before we connect this instance to the passed DB instance, so you can do manipulation and some initial settings here if you want. Not really sure what it's good for, but included for completeness.
- MongoCollectionPlus::onLoad - array of method names that are called when a document is loaded from the DB.
- MongoCollectionPlus::onBeforeInsert - Methods to be called before an insert happens.
- MongoCollectionPlus::onAfterInsert - Methods to be called after an insert happens.
- MongoCollectionPlus::onBeforeUpdate - Methods to be called before an update occurs.
- MongoCollectionPlus::onAfterUpdate - Methods to be called after an update occurs.
- MongoCollectionPlus::onBeforeRemove - Methods to be called before removing a document.
- MongoCollectionPlus::onAfterRemove - Methods to be called after removing a document.

Method magic
-------------------

- MongoCollectionPlus::findOne - instead of normal operation, this will load the document instance into the internal hash. Returns like the parent method.
- MongoCollectionPlus::findObjects - Works like the MongoCollection::find() method, but returns instances of MongoCollectionPlus instead of associative arrays.
- MongoCollectionPlus::insert() - when no properties are passed, will insert a new document with the document has of the current instance.
- MongoCollectionPlus::update() - when no parameters are passed, will update the data in the internal document has to the document referred to by the this::primaryKey
- MongoCollectionPlus::removte() - when no parameters are passed, will remove the document refered to by this::primaryKey

Helper Methods
-------------------
- MongoCollectionPlus::get($property) - returns the value of the specified property. DOES NOT interpret dot notation right now. If you don't pass a property, will return the entire loaded document.
- MongoCollectionPlus::set($property, $value) - sets the value specified. You can also provide a single associative array to set several properties at once. DOES NOT ACCEPT DOT NOTATION
- MongoCollectionPlus::getByPath($property) - same as ::get() but you can pass it dot notation. I seperated this out because my way of translating dot notation is probably not fast. But maybe I can integrate it into the standard getter later. @todo
- MongoCollectionPlus::setByPath($property, $value) - same as ::set() but you can pass dot notation. Again, it's ugly, so I didn't make the standard setter use it. @todo
- MongoCollectionPlus::inc($property) - immediately increments the specified property in the current document. This is a write method, and affects the db directly.
- MongoCollectionPlus::dec($property) - immediately decrements the specified property
- MongoCollectionPlus::castBool($value) - returns a boolean based on the provided variable. Smart enough to recognize numbers (0 or less = false) and strings 'false' and 'true'
- MongoCollectionPlus::castMongoId($value) - returns an instance of mongoId based on the provided string. If you provide a mongoId, it doesn't do anything, just returns what you gave it.
- MongoCollectionPlus::hasKey($property) - returns a boolean, based on the existance of the specified property in the internal hash.
- MongoCollectionPlus::loadRow($document) - manually loads a document into the class and sets isLoaded = true. @deprecated should probably call this loadDocument
- MongoCollectionPlus::validateInsert() - method that is called before an insert is made. If this method returns false, the insert is aborted.
- MongoCollectionPlus::unLoad() - unloads the current has from the object and sets isLoaded = false. Can be useful in loops maybe.

Todo / Roadmap
=====================
- Document MongoCursorPlus and MongoTempCollection.
- Document and test relvancy search, maybe this goes into another class or trait?
- Create some unit tests for public methods.
- Create some example classes
