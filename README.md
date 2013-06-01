MongoPlus
=========

A couple of PHP classes to enhance the functionality of MongoDB.

MongoCollectionPlus - extends PHP's Mongo collection, adding events, default structure, casting, and other neat stuff.

MongoCursorPlus - extends PHP's MongoCursor class to allow the cursor to return instances of MongoCollectionPlus instead of associative arrays. Accessed via MongoCollectionPlus::findObjects()

MongoTempCollection - creates a temporary collection which destroys itself at the end of the script run. Has some hooks to feed CSV files into it automatically. Good for searching and processing flat files.

