<?php

namespace PKMVC;

use \PDO;
use \Exception;
use \PDOStatement;
use \ReflectionProperty;
use \ReflectionClass;

/**
 * PKMVC Framework 
 *
 * @author    Paul Kirkaas
 * @email     p.kirkaas@gmail.com
 * @link     
 * @copyright Copyright (c) 2012-2014 Paul Kirkaas. All rights Reserved
 * @license   http://opensource.org/licenses/BSD-3-Clause  
 */
//require_once (__DIR__ . '/dbconnection.php');
//require_once (__DIR__ . '/pklib.php');

/** Base Model for PKMVC ORM
 * NAMESPACES:
 * Derived specific classes can be in their own namespace, separate from
 * the BaseModel namespace, BUT ALL THE MODELS THAT INTERACT WITH EACH OTHER
 * MUST SHARE THE SAME NAMESPACE.
 * <p>
 * properties used as follows:
 * protected: Map to representation of table fields, or other ORM objects or collectons
 * private: Internal Housekeeping ex, $dirty
 * Some internal attributes:
 * $memberObjects -- array of attributes that map to one to other ORM
 * object represented by an ID field in the current table, in the form
 * 'memberName => className. Typically the ClassName will be the same as 
 * the memberName. For example, if
 * if the table has an "employer_id" field that represents a full "Employer"
 * record in another table as an "object", the current DB field would be
 * "protected $employer_id; Int, and in the $memberObjects array be "employer"
 * The convention is if the external object class name is "Employer", the key
 * in this object will be 'employer', and the actual db field will be 
 * 'employer_id'
 * 
 * For one-to-many collections, for example, "employees", this would not have an
 * entry in this table, but be included as an array in the "memberCollections"
 * array as: 
 * array('employees'=>array('classname'=>'Employee','foreignkey'=>'boss_id')
 */
class BaseModel {

  /**
   * Array of names of member objects mapped to the foreign Class they represent.
   * This is a many-to-one relationship - may be several instances of this
   * object pointing to the same foreign object. Example: If the underlying table
   * has a "mother_id" integer representing an object in the Person table/object, 
   * this object will have two attributes representing that -- the "mother_id" field,
   * which is an integer containing the id of the row in the "Person" table, and
   * the "mother" attribute, which will contain the instantiated "mother" object.
   * In this static memberObjects array, it would be represented by the
   * key=>value pair <tt>'mother'=> 'Person'</tt> which maps the 'mother' object to
   * its class.
   */
  protected static $memberObjects = array(); #Array of names of member objects

  /** Array that maps collection attributes to their characteristics. A "collection"
   * attribute represents a one-to-many relationship from this object to a set of
   * foreign objects -- like, if this is a shopping_cart, it may have many "items".
   * The 'items' attribute of this object will be an array of the foreign objects.
   * The entry in this static array will map the collection attribute names to 
   * the external class name and the field name in the foreign object that points to
   * this object. Example: If this class contains an "items" collection attribute, it
   * would have an entry in this array of:
   * <tt>'items'=>array('classname'=>'Item', 'foreignkey'=>'shopping_cart_id')</tt>
   */
  protected static $memberCollections = array(); #Array of names of object collections

  /** Just a one dimentional array of attribute names of this class which correspond
   * directly to field names of the underlying table.
   */
  protected static $memberDirects = array(); #Array of Class attributes that map directly to table fields.
  protected $exclude = array(); #Members to exclude from direct maping to table fields
  /** Just keeps track if the object has been modified since being pulled from the 
   * the DB.
   */
  private $dirty = 0; #Checks if object has been modified
  /**
   * This is a static array of arrays belonging to the BaseModel class. The
   * primary key is 'ClassName', the secondary key is 'id'. Every object retrieved
   * from the DB is immediately entered/stored here. Any subsequent attempt to
   * retrieve an object of that ClassName/ID is checked against the objects
   * in the "instantiations" cache before it is retrieved from the DB.
   */
  static $instantiations = array(); #Associative array of class names with

#arrays of ID's of object instantiations.

  /** Checks if an object is already loaded, if so, returns it, if not
   *  loads it and registers it, if no $id, creates it, and returns it.
   *  <p>Objects should not be created by <tt>$obj = new ObjClass();</tt>.
   * Rather: <tt>$obj = ObjClass::get();</tt>. 
   * <p>::get() can take three types of arguments:
   * <ul>
   * <li> none/empty, in which case a virgin new object is created.
   * <li> An integer, which should be the ID of an underlying table row, in which
   * case the get will retrieve the table data, create the object and return it.
   * <li>An array. This is more complicated. The array may or may not contain
   * an object integer ID key. If not, a new object is created and initialized
   * with the data in the rest of the array. 
   *
   * TODO! RETHINK IF GET SHOULD DO AN UPDATE, OR ONLY FETCH/CREATE & LET
   * UPDATES BE CALLED EXPLICITLY!!!!!!
   *
   * <p>If the array contains an integer ID key, it is an update. First the get
   * checks to see if that object exists already in its cache (more later).
   * If not, it retrieves the object from the DB table. In either case, it then
   * updates the object with the data contained in the array.
   * </ul> 
   * @param int|Array $idOrArray: Either an integer or an array of data
   * @return BaseModel instance
   */
  public static function get($idOrArray = null) {
    $class = get_called_class();
    $baseName = static::getBaseName();
    pkdebug("IDORARR:", $idOrArray, "CLASS: [$class]");
    if (empty($idOrArray)) { #create new empty object
      return new $class();
    }
    if (is_numeric($idOrArray)) {
      $id = intval($idOrArray);
    }
    if (is_array($idOrArray) && !empty($idOrArray['id'])) {
      $id = $idOrArray['id'];
    }
    if (empty($id)) { #create new object initialized with array data
      $obj = new $class($idOrArray);
      pkdebug("Just created NEW object:", $obj);
      //pkstack();
      return $obj;
    }
    if (!isset(static::$instantiations[$baseName])) {
      static::$instantiations[$baseName] = array();
    }
    $classarr = &static::$instantiations[$baseName];
    if ($baseName == 'Chart') {
      pkdebug("Checking obj in classarr:", $classarr, "with ID", $id, " and CLASS [$class] with instantiation:", static::$instantiations);
    }
    if (isset($classarr[$id])) {
      if ($baseName == 'Chart') {
        pkdebug("Should do update with: $class though we have idOrAr:", $idOrArray);
      }
      $obj = $classarr[$id];
      if (is_array($idOrArray)) {  //Existing object with new data -- so update
        pkdebug("About to call update with obj:", $obj);
        $obj->update($idOrArray);
      }
      return $obj;
    }
    if ($baseName == 'Chart') {
      //pkdebug("In GET: No OBJ, so make new, with 'idOrArray:", $idOrArray);
    }
    $obj = new $class($idOrArray);
    if (!($obj instanceOf $class)) {
      //return false;
      throw new \Exception("Couldn't instantiate object of type [$class]");
    }
    $classarr[$id] = $obj;
    return $obj;
  }

