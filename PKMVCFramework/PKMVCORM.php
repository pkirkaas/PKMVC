<?php

/**
 * PKMVC Framework 
 *
 * @author    Paul Kirkaas
 * @email     p.kirkaas@gmail.com
 * @link     
 * @copyright Copyright (c) 2012-2014 Paul Kirkaas. All rights Reserved
 * @license   http://opensource.org/licenses/BSD-3-Clause  
 */
require_once (__DIR__ . '/dbconnection.php');
require_once (__DIR__ . '/pklib.php');

/** Base Model for PKMVC ORM
 * properties used as follows:
 * public: Map directly to DB table fields
 * protected: Map to representation of other ORM objects or collectons
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

  protected $memberObjects = array(); #Array of names of member objects
  protected $memberCollections = array(); #Array of names of object collections
  protected $exclude = array(); #Members to exclude from direct maping to table fields
  private $dirty = 0; #Checks if object has been modified
  static $instantiations = array(); #Associative array of class names with

#arrays of ID's of object instantiations.

  /** Checks if an object is already loaded, if so, returns it, if not
   *  loads it and registers it, if no $id, creates it, and returns it.
   * @param type $id
   */
  public static function get($idOrArray = null) {
    /*
    echo "<p>In get:: idOrArray:</p>";
    var_dump($idOrArray);
     * 
     */

    $class = get_called_class();
        if ($class == 'Chart') {
        pkdebug("Entering GET with: $class, idOrAr:", $idOrArray);
        }
    if (empty($idOrArray)) {
      return new $class();
    }
    if (is_numeric($idOrArray)) {
      $id = intval($idOrArray);
    }
    if (is_array($idOrArray) && !empty($idOrArray['id'])) {
      $id = $idOrArray['id'];
    }
    if (empty($id)) {
      //pkdebug("Getting new class for array:", $idOrArray);
      return new $class($idOrArray);
    }
    if (!isset(static::$instantiations[$class])) {
      static::$instantiations[$class] = array();
    }
    /*
      if ($class == 'Chart') {
      pkdebug("About to check if object of Chart already exists...");
      }
     * 
     */
    $classarr = &static::$instantiations[$class];
        if ($class == 'Chart') {
    pkdebug("Checking obj in classarr:", $classarr,"with ID", $id, "&CLASS [$class] with instantiation:", static::$instantiations);
        }
    if (isset($classarr[$id])) {
        if ($class == 'Chart') {
        pkdebug("Should do update with: $class though we have idOrAr:", $idOrArray);
        }
      $obj = $classarr[$id];
      if (is_array($idOrArray)) {  //Existing object with new data -- so update
        pkdebug("About to call update with obj:", $obj);
        $obj->update($idOrArray);
      }
      return $obj;
    }
    if ($class == 'Chart') {
      pkdebug("In GET: No OBJ, so make new, with 'idOrArray:", $idOrArray);
    }
    $obj = new $class($idOrArray);
    //pkdebug("Just after NEW in GET, OBJ:", $obj);
    if (!($obj instanceOf $class)) {
      //return false;
      throw new Exception("Problem instantiating object of type [$class]");
    }
    $classarr[$id] = $obj;
    return $obj;
  }

  /** Updates an existing object with data from array
   *  TODO: Problem here if there is no key in the 
   * array -- was the field deleted? 
   * @param array $arr
   */
  public function update(Array $arr) {
    pkdebug("In UPDATE with ARR:", $arr);
    $directFields = $this->getDirectFields();
    foreach ($directFields as $directField) {
      $table_field = unCamelCase($directField);
      if (isset($arr[$table_field])) {
        $this->$directField = $arr[$table_field];
      }
    }
    $collections = $this->memberCollections;
    foreach ($collections as $collName => $collDetails) {
      $collArr = $this->$collName; #array of collection objects
      if (! isset( $arr[unCamelCase($collName)])) {
        continue;
      }
      $newData = $arr[unCamelCase($collName)];
      $collClass = $collDetails['classname'];
      $foreignKeyName = $collDetails['foreignkey'];
      $foreignKeyValue = $this->getId();
      $collIds = static::getIds($collArr);
      $arrayIds = array();
      foreach ($newData as $newDatum) {
        if (isset($newDatum['id'])) {
          $arrayIds[] = $newDatum['id'];
        }
      }
      //We have an array of ID's in the new arrays,
      //and an array of ID's from the objects in the original collection
      //Delete objs in the collection not in the array
      $todelete = array_diff($collIds, $arrayIds);
      foreach ($collArr as $idx => $collObj) {
        if (in_array($collObj->getId(), $todelete)) {
          $collObj->delete();
          unset($collObj);
          unset($collArr[$idx]);
        }
      }
      #Now we should have a sparse array of only the remaining objects. Create
      #new, filled in array, update the objects with the new data, finally,
      #create any new objects in the collection...
      $newColl = array();
      foreach ($collArr as $collItem) {
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
        pkdebug("Found a new Datum!", $newDatum);
        if (empty($newDatum['id'])) {
          $newDatum[$foreignKeyName] = $foreignKeyValue;
          $newObj = $collClass::get($newDatum);
          $newColl[] = $newObj;
        }
      }
      pkdebug("NEW COLL COLLECTION:", $newColl);
      pkdebug("THIS before assignment of new coll...:", $this);
      //$this->$collName = $newColl;
      pkdebug("BEFORE SAVE IN UPDATE!!", $this);
      $this->save();
      pkdebug("AFTER SAVE IN UPDATE!!", $this);
    }

  }

  /** Returns an array of ID's from an array of Model instances
   * 
   */
  public static function getIds(Array $objs) {
    $ids = array();
    foreach ($objs as $obj) {
      $ids[] = $obj->getId();
    }
    return $ids;
  }

  /** Delete from DB, remove from persistent array, delete collectins
   * 
   */
  public function delete() {
    $collectionNames = array_keys($this->memberCollections);
    foreach ($collectionNames as $collectionName) {
      foreach ($this->$collectionName as $object) {
        $object->delete();
      }
    }
    $id = $this->getId();
    if ($id) { 
      $table_name = unCamelCase(get_class($this));
      $paramArr = array('id' =>$id);
      $paramStr = "DELETE FROM `$table_name` WHERE `id` = :id";
      //$strSQL = "DELETE FROM `$table_name` WHERE `id` = $id";
      prepare_and_execute($paramStr,$paramArr);
      unset(static::$instantiations[get_class($this)][$id]);
    }
  }

  /** Returns the default value of member collections. No doubt
   * there is a better way to do this...
   */
  public static function getMemberCollections() {
    $myVars = get_class_vars(get_called_class());
    return $myVars['memberCollections'];
  }

  /** Queries the table with arguments in $args */

  /** Returns an array of matched objects */
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
   * no object/ORM
   */
  public static function getAsArrays($params = null, $orderBy = null) {
    $className = get_called_class();
    $table_name = unCamelCase($className);
    $retval = getArraysFromTable($table_name, $params, $orderBy); // $val, $field_name, $orderBy);
    return $retval;
  }

  /**
   * Creates the instance, based on $arg:
   * @param type $arg: Either null, int ID, or initializing array of data
   */
  public function isDirty() {
    return $this->dirty();
  }

  public function makeDirty($val = 1) {
    $this->dirty = $val;
  }

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
    throw new Exception("Couldn't find the property for [$memberName]");
  }

  /**
   * Returns all attribute names of the child instance
   * @return array of attributes (including private) of the child object
   */
  //Won't work for child private members!
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
   * Currently indicated by "public" visibility, but something
   * smarter later
   */
  public static function getDirectFields() {
    $publicFields = array();
    $reflector = new ReflectionClass(get_called_class());
    $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
    foreach ($properties as $property) {
      $publicFields[] = $property->getName();
    }
    return $publicFields;
  }

