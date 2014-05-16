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
/**
 * Description of PKMVCForm
 */

/** Maps control names to object properties
*/
namespace PKMVC;


/** Takes submitted post data and populates an object.
 * The map in the constructor only needs to include items
 * where the form element name doesn't match the default
 * table/object field name. For example, 'id'=>'product_id'.
 * 
 * For collections, the protected name of the collection in the 
 * class maps to a subarray with the same concept -- 
 * only map exceptions, like 'id' => 'item_id'
 */

class BaseForm {
  protected $map = array();
  protected $class;

  public function __construct($map = array()) {
    if ($map) {
      $this->map = $map;
    }
  }

  /** The $formData should be a clean array of data, with relevant
   * class names as the array keys to the data
   * @param array $formData
   * @return type array of saved objects
   */
  public function submitToClass(Array $formData) {
    /*
    echo "<p>In submitToClass; formData:<p>";
    var_dump($formData);
     * 
     */
    $results = array();
    $formData = htmlclean($formData);
    $classNames = array_keys($formData); 
    foreach ($classNames as $className) {
      /*
    echo "<p>Looping for class[$className]; classNames:<p>";
    var_dump($classNames);
       * 
       */
      $obj = $className::get($formData[$className]);
      /*
      $subPost = $formData[$className];
      $directFields = $className::getDirectFields();
      $collections = $className::getMemberCollections();
      //if (isset($subMap['id'])) {
      $id = $formData['id'];
      $obj = $className::get($id); //Retrieves existing object, or new
      //Do direct fields first
      foreach ($directFields as $directField) {
        if (isset($formDatformDatadirectField])) {
          $obj->$directField =$formData[$directField];
        }
       * 
       */
      $obj->save();
      $results[]= $obj;
    }
    return $results;
  }
}

/**
 * Returns a string containing two HTML input elements of the same name -- a hidden
 * input field followed by a check-box. The value of the hidden field will always
 * be set to the emmpty string. If the checkk box is checked, its value will
 * replace the value of the hidden field during a submmit. 
 * @param $name: Either a string representing tthte "name" value, or an array of HTML
 * attributes for the elements. 
 * @param $value: The value of the checkox. If empty, just One. The hidden will
 * always be the empty string.
 ^ @ return 
 */
#Should they be wrapped in a div?

function makeBooleanInput ($name, $checked = false, $value=null) {
  $defaultClass = 'boolean-checkbox';
  $defaultValue = '1';
  if (!is_array($name)) {
    if (!is_string($name) || empty($name)) {
      throw new Exception ("The first argument to to booleanInput() must either be
      a string with the name, or an array with 'name' as a key");
    }
    $name = array('name'=>$name, 'class' =>  $defaultClass,);# 'value'=$value);
  }
  if (!isset($name['name'])) {
    throw new Exception ("Attemmpt to create a Boolean Checkbox control without a name");
  }
  if (!isset($name['class'])) {
    $name['class'] = $defaultClass;
  }
  if (!isset($name['value'])) {
    if (is_null($value)) {
      $value = $defaultValue;
    }
    $name['value'] = $value;
  }
  $checkStr =  " <input type='checkbox' "; 
  $hiddenStr = " <input type='hidden' value='' name='".$name['name'] ."' />";
  if (!isset($name['checked'])) {
    if ($checked) {
      $name['checked'] = 'checked';
    }
  }
  foreach ($name as $key => $val)  {
    $checkStr .= " $key='".htmlspecialchars($val,ENT_QUOTES)."' ";
  }
  $checkStr .= " />";

  $retstr = $hiddenStr .' '.$checkStr;
  return $retstr;
}