  /** Updates an existing object with data from array
   *  TODO: Problem here if there is no key in the 
   * array -- was the field deleted? 
   * 
   * For now, with direct fields & member objects -- if the 
   * data array contains the key but has no value, delete the content, 
   * if no key, leave unchanged.
   * 
   * For collections, similar; if the array key is unset, leave alone, if the
   * key is set but empty, delete entire collection, and if the key is set
   * and contains an array, replace the entire contents of the existing object
   * collection array with the contents of the new input array.
   * 
   * @param Array $arr: The array of data to update this object with. Just raw
   * data in array form.
   */
  public function update(Array $arr, $withDelete = true) {
    //pkdebug("In UPDATE with ARR:", $arr);
#TODO: Eventually, make more efficient -- only set dirty if actual
#change results from this update
    #If no data, return
    if (!sizeof($arr) || empty($arr)) {
      return $this;
    }

    #Step through input array and set/update
    #First, direct fields
    $directFields = $this->getDirectFields();
    foreach ($directFields as $directField) {
      $table_field = unCamelCase($directField);
      if (isset($arr[$table_field])) {
        if ($this->$directField != $arr[$table_field]) {#Change; set & makeDirty
          $this->$directField = $arr[$table_field];
          $this->makeDirty(1);
        }
      }
    }

    #Now collections. 
    #data arr, and just raw data. If raw data, have to call ::get() with the 
    #data to see if the object exists, and then update it as well, recursively

    $collections = static::$memberCollections;
    foreach ($collections as $collName => $collDetails) {
      #$collObjArr = $this->$collName; #array of collection member objects
      #If data array has no key for this collection, skip
      if (!isset($arr[unCamelCase($collName)])) {
        continue;
      }
      $namespace = $this->getNamespaceName(); #For getting member objects
      $newData = $arr[unCamelCase($collName)];
      if (!sizeof($newData) || empty($newData)) { #Clear this collection
        $this->$collName = array();
        continue;
      }
      #We have an array of data arrays (at least one) for the collectin objects
      #They may be a mix of existing persisted objects (with an "ID"), and 
      # new ones, without.
      $collClass = $collDetails['classname'];
      $fullCollClass = $namespace . '\\' . $collClass;
      $foreignKeyName = $collDetails['foreignkey'];
      $foreignKeyValue = $this->getId();
      $newCollection = array();
      #First, make sure each element has this->id as the foreign key
      #then ::get() each of the datums and add to the new collection
      foreach ($newData as $newDataRow) {
        $newDataRow[$foreignKeyName] = $foreignKeyValue;
        $collObj = $fullCollClass::get($newDataRow);
        if ($collObj instanceOf $fullCollClass) {
          $newCollection[] = $collObj;
        }
      }
      $this->$collName = $newCollection;

      /*
        $collIds = static::getIds($collObjArr);
        $arrayIds = array();
        foreach ($newData as $newDatum) {
        if (isset($newDatum['id'])) {
        $arrayIds[] = $newDatum['id'];
        }
        }
        pkdebug("The Update Data Array:", $newData);
        //We have an array of ID's in the new arrays,
        //and an array of ID's from the objects in the original collection
        //Delete objs in the collection not in the array
        $todelete = array_diff($collIds, $arrayIds);
        foreach ($collObjArr as $idx => $collObj) {
        if (in_array($collObj->getId(), $todelete)) {
        $collObj->delete();
        unset($collObj);
        unset($collObjArr[$idx]);
        }
        }
       * 
       */
      #Now we should have a sparse array of only the remaining objects. Create
      #new, filled in array, update the objects with the new data, finally,
      #create any new objects in the collection...
      /*
        $newColl = array();
        foreach ($collObjArr as $collItem) {
        $newColl[] = $collItem;
        $id = $collItem->getId();
        $newDataItem = false;
        foreach ($newData as $newDataEl) {
        if ($newDataEl['id'] == $id) {
        $newDataItem = $newDataEl;
        break;
        }
        }
        if ($newDataItem) {
        $collItem->update($newDataItem);
        }
        } //Now we can make the new items in the collection!
        foreach ($newData as $newDatum) {
        //pkdebug("Found a new Datum!", $newDatum);
        if (empty($newDatum['id'])) {
        $newDatum[$foreignKeyName] = $foreignKeyValue;
        $newObj = $collClass::get($newDatum);
        $newColl[] = $newObj;
        }
        }
       */
      #pkdebug("NEW COLL COLLECTION:", $newColl);
      #pkdebug("THIS before assignment of new coll...:", $this);
      //$this->$collName = $newColl;
      #pkdebug("BEFORE SAVE IN UPDATE!!", $this);
      #$this->save();
      #pkdebug("AFTER SAVE IN UPDATE!!", $this);
    }
  }

  /** Returns an array of ID's from an array of Model instances
   * @param Array $objs: array of objects to extract IDs from
   * @return Array: Array of object ID's.
   */
  public static function getIds(Array $objs) {
    $ids = array();
    foreach ($objs as $obj) {
      $ids[] = $obj->getId();
    }
    return $ids;
  }

  /** Delete from DB, remove from persistent array, delete collections
   * 
   */
  public function delete() {
    $collectionNames = array_keys(static::$memberCollections);
    foreach ($collectionNames as $collectionName) {
      foreach ($this->$collectionName as $object) {
        $object->delete();
      }
    }
    $id = $this->getId();
    if ($id) {
      //$table_name = unCamelCase(get_class($this));
      $baseName = static::getBaseName();
      $table_name = unCamelCase($baseName);
      $paramArr = array('id' => $id);
      $paramStr = "DELETE FROM `$table_name` WHERE `id` = :id";
      //$strSQL = "DELETE FROM `$table_name` WHERE `id` = $id";
      prepare_and_execute($paramStr, $paramArr);
      unset(static::$instantiations[$baseName][$id]);
    }
  }

  /** Returns the default value of member collections. No doubt
   * there is a better way to do this...
   */
  public static function getMemberCollections() {
    return static::$memberCollections;
  }

  /** Queries the table with arguments in $args */

  /** Given an array of arrays of data, returns an array of matched objects,
   * as evaluated by "::get()".
   * @param Array $args: Array of arguments to ::get()
   * @return Array: Array of objects that match the elements of the input array
   * 
   */
  public static function getAsObjs(Array $args = null, $orderBy = null) {
    $className = get_called_class();
    $resArr = static::getAsArrays($args, $orderBy);
    if (!$resArr || !is_array($resArr) || empty($resArr) || !sizeOf($resArr)) {
      return false;
    }
    $retObjs = array();
    foreach ($resArr as $res) {
      if (!is_array($res)) {
        continue;
      }
      $ret = $className::get($res);
      //pkdebug("Returned in getAsObjs, ret:", $ret, 'res was:', $res);
      if ($ret instanceOf $className) {
        $retObjs[] = $ret;
      }
    }
    return $retObjs;
  }

  /**
   * Returns entries from an underlying table as a straight array -
   * With no args, by id, all, or by array of (field_names, values)
   * no object/ORM. With no argument, just a 'SELECT *' from the underlying
   * table. If $params are set, can be an integer, which should be the ID 
   * of the object, and return just the relevant row. Otherwise, $params
   * should be an array of key=>value pairs to be used in the select statement.
   * @param null|int|Array $params: The parameters for the query (see above)
   * @param String $orderBy: If present, the orderBy field for the query
   */
  public static function getAsArrays($params = null, $orderBy = 'id') {
    $fullClassName = get_called_class();
#    $table_name = unCamelCase($className);

    $retval = getArraysFromTable($fullClassName, $params, $orderBy); // $val, $field_name, $orderBy);
    return $retval;
  }

  /** True if the object has been modified without being saved to the DB,
   * else false
   */
  public function isDirty() {
    return $this->dirty();
  }

  /** Sets the dirty bit true with no arguments, but can also clear it
   * if arg is false/0.
   */
  public function makeDirty($val = 1) {
    $this->dirty = $val;
  }