//Something is confused around here with getting the different kinds
//of attributes in different methods -- investigate!

  /** Returns the attributes for the given model property --
   * If a collection, the collection attributes, if an index to an
   * external instance of a model
   * 
   */
# This pair converts table field names to the object property they 
# represent, but also checks if both attributes are in this class
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

//As above, in revers
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
   * $return true/false
   */
  public function isTableField($name) {
    return doesFieldExist($name, get_class($this));
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
    return $this->memberCollections[$name];
  }

  public function getExternalObjectClassName($name) {
    if (in_array($name, array_keys($this->memberObjects))) {
      return $this->memberObjects[$name];
    }
    return false;
  }

  /** Just examines $this->memberCollections and returns an array
   * of all collection names.
   */
  public function getCollectionNames() {
    return array_keys($this->memberCollections);
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
        throw new Exception("Setting/Getting nothing");
      }
      $field_name = unCamelCase($memberName);
      $refProp = new ReflectionProperty($this, $field_name);
      if (!$refProp || !($refProp instanceOf ReflectionProperty) ||
              !$refProp->isPublic()) { //Not a public attribute, so give to __set/__get
        if ($pre == 'get') {
          return $this->__get($field_name);
        } else if ($pre == 'set') {
          return $this->__set($field_name, $val);
        }
        throw new Exception("Shouldn't get here: [$field_name]");
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
        throw new Exception ("No field to set/get in call to [$className]");
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
        throw new Exception ("Problem to [$pre] $memberName");
        break;
        case (is_string($type)): #We have an external Object
        break;
        case ($type === true): #Direct table field map
        break;
        default:
        throw new Exception("Trying to '$pre' non member [$name]");
        }
       * 
       */
      throw new Exception("Unknown method [$name] on class " . get_class($this));
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

  protected function __construct($arg = null) {
    $class=get_class($this);
    if ($class == 'Chart') { pkdebug("Creating a new chart with:",$arg);}
    if (empty($arg)) {
      return;
    }
    if (!is_array($arg)) {
      if (!is_numeric($arg) || !($arg = intval($arg)))
        return;
      $args = array('id' => $arg);
      $resarr = getArraysFromTable(unCamelCase(get_class($this)), $args);
      if (empty($resarr))
        return;
      $arg = $resarr[0];
    }
    if ($class == 'Chart') { pkdebug("About to populate from Array..");}
    $this->populateFromArray($arg);
    if ($class == 'Chart') { pkdebug("Now this is:", $this);}
    if (is_array($arg)) {
      $this->update($arg);
    }
    if ($class == 'Chart') { pkdebug("Just after update in __construct, Now this is:", $this, "and ARG", $arg);}

    /*
      if ($this instanceOf Chart) {
      pkdebug ("In construct, this is:", $this, "Arg is:", $arg, "ThisGetId:", $this->getId());
      }
     * *
     */
    if ($this->getId() && !empty($this->memberCollections)) {
      /*
        if ($this instanceOf Chart) {
        pkdebug ("Trying to Hydrate" );
        }
       * 
       */

      $this->hydrateMemberCollections();
    }
    if ($this instanceOf Chart) {
      //pkdebug ("Just Hydrated, leaving __contstruct with ... THIS", $this );
    }
//The child class may need to do additional initialization
  }

  public function hydrateMemberCollections() {
    $memberCollections = $this->memberCollections;
    $keys = array_keys($memberCollections);
    foreach ($keys as $collName) {
      //foreach (array_keys($this->memberCollections) as $collName) {
      $this->hydrateMemberCollection($collName);
      //$this->$collName = $this->hydrateMemberCollection($collName);
    }
    //pkdebug("Leaving to Hydrate Collections..collections: THIS", $this);
  }

  public function hydrateMemberCollection($collName) {
#protected $memberCollections = array('cells'=>array('classname'=>'ChartCell','foreignkey'=>'chart_id'));
    $classname = ($this->memberCollections[$collName]['classname']);
    $foreignkey = $this->memberCollections[$collName]['foreignkey'];
    //pkdebug("hydrateMemberCollection: collname: $collName, foreignkey: $foreignkey, className: $classname");
    //$this->$collName = $classname::getOBjectArray($foreignkey, $this->id);
    $collection = $classname::getOBjectArray($foreignkey, $this->id);
    $this->$collName = $collection;
    //pkdebug("Leaving hydrateMemberCollection: collection::",$collection, "THIS", $this);
  }

  /*   * Takes the name of a member object, and either an instance
   * of that member object, or the unique ID of that member object,
   * and sets both the member_object member and member_object_id
   * of the current class
   * @param $objectName -- the name of the member object, usually the table name
   *         if so, it is converted to a class name by removing underscores.
   * @param $value -- an instance of that object, or an ID integer
   * @param $objectType -- optional - in case the member object name isn't
   * the same as the class name (camelCased, converted to table_name)
   */

  public function setFieldObject($objectName, $value, $objectType = null) {
    if (!$objectType) {
      $objectType = toCamelCase($objectName, true);
      $tableName = $objectName;
    } else
      $tableName = unCamelCase($objectType);
    $field_id = $tableName . '_id';
    if ($value instanceOf $objectType) {
      if ($id = $value->id) { #Restrict only to persisted objects? For now...
        $this->$tableName = $value;
        $this->$field_id = $id;
      } else if (is_numeric($value) && ($id = intval($value))) {
        $obj = new $objectType($id);
        if ($obj->id) { #It exists and is persisted
          $this->$tableName = $obj;
          $this->$field_id = $id;
        }
      }
    }
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


    if ($this->$tableName) {
      return $this->$tableName;
    }
    if ($this->$field_id) { //We have an ID key, but not the instance
      $obj = new $objectType($this->$field_id);
      if ($obj instanceOf $objectType) {
        $this->$tableName = $obj;
        return $this->$tableName;
      }
    }
    return false;
  }

  public function __set($name, $value) {
    if ((in_array($name, array_keys($this->memberObjects)) &&
            ($value instanceOf Base))) {
      $this->setFieldObject($name, $value);
      $this->makeDirty(1);
    } else if (in_array($name, array_keys($this->memberCollections))) {
      if (is_array($value) &&
              (!sizeof($value) || ($value[0] instanceOf $this->memberCollections[$name]['classname'])))
        $this->$name = $value;
      $this->makeDirty(1);
    }
  }

  /**
   * 
   * @param type $name
   * @return type
   */
  public function __get($name) {
    #check if the property exists in our class
    $className = get_class($this);
    if (in_array($name, array_keys($this->memberObjects))) { //it's a member object'
      $field_id = $this->objectNameToFieldId($name);
      if (isset($this->$name)) {
        return $this->$name;
      } else if (isset($field_id)) {
        return $this->getFieldObject($name, $this->memberObjects[$name]);
      }
    } else if (in_array($name, array_keys($this->memberCollections))) {
      return $this->$name;
    }
    throw new Exception("In class [$className]; couldn't get member [$name]");
  }

  /**
   * get properties. Abstracts in case we want to change how we view
   * private properties
   */
  public function getVars() {
    return get_object_vars($this);
  }

  public function getPublicVars() {
    $class = get_class($this);
    $publicProperties = $class::getDirectFields();
    $objVars = $this->getVars();
    $ret = array();
    foreach ($objVars as $key => $value) {
      if (in_array($key, $publicProperties)) {
        $ret[$key] = $value;
      }
    }
    return $ret;
  }

  //$class = get_class($obj);
  //$objvars = $class::getDirectFields();
  public function save() {
    $table = unCamelCase(get_class($this));
    $obarr = createArrayFromObj($this, $this->exclude);
    //pkecho("ObjArr:", $obarr);
    $obarr = saveArrayToTable($obarr, $table);
    if (!$obarr || !is_array($obarr) || empty($obarr['id']))
      return false;
    $this->id = $obarr['id'];
    $class = get_class($this);
    if (!isset(static::$instantiations[$class])) {
      static::$instantiations[$class] = array();
    }
    static::$instantiations[$class][$this->id] = $this;
    $this->saveCollections();
    $this->makeDirty(0);
    return $this;
  }

  public function saveCollections() {
    foreach (array_keys($this->memberCollections) as $collName) {
      $this->saveCollection($collName);
    }
  }

  /**
   * Saves a collection of objects belonging exclusively to $this object.
   * If $this no longer contains any of these objects, they should be
   * deleted from the database.
   * 
   * @param type $fieldname -- the collection of objects belonging to this
   * @param type $className -- the type in the collection
   * @param type $foreignKey -- they key in the objectType table pointing to this
   */
  public function saveCollection($fieldname, $className = null, $foreignKey = null) {
    if (is_string($fieldname) && property_exists($this, $fieldname)) {
      //$objArr = $this->$objArr;
      $objArr = $this->$fieldname;
    } else {
      return false;
    }
    if (empty($objArr) || !sizeof($objArr)) {
      return false;
    }
    if (!$this->id)
      $this->save();
    if (!$this->id)
      return false;
    $ids = array();
    //pkdebug("In saveCollection Pre, objArr:",$objArr);
    if (!$className && ( empty($this->memberCollections[$fieldname]) ||
            empty($this->memberCollections[$fieldname]['classname']))) {
      throw new Exception("No classname found for this collection class Class: "
      . get_class($this) . ", for collection [$fieldname]");
    } else if (!$className) {
      $className = $this->memberCollections[$fieldname]['classname'];
      $foreignKey = $this->memberCollections[$fieldname]['foreignkey'];
    }

    $tableName = unCamelCase($className);
    foreach ($objArr as $obj) {
      $obj->$foreignKey = $this->id;
      $obj->save();
      $ids[] = intval($obj->id);
    }

    $ret = idxArrayToPDOParams($ids);
    $paramStr = $ret['paramStr'];
    $argArr = $ret['argArr'];
    $argArr['owner'] = $this->id;

    $strSql = "DELETE FROM `$tableName` WHERE `$foreignKey` = :owner AND  " .
            " `id` NOT IN ($paramStr)";
    $stmt = prepare_and_execute($strSql, $argArr);
    if (!$stmt)
      return false;
    return $stmt;
  }

  public function __toString() {
    try {
      $className = get_class($this);
      $fieldName = unCamelCase($className) . "_name";
      if (isset($this->name))
        return $this->name;
      if (isset($this->$fieldName)) {
        return $this->$fieldName;
      }
      return "No Name";
    } catch (Exception $e) {
      return "Eception: " . $e->getMessage();
    }
  }

  /**
   * Returns the array of objects match the value of the key
   * @param type $key - the table field name, object member name of the one side
   * @param type $key - the table field name, object member name of the one side
   */
  public static function getOBjectArray($key, $val) {
    //pkdebug("Getting ObjArray for Key:", $key,"VAL", $val);
    $id = 0;
    if (is_numeric($val) && intval($val)) {
      $id = intval($val);
    } else if (is_object($val) && property_exists($val, 'id')) {
      $id = $val->id;
    }
    if (!$id) {
      throw new Exception("Trying to get data wihout a valid ID, VAL: " . pkvardump($val));
    }
    $class = get_called_class();
    $tableName = unCamelCase($class);
    if (property_exists($class, $key)) {
      $strSql = "SELECT `id` FROM `$tableName` where `$key` = :$key";
      $stmt = prepare_and_execute($strSql, array($key => $id));
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

  public function populateFromArray($arr, $recursive = true, $exclude = array()) {
    $class = get_class($this);
    $directFields = $class::getDirectFields();
    $colls = $this->memberCollections;
    //pkdebug("COLLS:", $colls, 'THIS', $this);
    foreach ($arr as $key => $val) {
      if (in_array($key, $exclude)) {
        continue;
      }
      if (in_array($key, $directFields)) {
        $this->$key = $val;
      }
      //pkdebug("IN populateFromArray; input array:", $arr, "key:", $key, "Colls:", $colls, "THIS",$this);
      if ($recursive && in_array($key, array_keys($colls))) {
        $this->$key = array();
        if (is_array($subarr = $arr[$key])) {
          $foreignKey = $colls[$key]['foreignkey'];
          //pkdebug("IN populateFromArray; subarr:",$subarr, "Colls:", $colls, "ForeignKeh:", $foreignKey);
          foreach ($subarr as $subel) {
            if (empty($subel[$foreignKey]) && (!empty($this->id) || !empty($arr['id']))) {
              $subel[$foreignKey] = $this->id ? $this->id : $arr['id'];
            }
            $res = $this->$key;
            $res[] = $colls[$key]['classname']::get($subel);
            //$this->$key[] = $colls[$key]['classname']::get($subel);
            $this->$key = $res;
          }
        }
      }
    }
    return $this;
  }

}

/**
 * Populates an object's member variables with the contents of 
 * the given array, excluding field names in the optional $exclude array
 * @param type $obj - The object to populate
 * @param type $arr - The array to populate it from
 * @param type $exclude -- An array of field names to exclude from the population
 */
function populateObjectFromArray($obj, $arr, $exclude = array()) {
  foreach ($arr as $key => $val) {
    if (in_array($key, $exclude))
      continue;
    if (property_exists($obj, $key))
      $obj->$key = $val;
  }
  return $obj;
}

/**
 * Creates an array from an object's member variables,
 * excluding those fields in the exclude array.
 * @param type $obj - The object to populate from
 * @param type $exclude -- An array of field names to exclude from the population
 */
function createArrayFromObj($obj, $exclude = null) {
  $arr = array();
  //$objvars = $obj->getVars();
  //$objvars = get_class($obj)::getDirectFields();
  //$class = get_class($obj);
  //$objvars = $class::getDirectFields();
  $objvars = $obj->getPublicVars();

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
 * 
 * @param $params: empty, int ID, or array of query params
 * @param type $table
 * @param type $orderBy: field name to order by, or null
 */
//function getArraysFromTable($table, $value=null, $key = 'id', $orderBy = null) {
function getArraysFromTable($table, $params = null, $orderBy = null) { // $value=null, $key = 'id', $orderBy = null) {
  if ($table instanceOf BaseModel) {
    $table = get_class($table);
  }
  $table_name = unCamelCase($table);
  $db = getDb();
  if (is_numeric($params) && intval($params)) {
    $params = array('id' => intval($params));
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

  if (!$orderBy || !is_string($orderBy))
    $orderBy = 'id';

  if (!$table_name || !is_string($table_name) || !doesTableExist($table_name)) {
    throw new Exception("Bad table reference: [$table_name]");
  }
  if (!doesFieldExist($orderBy, $table_name)) {
    throw new Exception("No field [$orderBy] in table: [$table_name]`");
  }
  $sqlstr = "SELECT * FROM `$table_name`";
  if ($table_name && $paramstr) {
    $sqlstr .= " WHERE $paramstr ";
  }
  $sqlstr .= " ORDER BY `$orderBy` ";
  $stmt = prepare_and_execute($sqlstr, $paramArr);
  if (!$stmt) {
    throw new Exception("Problem with query string [$sqlstr");
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
 * creates strings for WHERE AND clauses
 * 
 * 
 * insert/update statmements, and parameter array
 * @param array $data -- input associative array of field names & values
 * NOTE: Data Key names MUST BE THE SAME AS TABLE FIELD NAMES (or camel cased)
 * @param String $omitId -- if true, remove ID from the parameter list
 * or null for general string to which update or insert needs to be
 * prepended
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
  if (!$stmt instanceOf PDOStatement) {
    throw new Exception("Problem with query: [$sql]");
  }
  $res = $stmt->fetchAll();
  if (!is_array($res) || empty($res[0]) || empty($res[0][0]) || !is_string($res[0][0])) {
    throw new Exception("Problem retrieving DB name with query: [$sql]");
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