  /** False if the name is not a member of this object, else a string
   * of public/private/protected for the member.
   */
  public function getMemberStatus($memberName) {
    if (!property_exists($this, $memberName)) {
      return false;
    }
    $reflectionProperty = new ReflectionProperty($this, $memberName);
    if ($reflectionProperty->isPrivate())
      return "private";
    if ($reflectionProperty->isProtected())
      return "protected";
    if ($reflectionProperty->isPublic())
      return "public";
    throw new \Exception("Couldn't find the property for [$memberName]");
  }

  /**
   * Returns all attribute names of the child instance
   * TODO: Won't work for child private members! Fix.
   * @return array of attributes (including private) of the child object
   */
  public function getMemberAttributeNames() {
    $reflector = new ReflectionClass($this);
    $properties = $reflector->getProperties();
    $names = array();
    foreach ($properties as $property) {
      $names[] = $property->getName();
    }
    return $names;
  }

  /** Returns all the "direct" fields of the class; that is,
   * those that map directly to fields in the underlying table.
   * Currently just the ::$memberDirects array.
   */
  public static function getDirectFields() {
    return static::$memberDirects;
  }

//Something is confused around here with getting the different kinds
//of attributes in different methods -- investigate!

  /** Returns the attributes for the given model property --
   * If a collection, the collection attributes, if an index to an
   * external instance of a model
   * 
   */

  /** Basically just camel-cases a table/object field name (person_id), to the
   * corresponding object property/class name (Person) in the foreign table,
   * but also checks
   * that both the field name exists and the foreign class exists.
   * @param String $field_id: The integer field_id that indicates an external object
   */
  public function fieldIdToObjectName($field_id) {
    $endsWith = '_id'; //Make sure it ends in _id
    if (!(substr($field_id, -strlen($endsWith)) == $endsWith)) {
      return false;
    }
    $field_id = unCamelCase($field_id) . $endsWith;

    //Get our members -- but NOT private properties of the child object
    $objectVars = $this->getVars();
    if (!in_array($field_id, $objectVars)) {
      return false;
    }
    #Convert to Object Name 
    $objName = fieldIdToObjectName($field_id);
    if (!$objName || !in_array($objName, $objectVars)) {
      return false;
    }
    return $objName;
  }

  /**
   * As above, in reverse. Returns the expected name of the object, given
   * the table field name.
   */
  public function objectNameToFieldId($objectName) {
    $endsWith = '_id'; //Make sure it ends in _id
    $objectVars = $this->getVars();
    if (!in_array($objectName, $objectVars)) {
      return false;
    }
    //Convert to field name
    $field_id = unCamelCase($objectName) . $endsWith;
    if (!in_array($field_id, $objectVars)) {
      return false;
    }
    return $field_id;
  }

  /** Even though defined in the base class, will return only private attributes
   * of the derived calling object instance. Via reflection.
   * (Not implemented)
   */
  public function getInstanceProperties() {
    
  }

  /** Checks if this is a direct DB value, a reference to 
   * an external Model object represented by an ID field in
   * this table, or a collection of external objects owned by this
   * object without a representation in the table
   * @param type $name
   * @return mixed -- array of attriubte properties if a collectin attribute,
   *    string external class name if reference to an external object,
   *    or boolean 'true' if just a direct table field mapping.
   * (Should do something way smarter in future)
   */
  public function getModelPropertyAttributes($name) {
    # Is it a collection?
    $collectionAttrs = $this->getCollectionAttributes($name);
    if ($collectionAttrs) {
      return $collectionAttrs;
    }
    if ($this->isTableField($name)) {
      return true;
    }
    return getExternalObjectClassName($name);
  }

  /** Does the member name map directly to a table field? Got to do
   * this smarter in future, therefore wrapping in a method.
   * @param memberName
   * @return true/false
   */
  public function isTableField($name) {
    #return doesFieldExist($name, get_class($this));
    return doesFieldExist($name, static::getBaseName());
  }

  /** Checks if the given field name refers to a collection in this
   * object, if so, returns the collection attributes
   * else false
   */
  public function getCollectionAttributes($name) {
    if (!is_string($name) || !strlen($name)) {
      return false;
    }
    if (!in_array($name, $this->getCollectionNames())) {
      return false;
    }
    return static::$memberCollections[$name];
  }

  public function getExternalObjectClassName($name) {
    if (in_array($name, array_keys(static::$memberObjects))) {
      return static::$memberObjects[$name];
    }
    return false;
  }

  /** Just examines static::$memberCollections and returns an array
   * of all collection names.
   */
  public function getCollectionNames() {
    return array_keys(static::$memberCollections);
  }

  /** Magic method to for auto set/get generation -
   * To allow dirty bit to be set with every change
   * @param type $name -- the method name
   * @param type $args -- array of args if any
   * @return -- it depends
   */
  public function __call($name, $args = array()) {
    $val = null; //default
    #First try get/set
    $className = get_class($this);
    $pre = substr($name, 0, 3); //is it a 'get' or 'set'?
    $memberName = substr($name, 3); #remove prefix to find field name
    if (($pre == 'get') || ($pre == 'set')) { #doing a get/set
      //Check if setting/getting a public table / field value...
      if (!$memberName) { //Shouldn't be here...
        throw new \Exception("Setting/Getting nothing");
      }
      $field_name = unCamelCase($memberName);
      $refProp = new \ReflectionProperty($this, $field_name);
      if (!$refProp || !($refProp instanceOf \ReflectionProperty) ||
              !$refProp->isPublic()) { //Not a public attribute, so give to __set/__get
        if ($pre == 'get') {
          return $this->__get($field_name);
        } else if ($pre == 'set') {
          return $this->__set($field_name, $val);
        }
        throw new \Exception("Shouldn't get here: [$field_name]");
      }
      #So is a public property, set the dirty bit if doing set...
      if ($pre == 'set') {
        $this->makeDirty();
        $this - $field_name = $val;
        return;
      }
      return $this->$field_name;

      // Totally duplicating work here. All I neeed to do is check if
      // this is a set/get of a public member, do that, otherwise forward
      //to __get/__set...
      /*
        if ($pre == 'set') { //First element of arg is value, or null
        if (isset($args[0])) {
        $val = $args[0];
        }
        }
        if (!$memberName) { //Shouldn't be here...
        throw new \Exception ("No field to set/get in call to [$className]");
        }
        #See what kind of property we have here
        $type = $this->getModelPropertyAttributes($name);
        switch (true) {
        case (is_array($type)): #We have a collection
        if ($pre == 'get') { //Get the collection...
        return $this->__get($memberName);
        } else if ($pre == 'set') {
        if (empty($val) || (is_array($val) && $val[0] instanceOf Base)) {
        return $this->__set($memberName, $val);
        }
        throw new \Exception ("Problem to [$pre] $memberName");
        break;
        case (is_string($type)): #We have an external Object
        break;
        case ($type === true): #Direct table field map
        break;
        default:
        throw new \Exception("Trying to '$pre' non member [$name]");
        }
       * 
       */
      throw new \Exception("Unknown method [$name] on class " . get_class($this));
    }
  }

  /*
    function fieldIdToObjectName ($field_id) {
    $endsWith = '_id'; //Make sure it ends in _id
    if (! (substr( $field_id, -strlen( $endsWith ) ) == $endsWith) ) {
    return false;
    }
    $objectName = toCamelCase(substr($field_id, 0, -strlen($endsWith));
    return $objectName;
    }

    function objectNameToFieldId ($objectName) {
    $endsWith = '_id'; //Make sure it ends in _id
    return unCamelCase($objectName).$endsWith;
    }



   */

  /**
   * TODO! Again, really think through if we really want to have the same method
   * update an existing, persisted object with new data in the argument...
   *
   * Creates the instance, based on $arg:
   * @param type $arg: Either null, to make a new, empty object, or
   * int ID, to retrieve an existing object from DB,
   * or initializing array of data, which we initialize the new object with
   */
  protected function __construct($arg = null) {
    if (empty($arg)) { #A new object.
      return;
    }
    #$arg should be an object integer ID, an array with a key 'id' set to 
    #a non-zero object ID, or an array of data for a new/non-persisted object
    $class = get_class($this);
    if (is_numeric($arg) && intval($arg)) {
      $id = intval($arg);
    } else if (is_array($arg)) {
      if (!empty($arg['id'])) {
        $id = intval($arg['id']);
      } else {
        $id = null;
      }
    } else {
      throw new Exception("Invalid parameter [" . pkvardump($arg) .
      "] for contstructor of class [$class]");
    }
    $this->setId($id);
    #If we have an ID, retrieve the persisted object.
    //$this->populateFromArray($arg);
    #
    #`if ()
    if (!is_array($arg)) { #Must be a non-zero integer equivalent
      if (!is_numeric($arg) || !($arg = intval($arg))) {
        throw new \Exception("Trying to create a new instance of [$class]
          with an invalid argument: " . pkvardump($arg));
      }
      $args = array('id' => intval($arg));
      $resarr = getArraysFromTable(get_class($this), $args);
      if (empty($resarr)) {
        throw new \Exception("Fetching a non-existant instance of [$class]
          with id: [$arg]");
      }
      $arg = $resarr[0];
    } else { #is an array of data for a new obj, so initialize - but make dirty
      $this->makeDirty(1);
    }
    $this->populateFromArray($arg);
    pkdebug("Construct, [$class] after PopulateFromArray: THIS:", $this, "ARGARR", $arg);
    if ($this->getId() && !empty(static::$memberCollections)) {
      $this->hydrateMemberCollections();
    }
    if (is_array($arg)) {
      $this->update($arg);
    }
    //The child class may need to do additional initialization
  }

  /**
   * Iterates though all the known member collections and calls 
   * "hydrateMemberCollection($collectionName) on each.
   */
  public function hydrateMemberCollections() {
    $memberCollections = static::$memberCollections;
    $keys = array_keys($memberCollections);
    foreach ($keys as $collName) {
      //foreach (array_keys(static::$memberCollections) as $collName) {
      $this->hydrateMemberCollection($collName);
      //$this->$collName = $this->hydrateMemberCollection($collName);
    }
    //pkdebug("Leaving to Hydrate Collections..collections: THIS", $this);
  }

  /**
   * Takes a member collection name of this object and populates it.
   * ASSUMES all collection objects are in the same namespace as this
   * @param String $collName: The name of the collection attribute
   */
  public function hydrateMemberCollection($collName) {
    if (!in_array($collName, array_keys($this->getMemberCollections()))) {
      throw new \Exception("Collection: [$collName] not found in " . get_class($this));
    }
#protected $memberCollections = array('cells'=>array('classname'=>'ChartCell','foreignkey'=>'chart_id'));
    $classname = (static::$memberCollections[$collName]['classname']);
    $foreignkey = static::$memberCollections[$collName]['foreignkey'];
    $namespace = $this->getNamespaceName();
    if ($namespace) {
      $fullClassName = $namespace . '\\' . $classname;
    } else {
      $fullClassName = $classname;
    }
    //pkdebug("hydrateMemberCollection: collname: $collName, foreignkey: $foreignkey, className: $classname");
    //$this->$collName = $classname::getOBjectArray($foreignkey, $this->id);
    $collection = $fullClassName::getOBjectArray($foreignkey, $this->id);
    if (!$collection) {
      $collection = array();
    }
    $this->$collName = $collection;
    //pkdebug("Leaving hydrateMemberCollection: collection::",$collection, "THIS", $this);
  }

  /*   * CANCELLED: Don't bother keeping member objects with this object, can just
   * ::get() them from the ID.#########################
   *  Takes the name of a member object, and either an instance
   * of that member object, or the unique ID of that member object,
   * and sets both the member_object member and member_object_id
   * of the current class
   * @param $objectName -- the name of the member object, as an attribute name
   * of this object -- that is, baseName without Namespace. Usually the table
   * name.  If so, it is converted to a class name by removing underscores.
   * @param $value -- an instance of that object, or an ID integer
   * @param $objectType -- optional - in case the member object name isn't
   * the same as the class name (camelCased, converted to table_name)
   */

  /*
    public function setFieldObject($objectName, $value, $objectType = null) {
    if (!$objectType) {
    $objectType = toCamelCase($objectName, true);
    $tableName = $objectName;
    } else {
    $tableName = unCamelCase($objectType);
    }
    $fullTypeName = static::getNamespaceName().'\\'
    .static::getBaseName($objectType);
    $field_id = $tableName . '_id';
    if ($value instanceOf $fullTypeName) {
    if ($id = $value->getId()) { #Restrict only to persisted objects? For now...
    $this->$tableName = $value;
    $this->$field_id = $id;
    } else if (is_numeric($value) && ($id = intval($value))) {
    $obj = new $fullTypeName($id);
    if ($obj->id) { #It exists and is persisted
    $this->$tableName = $obj;
    $this->$field_id = $id;
    }
    }
    }
    }
   * *
   */

  /**
   * TODO: Consider including Collection fields?
   * To support Zend Framework 2 requirements for Form interaction.
   * Takes data from the input array and sets this objects direct members
   * from the array. Individual subclasses will want to extend/add to this
   * with setting member attributes NOT mapped directly to the table
   * 
   * Changed from Zend Framework 2 model of setting $obj->attr to null
   * even if $arr['attr'] doesn't exist.
   */
  public function exchangeArray($data) {
    $this->makeDirty();
    $directFields = static::getDirectFields();
    foreach ($directFields as $directField) {
      $direct_field = unCamelCase($directField);
      /*
        if (isset($data[$direct_field])) {
        $this->$directField= $data[$direct_field];
        }
       */
      $this->$directField = (!empty($data[$direct_field])) ?
              $data[$direct_field] : null;
    }
  }

  /**
   * As above, to support Zend Framework 2 Form interaction
   * @return Array: The array equivalent of the object data attributes
   */
  public function getArrayCopy() {
    $retarr = array();
    $directFields = static::getDirectFields();
    foreach ($directFields as $directField) {
      $direct_field = unCamelCase($directField);
      $retarr[$direct_field] = $this->$directField;
    }
    return $retarr;
  }

  /** Takes a field object name, returns that field object if it is
   * already initialized (hydrated). If not set, checks the underlying
   * table field integer to see if that is set -- if so, initializes
   * the external object and returns it.
   * 
   * Typically, the field id will map to the object name and object class -
   * but might not always map to class name if there is more than one instance
   * external object class -- like, "Father" and "Mother" might both be
   * instances of the "Person" class. So allow for that possibility.
   * 
   * The current convention is that the self object attribute that corresponds
   * to the external class/object is uncamelcased, without the "_id" appended.
   * For example, if the external class/object is "ShoppingCart", the convention
   * is that the property name in the current object will be "shopping_cart",
   * which points to an external "ShoppingCart" instance/object. But in the DB
   * table, we keep the field "shopping_cart_id".
   * 
   * Also, the DB table that corresponds to the "ShoppingCart" class and
   * underlying field name "shopping_cart_id" will be called "shopping_cart".
   * 
   * And in the case of "Mother", the 'this' table field would be mother_id, 
   * the member object would be "mother", and the class would be "Person"
   * @param type $objectName -- normally maps to field_id
   * @param type $objectType (only if the object name of the member is different
   *   from the related Class/Object/Table name
   * 
   * @return type -- the object or false
   */
  //TODO! check this
  public function getFieldObject($objectName, $objectType = null) {
    if (!$objectType) {
      $objectType = toCamelCase($objectName, true);
      $tableName = unCamelCase($objectName);
    } else
      $tableName = unCamelCase($objectType);

    $field_id = unCamelCase($objectName) . '_id';
    if ($this->$field_id) {
      $obj = $objectType::get($this->$field_id);
      if ($obj instanceOf $objectType) {
        return $obj;
      }
    }
    return false;
  }

  /** Magic Set.
   * 
   * @param type $name
   * @param type $value
   * @throws \Exception
   */
  public function __set($name, $value) {
    $className = get_class($this);
    $uccName = unCamelCase($name);
    if (in_array($name, static::$memberDirects)) { //it's a member direct'
      $this->$name = $value;
    } else if ((in_array($name, array_keys(static::$memberObjects)) &&
            ($value instanceOf BaseModel) || !$value)) {
      #TODO: Check the object is of the correct class
      ## Removed keeping the object itself; just the ID. This means it is not
      #possible to add a member object that hasn't been persisisted to the DB
      #yet, so may want to rethink this later.
      $field_name = unCamelCase($name) . "_id";
      if (!property_exists($className, $field_name)) {
        throw new Exception("Bad class definition: No property [$field_name]
          defined for member object [$name] in class [$className]");
      }
      if ($value) { //Get the ID
        $objId = $value->getId();
        $this->$field_name = $objId;
      } else { //Clearing property
        unset($this->$field_name);
      }
      return;
    } else if (in_array($name, array_keys(static::$memberCollections))) {
      if (is_array($value) &&
              ((!sizeof($value) || !$value) ||
              ($value[0] instanceOf
              static::$memberCollections[$name]['classname']))) {
        #Value either an appropriate collection, or empty & clear
        $this->$name = $value;
      }
    } else {
      throw new \Exception("Trying to set unavailable attribute [$name] on [$className] ");
    }
    $this->makeDirty(1);
  }

  /**
   * 
   * @param type $name
   * @return type
   */
  public function __get($name) {
    #check if the property exists in our class
    $className = get_class($this);
    if (in_array($name, static::$memberDirects)) { //it's a member direct'
      return $this->$name;
    }
    if (in_array($name, array_keys(static::$memberObjects))) { //it's a member object'
      $field_id = $this->objectNameToFieldId($name);
      /*
        if (isset($this->$name)) {
        return $this->$name;
        } else */
      if (isset($field_id)) {
        return $this->getFieldObject($name, static::$memberObjects[$name]);
      }
    } else if (in_array($name, array_keys(static::$memberCollections))) {
      return $this->$name;
    }
    throw new \Exception("In class [$className]; couldn't get member [$name]");
  }

  /**
   * get properties. Abstracts in case we want to change how we view
   * private properties
   */
  public function getVars() {
    return get_object_vars($this);
  }

  /**
   * Returns an array of attribute names
   * and values for this object, only of 
   * "direct" fields.
   * @return Array: Of Key=>Value pairs
   */
  public function getDirectVars() {
    $directProperties = static::getDirectFields();
    $objVars = $this->getVars();
    $ret = array();
    foreach ($objVars as $key => $value) {
      if (in_array($key, $directProperties)) {
        $ret[$key] = $value;
      }
    }
    return $ret;
  }

  //$class = get_class($obj);
  //$objvars = $class::getDirectFields();
  public function save() {
    pkdebug("Entering SAVE, THIS IS:", $this);
    $baseName = static::getBaseName();
    //$table = unCamelCase(get_class($this));
    $table = unCamelCase($baseName);
    $obarr = createArrayFromObj($this, $this->exclude);
    //pkecho("ObjArr:", $obarr);
    $obarr = saveArrayToTable($obarr, $table);
    if (!$obarr || !is_array($obarr) || empty($obarr['id'])) {
      throw new \Exception("Something wasn't set right for save: obarr: [" . pkvardump($obarr) . "]; THIS: " . pkvardump($this));
      //return false;
    }
    $this->id = $obarr['id'];
    if (!isset(static::$instantiations[$baseName])) {
      static::$instantiations[$baseName] = array();
    }
    static::$instantiations[$baseName][$this->id] = $this;
    //if ($this instanceOf Profile) {
    pkdebug("Before Save,  this:", $this);
    //}

    $this->saveCollections();
    $this->makeDirty(0);
    return $this;
  }

  public function saveCollections() {
    foreach (array_keys(static::$memberCollections) as $collName) {
      $this->saveCollection($collName);
    }
  }

  /**
   * Saves a collection of objects belonging exclusively to $this object.
   * If $this no longer contains any of these objects, they should be
   * deleted from the database.
   * 
   * TODO! Do we always want to delete collections from the DB if the items
   * are no longer members of this object? Investigate...
   * 
   * @param string $fieldname -- the collection of objects belonging to this
   * @param string $className -- the type in the collection
   * @param string $foreignKey -- they key in the objectType table pointing to this
   * @return PDOStatemjent or false;
   */
  public function saveCollection($fieldname) {
    $thisClassName = get_class($this);
    if (is_string($fieldname) && property_exists($this, $fieldname)) {
      $objArr = $this->$fieldname;
    } else {
      throw new \Exception("Collection [$fieldname] not in [$thisClassName]");
    }
    if (empty($objArr) || !sizeof($objArr)) {
      return false;
    }
    if (!$this->getId())
      $this->save();
    if (!$this->getId()) {
      throw new \Exception("Couldn't save 'this' and get an id");
    }
    $ids = array();
    //pkdebug("In saveCollection Pre, objArr:",$objArr);
    if (empty(static::$memberCollections[$fieldname]) ||
            empty(static::$memberCollections[$fieldname]['classname'])) {
      throw new \Exception("No classname found for this collection class Class: "
      . get_class($this) . ", for collection [$fieldname]");
    }
    $baseCollClassName = static::$memberCollections[$fieldname]['classname'];
    $foreignKey = static::$memberCollections[$fieldname]['foreignkey'];

    $tableName = unCamelCase($baseCollClassName);
    foreach ($objArr as $obj) {
      $obj->$foreignKey = $this->id;
      $obj->save();
      $ids[] = intval($obj->id);
    }

    #Saved all objects in collection, now delete from DB Table all collection
    #instances that are no longer part of this collection. 
    #here, we are not deleting them from the instantiations cache...

    $ret = idxArrayToPDOParams($ids);
    $paramStr = $ret['paramStr'];
    $argArr = $ret['argArr'];
    $argArr['owner'] = $this->id;

    $strSql = "DELETE FROM `$tableName` WHERE `$foreignKey` = :owner AND  " .
            " `id` NOT IN ($paramStr)";
    pkdebug("About to delete prof_jobs? strSql: [$strSql], argArr:", $argArr);
    $stmt = prepare_and_execute($strSql, $argArr);
    if (!$stmt)
      return false;
    return $stmt;
  }

  /**
   * Magic Method to return a string representation of the object.
   * Have to catch exceptions that may occur, because __toString may
   * not throw exceptions itself.
   * 
   * @return String: If this [classname] has a member field called:
   * [classname]_name, return that, else just a string saying "No Name"
   */
  public function __toString() {
    try {
      $className = get_class($this);
      $baseName = $this->getBaseName();
      $fieldName = unCamelCase($baseName) . "_name";
      if (isset($this->name)) {
        return $this->name;
      }
      if (isset($this->$fieldName)) {
        return $this->$fieldName;
      }
      return "No Name for this $baseName";
    } catch (\Exception $e) {
      return "Exception: " . $e->getMessage();
    }
  }

/** Retrieves a persisted object from the db -- only requires $this->id
 * 
 */
  public static function hydrate() {
    $className = get_class($this);
    if (!($id = $this->getId())) {
      throw new Exception ("Trying to hydrate [$className] without an ID");
    }
    //$baseName = $this->getBaseName();
    //$table_name = unCamelCase($baseName);

    $dataArr = $this->getTableRow();

  }

  /**
   * Returns the array of objects match the value of the key
   * @param string $key - the table field name, object member name of the one side
   * @param string $val - The value to be matched
   * @return array  - array of matching object instances
   */
  public static function getOBjectArray($key, $val) {
    $params = array($key => $val);
    return static::getObjectsThatMatch($params);
    //pkdebug("Getting ObjArray for Key:", $key,"VAL", $val);
    /*
      $id = 0;
      if (is_numeric($val) && intval($val)) {
      $id = intval($val);
      } else if (is_object($val) && property_exists($val, 'id')) {
      $id = $val->id;
      }
      if (!$id) {
      throw new \Exception("Trying to get data wihout a valid ID, VAL: " . pkvardump($val));
      }
     * 
     */
    /*
      $class = get_called_class();
      $baseName = static::getBaseName();
      $tableName = unCamelCase($baseName);
      if (property_exists($class, $key)) {
      $strSql = "SELECT `id` FROM `$tableName` where `$key` = :$key";
      $stmt = prepare_and_execute($strSql, array($key => $val));
      if (!$stmt)
      return false;
      $resarr = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if (!is_array($resarr) || !sizeof($resarr)) {
      return false;
      }
      $ids = array();
      $objarr = array();
      //pkdebug("About to loop through resarr:",$resarr);
      foreach ($resarr as $res) {
      $obj = $class::get($res['id']);
      if ($obj instanceOf $class)
      $objarr[] = $obj;
      }
      //pkdebug("Finished loop with ObjArr:",$objarr);
      return $objarr;
      }
     * 
     */
  }

  /** Newera-- accounts for collections and pre-axisting objs */
  /*
    public static function makeObjFromArray($arr) {
    if (empty($arr['id'])) {
    $i;
    }
    }
   * *
   */

  /**
   * Populates an object with data from the array
   * TODO: Kind of duplicates ->exchangeArray($data) but not exactly yet,
   * need to refactor them together eventually
   * @param Array $arr: Array of raw data, to populate object attributes
   * @param Boolean $recursive: Go down into collections as well?
   * @param Array $exclude: Field names to skip/exclude
   * @return \BaseModel
   */
  public function populateFromArray($arr, $recursive = true, $exclude = array()) {
    $this->makeDirty();
    $directFields = static::getDirectFields();
    $colls = static::$memberCollections;
    //pkdebug("COLLS:", $colls, 'THIS', $this);
    foreach ($arr as $key => $val) {
      #Skip fields to exlcude
      if (in_array($key, $exclude)) {
        continue;
      }
      #Just set "direct fields"
      if (in_array($key, $directFields)) {
        $this->$key = $val;
      }
      //pkdebug("IN populateFromArray; input array:", $arr, "key:", $key, "Colls:", $colls, "THIS",$this);
      #If recursive, populate collections as well...
      if ($recursive && in_array($key, array_keys($colls))) {
        $this->$key = array();
        #if (is_array($subarr = $arr[$key])) {
        if (is_array($val)) {
          $foreignKey = $colls[$key]['foreignkey'];
          //pkdebug("IN populateFromArray; subarr:",$subarr, "Colls:", $colls, "ForeignKeh:", $foreignKey);
          foreach ($val as $subel) {
            if (empty($subel[$foreignKey]) && (!empty($this->id) || !empty($arr['id']))) {
              $subel[$foreignKey] = $this->id ? $this->id : $arr['id'];
            }
            $res = $this->$key;
            $namespace = $this->getNamespaceName();
            $collClassName = $namespace . '\\' . $colls[$key]['classname'];
            $res[] = $collClassName::get($subel);
            //$res[] = $colls[$key]['classname']::get($subel);
            //$this->$key[] = $colls[$key]['classname']::get($subel);
          }
          $this->$key = $res;
          pkdebug("PopFromArray: THIS:", $this, "RES:", $res);
        }
      }
    }
    return $this;
  }

  /**
   * Returns an array of objects of this class which match the parameters. The
   * parameters are just an array of key=>value pairs of simple select criteria,
   * just based on the underlying table; no joins.
   * <p>Does an SQL query on the underlying table to get an array of ID's,
   * then uses ::get($id) to retrieve the individual objects
   * @param Array $params: key=>value pairs of table select criteria
   * @param null|String $orderBy: If present, field name to order by
   * @return: Array: Array of matching objects, if any
   */
  public static function getObjectsThatMatch(Array $params, $orderBy = 'id') {
    //$tableName = unCamelCase(get_called_class());
    $baseName = static::getBaseName();
    $tableName = unCamelCase($baseName);
    $argArr = pre_prepare_data($params);
    $queryStr = $argArr['queryString'];
    $paramArr = $argArr['paramArr'];
    //pkdebug("input Params:",$params, "Ret from pre_prep", $argArr);
    $fullQS = "SELECT `id` FROM `$tableName` WHERE $queryStr";
    if ($orderBy && is_string($orderBy)) {
      //Can't paramaterize field names, so check orderBy in table:
      if (!doesFieldExist($orderBy, $tableName)) {
        throw new \Exception("No field [$orderBy] in table: [$tableName]`");
      }
    }
    $fullQS .= " ORDER BY `$orderBy` ";
    $stmt = prepare_and_execute($fullQS, $paramArr);
    $resarr = $stmt->fetchAll();
    $retarr = array();
    foreach ($resarr as $resrow) {
      $retarr[] = static::get($resrow['id']);
    }
    //pkdebug("Leaving objmatch::retarr:",$retarr,'resarr',$resarr, 'params',$params,'fullQQS',$fullQS,'argarr',$argArr);
    return $retarr;
  }

  /** Gets the raw data from the underlying table
   * 
   */
  public function getTableRow() {
    $id = $this->getId();
    if (!$id) {
      throw new Exception("No ID");
    }
    $class = get_class($this);
    $rowArr = $class::getRowsThatMatch(array('id'=>$id));
    if (!is_array($rowArr) || !sizeof ($rowArr))  {
      return false;
    }
    return $rowArr[0];
  }

  /**
   * Retrieves rows from a table that match the parameters
   */
  public static function getRowsThatMatch(Array $params, $orderBy = 'id') {
    $baseName = static::getBaseName();
    $tableName = unCamelCase($baseName);
    $argArr = pre_prepare_data($params);
    $queryStr = $argArr['queryString'];
    $paramArr = $argArr['paramArr'];
    //pkdebug("input Params:",$params, "Ret from pre_prep", $argArr);
    $fullQS = "SELECT * FROM `$tableName` WHERE $queryStr";
    if ($orderBy && is_string($orderBy)) {
      //Can't paramaterize field names, so check orderBy in table:
      if (!doesFieldExist($orderBy, $tableName)) {
        throw new \Exception("No field [$orderBy] in table: [$tableName]`");
      }
    }
    $fullQS .= " ORDER BY `$orderBy` ";
    $stmt = prepare_and_execute($fullQS, $paramArr);
    $resarr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $resarr;
  }

  public static function getNamespaceName($class = null) {
    if (!$class) {
      $fullName = get_called_class();
    } else if (is_string($class)) {
      $fullName = $class;
    } else if (is_object($class)) {
      $fullName = get_class($class);
    }
    $strrpos = strrpos($fullName, '\\');
    if (!$strrpos) { //That is, either 0 OR FALSE, maybe different later?
      return '';
    }
    $namespace = substr($fullName, 0, $strrpos);
    return $namespace;
  }

  public static function getBaseName($class = null) {
    if (!$class) {
      $className = get_called_class();
    } else if (is_string($class)) {
      $className = $class;
    } else if (is_object($class)) {
      $className = get_class($class);
    }

    if (strrchr($className, "\\") === false) {
      return $className;
    }
    return substr(strrchr($className, "\\"), 1);
  }

#Close Class Brace
}

###
#####   ########
#####
##  END of BaseModel Class Definition ##################################################
#############################################################################################################
#######################  START of PKMVC ORM Helper Functions  ##################################################
/**
 * Populates an object's member variables with the contents of 
 * the given array, excluding field names in the optional $exclude array
 * @param type $obj - The object to populate
 * @param type $arr - The array to populate it from
 * @param type $exclude -- An array of field names to exclude from the population
 */
/*
  function populateObjectFromArray($obj, $arr, $exclude = array()) {
  foreach ($arr as $key => $val) {
  if (in_array($key, $exclude))
  continue;
  if (property_exists($obj, $key))
  $obj->$key = $val;
  }
  return $obj;
  }
 * *
 */

/**
 * Creates an array from an object's member variables,
 * excluding those fields in the exclude array.
 * @param type $obj - The object to populate from
 * @param type $exclude -- An array of field names to exclude from the population
 */
function createArrayFromObj($obj, $exclude = null) {
  $arr = array();
  $objvars = $obj->getDirectVars();
  foreach ($objvars as $key => $val) {
    if (in_array($key, $exclude))
      continue;
    $arr[$key] = $val;
  }
  return $arr;
}

/**
 * Persists an array to a table, using array keys as field names,
 * excluding any field names in the exclude array
 * Assumes primary key is 'id', so updates or inserts, depending
 * returns the array, with new ID added if record inserted
 * 
 * @param Array &$inarr: The input array of data, by reference. If the object
 * hasn't been persisted yet ($inarr['id'] not set), a new entry is made in the
 * table and 'id' is set for inarr.
 */
function saveArrayToTable(&$inarr, $table, $exclude = array()) {
  if (!empty($inarr['id']) && !intval($inarr['id']))
    return false;#bad ID
  $arr = array();
  foreach ($inarr as $key => $value) {
    if (!in_array($key, $exclude)) {
      $arr[$key] = $value;
    }
  }
//pkecho ("inarr",$inarr,"arr",$arr);
//die();
  $db = getDb();
  $paramSet = prePrepareDataForUpdateOrInsert($arr, true); //true means, omit ID
  $paramstr = $paramSet['queryString'];
  $paramArr = $paramSet['paramArr'];
//return array('queryString'=>$retstr, 'paramArr' => $retarr);
  if (empty($arr['id'])) {
    $id = 0;
    $sqlStr = "INSERT INTO `$table` SET $paramstr";
  } else {
    $id = intval($arr['id']);
    $paramArr['id'] = $id;
    $sqlStr = "UPDATE `$table` SET $paramstr WHERE `id` = :id";
  }
  unset($arr['id']);
//pkecho("SQLSTR & PARAMARR:", $sqlStr, $paramArr);
  $stmt = prepare_and_execute($sqlStr, $paramArr);
  if (!$stmt instanceOf PDOStatement)
    return false;
  if (!$id)
    $id = $db->lastInsertId();
  $arr['id'] = $id;
  foreach ($arr as $key => $value) {
    $inarr[$key] = $value;
  }
//pkdebug("Leaving saveArrayToTable, inarr:", $inarr);
  return $inarr;
}

/**
 * Returns an array of table results based on key
 * 
 * @param $params: empty, int ID, or array of query params
 * @param type $table
 * @param type $orderBy: field name to order by, or null
 */
//function getArraysFromTable($table, $value=null, $key = 'id', $orderBy = null) {
/*
  //TODO: This function has the wrong name -- look more closely to see if needed later
  function getArraysFromTable($table, $params = null, $orderBy = null) { // $value=null, $key = 'id', $orderBy = null) {
  if (is_numeric($params) && intval($params)) {
  $params = array('id'=>intval($params));
  } else if (!is_array($params)) {
  $params = null;
  }
  $paramstr = '';
  $paramArr = array();
  if (is_array($params) && !empty($params)) {
  $paramSet = pre_prepare_data($params);
  $paramstr = $paramSet['queryString'];
  $paramArr = $paramSet['paramArr'];
  }
  //return array('queryString'=>$retstr, 'paramArr' => $retarr);
  if (empty($arr['id'])) {
  $id = 0;
  $sqlStr = "INSERT INTO `$table` SET $paramstr";
  } else {
  $id = intval($arr['id']);
  $sqlStr = "UPDATE `$table` SET $paramstr WHERE `id` = $id";
  }
  unset($arr['id']);
  $stmt = prepare_and_execute($sqlStr, $paramArr);
  if (!$stmt instanceOf PDOStatement)
  return false;
  if (!$id)
  $id = $db->lastInsertId();
  $arr['id'] = $id;
  foreach ($arr as $key => $value) {
  $inarr[$key] = $value;
  }
  return $inarr;
  }
 * *
 */

/**
 * Returns an array of table results based on key/value parameters, or all
 * A simple single-table select query
 * 
 * @param string|object $objectOrClassName: If name, can be with Namespace
 *   or without
 * @param $params: empty, int ID, or array of query params. If empty, return all
 * @param type $orderBy: field name to order by, or null
 */
function getArraysFromTable($objectOrClassName, $params = null, $orderBy = 'id') { // $value=null, $key = 'id', $orderBy = null) {
  if (!$orderBy) {
    $orderBy = 'id';
  }
  if ($objectOrClassName instanceOf BaseModel) {
    $fullClassName = get_class($objectOrClassName);
  } else if (is_string($objectOrClassName)) {
    $fullClassName = $objectOrClassName;
  } else {
    throw new \Exception("Invalid Model Class or Name: [$objectOrClassName]");
  }
//$namespace = BaseModel::getNamespaceName($fullClassName);
  $baseName = BaseModel::getBaseName($fullClassName);
  $table_name = unCamelCase($baseName);
  if (is_numeric($params) && intval($params)) {
    $params = array('id' => intval($params));
  } else if (!is_array($params)) {
    $params = null;
  }
  $paramStr = '';
  $paramArr = array();
  if (is_array($params) && !empty($params)) {
    $paramSet = pre_prepare_data($params);
    $paramStr = $paramSet['queryString'];
    $paramArr = $paramSet['paramArr'];
  }
  if (!$table_name || !is_string($table_name) || !doesTableExist($table_name)) {
    throw new \Exception("Bad table reference: [$table_name]");
  }
  if (!doesFieldExist($orderBy, $table_name)) {
    throw new \Exception("No field [$orderBy] in table: [$table_name]`");
  }
  $sqlstr = "SELECT * FROM `$table_name`";
  if ($table_name && $paramStr) {
    $sqlstr .= " WHERE $paramStr ";
  }
  $sqlstr .= " ORDER BY `$orderBy` ";
  $stmt = prepare_and_execute($sqlstr, $paramArr);
  if (!$stmt) {
    throw new \Exception("Problem with query string [$sqlstr]");
  }
  $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return $res;
}

//// Parameter preparation functions

/**
 * 
 */

/**
 *  Takes an associative array of field names and values and
 * creates an array of a string for PDO paramterized WHERE...AND clauses
 * and the array of input values. NOTE: This paramArr will be identical
 * to the input parameter array, UNLESS $omitId is true, in which case
 * it will be the input array with the ID field removed.
 * 
 * @param array $data -- input associative array of field names & values
 * NOTE: Data Key names MUST BE THE SAME AS TABLE FIELD NAMES (or camel cased)
 * @param String $omitId -- if true, remove ID from the parameter list
 * or null for general string to which update or insert needs to be
 * prepended
 * #@return String: In the form of: "`key1`=:key1 AND `key2`=:key2....".
 * @return Array: 'queryString'=>String: In the form of: "`key1`=:key1 AND `key2`=:key2....".
 *                'paramArr' => $paramArr
 */
function pre_prepare_data(Array $data, $omitId = null) {
  $retstr = '';
  $retarr = array();
  foreach ($data as $key => $value) {
    if ($omitId && ($key == 'id'))
      continue;
    $key = unCamelCase($key);
    $retstr .= " `$key` = :$key AND ";
    $retarr[$key] = $value;
  }
  $retstr .= " 1 = 1 ";
//$retstr = substr($retstr, 1);
//$retstr = substr($retstr, 1);
  return array('queryString' => $retstr, 'paramArr' => $retarr);
}

/**
 * In contrast to pre_prepare_data (above) which prepares a parameter array
 * for use in a SELECT WHERRE clause (separated by 'AND's), this prepares
 * values for use in insert/update, separated by commas.
 * @param array $data
 * @param Boolean $omitId: Should the ID field be ommited from the insert/update
 * @return Array: 'queryString'=>String: In the form of: "`key1`=:key1, `key2`=:key2,...".
 *                'paramArr' => $paramArr
 */
function prePrepareDataForUpdateOrInsert(Array $data, $omitId = null) {
  $retstr = '';
  $retarr = array();
  foreach ($data as $key => $value) {
    if ($omitId && ($key == 'id'))
      continue;
    $key = unCamelCase($key);
    $retstr .= ", `$key` = :$key  ";
    $retarr[$key] = $value;
  }
//$retstr .= " 1 = 1 ";
  $retstr = substr($retstr, 1);
//$retstr = substr($retstr, 1);
  return array('queryString' => $retstr, 'paramArr' => $retarr);
}

/**
 * For an indexed array, creates key names for PDO statments. Really just to
 * parameterize IN clauses...

 * @param type $array a numeric array of values
 * return 2 element array -- a comma separated string of parameter keys, and the
 * argument array of key/value pairs
 */
function idxArrayToPDOParams($array) {
  if (empty($array) || !is_array($array)) {
    return false;
  }
  $paramArr = array();
  $argArr = array();
  foreach ($array as $key => $value) {
    $newKey = "key_" . $key;
    $paramArr[] = ":" . $newKey;
    $argArr[$newKey] = $value;
  }
  $paramStr = implode(',', $paramArr);
  $ret = array('paramStr' => $paramStr, 'argArr' => $argArr);
  return $ret;
}

/** Check if a field exists in a table */
function doesFieldExist($field, $table) {
  $field = unCamelCase($field);
  $table = unCamelCase($table);
  $db = getDb();
  $dbName = getDatabaseName();
  $strSql = " SELECT * FROM `information_schema`.`COLUMNS`
WHERE TABLE_SCHEMA = '$dbName'
AND TABLE_NAME = :table_name
AND COLUMN_NAME = :field_name";
  $queryArr = array("table_name" => $table, 'field_name' => $field);
  $stmt = prepare_and_execute($strSql, $queryArr);
  $res = $stmt->fetchAll();
  if (empty($res) || !sizeof($res) || !isset($res[0][0])) {
    return false;
  } else {
    return true;
  }
}

/**
 * 
 * @param type $tableName -- can be table or class name
 */
function doesTableExist($tableName) {
  $tableName = unCamelCase($tableName);
  if (!$tableName || !is_string($tableName))
    return false;
  $dbName = getDatabaseName();
  $strSql = " SELECT `table_name` FROM `information_schema`.`TABLES`
WHERE TABLE_SCHEMA = '$dbName'
AND TABLE_NAME = :table_name";
  $queryArr = array("table_name" => $tableName);
  $stmt = prepare_and_execute($strSql, $queryArr);
  $res = $stmt->fetchAll();
  if (empty($res) || !sizeof($res) || !isset($res[0][0])) {
    return false;
  } else {
    return true;
  }
}

function getDatabaseName() {
  $db = getDb();
  $sql = "SELECT DATABASE()";
  $stmt = $db->query($sql);
  if (!$stmt instanceOf \PDOStatement) {
    $errorInfo = pkvardump($db->errorInfo(), false);
    throw new \Exception("Problem with query: [$sql], errorInfo: [$errorInfo]");
  }
  $res = $stmt->fetchAll();
  if (!is_array($res) || empty($res[0]) || empty($res[0][0]) || !is_string($res[0][0])) {
    throw new \Exception("Problem retrieving DB name with query: [$sql]");
  }
  return $res[0][0];
}

/** Simple utilities to convert the convention of an externally mapped
 * object name in the class to the underlying table field name, which is
 * de-camelcased object name . '_id'. No checking, just string conversion
 * 
 * A model member function checks the actual existance of these members
 */
function fieldIdToObjectName($field_id) {
  $endsWith = '_id'; //Make sure it ends in _id
  if (!(substr($field_id, -strlen($endsWith)) == $endsWith)) {
    return false;
  }
  $objectName = toCamelCase(substr($field_id, 0, -strlen($endsWith)));
  return $objectName;
}

function objectNameToFieldId($objectName) {
  $endsWith = '_id'; //Make sure it ends in _id
  return unCamelCase($objectName) . $endsWith;
}

/** Takes a mysql string with no parameters, or named or '?' for placeholders, and input which is
 * a single (string) value, or array of values, prepares the statement, and
 * executes it.
 * @param type $stmntstr
 * @param type $params
 * @return FALSE if failure, or the result statement
 */
if (!function_exists('prepare_and_execute')) {

  function prepare_and_execute($stmntstr, $params = null) {
    if (!is_string($stmntstr)) {
      throw new \Exception("Invalid Statement String: [ $stmntstr ]");
// return ("Invalid Statement String: [$stmntstr]");
    }
    if (!is_array($params)) {
      $params = array($params);
    }
    $success = true;
    $db = getDb();
    $stmt = $db->prepare($stmntstr);
    if ($stmt instanceOf PDOStatement) {
      $success = $stmt->execute($params);
      if (!$success) {
        $errorInfo = pkvardump($stmt->errorInfo(), false);
      }
    } else {
      $errorInfo = pkvardump($db->errorInfo(), false);
      $success = false;
    }
    if (!$success) {
      $debugDumpParams = pkcatchecho(array($stmt, 'debugDumpParams'));
      throw new \Exception("DB error in prepare_and_execute;"
      . "Error and debug output:\n\n$errorInfo\n\n$debugDumpParams");
// return false;
    }
    return $stmt;
  }

}
