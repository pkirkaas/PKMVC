<?php
namespace PKMVC;
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
 * Description of PKMVCLib
 *
 * @author Paul Kirkaas
 */
class MVCLib extends PKMVCBase {
  /** Returns false if false, else the string with end removed */
  public static function endsWith($str,$test) {
    if (! (substr( $str, -strlen( $test ) ) == $test) ) {
      return false;
    }
    return substr($str, 0, strlen($str) - strlen($test));
  }
  
  //Returns the base action/partial name with Action/Parial removed
  public static function getMethodBase($methodName) {
    
  BaseController::PARTIAL;
  BaseController::ACTION;
}
  /** Gets the string string "controller/method" for tempaltes */
  public static function getDefaultTemplate($controllerName, $methodName) {


  }

  /**
   * Merges all input arrays, with each other and with
   * static::$globalHtmlAttributess, adds a special check for "data-*"
   * attributes, and returns true or false
   * @param String $attr: Attribute Name
   * @param Mixed $OptArgs: Any number of arguments, which can be strings
   * or indexed arrays of strings, or indexed arrays of indexex arrays
   * of strings...
   * 
   * @return boolean: Is the attribute acceptable in the context?
   */
  /*
  public static function isValidAttribute($attr ) {
    $args = func_get_args();
    array_shift($args);
    $validAttributes = array_flatten($args,static::$globalHtmlAttributes);
    if ( in_array($attr, $validAttributes)) {
      return true;
    }
    if (substr($attr,0,5) === 'data-') {
      return true;
    }
    return false;
  }
  */

  /** So ready for 5.4 and traits, but until then ... common function, but
   * the static arrays must be declared public to be accessable from here,
   * until traits...
   * Recurse up through inheretence hierarchy and merge static arrays of
   * the given attribute name. This is for use by ::getMemberDirects(),
   * ::getMemberObjects, ::getMemberCollections()... to support deep object/class
   * inheritence. For example, BaseModel has "memberDirects=array('id');".
   * Child class "BaseUser extends BaseModel" has "memberDirects=array('uname')"
   * Your child user class might be "MyUser extends BaseUser", and 
   * MyUser::memberDirects = array('myextrastuff');
   *
   * So MyUser::getMemberDirects() should return "array('id','uname','myextrastuff');
   * etc.
   *
   * This static function is used by the various "getMemberXXX()" functions, and
   * returns a merged array, with child definitions overriding base defs.
   * @param String class: The calling class
   * @param $attributeName String: the name of the attribute: memberDirects,
   *  memberObjects, or memberCollections (for now)
   * @param $idx Boolean: is the array type indexed or associative? Used for 
   *   merging strategy - $memberDirects are indexed, others assoc.
   * @return Array: Merged array of hierarchy
   */
   public static function getMemberMerged($class, $attributeName, $idx = false) {
     #First, build array of arrays....
     $retArr = array();
     //$class = get_called_class();
     $retArr[]=$class::$$attributeName; #Deliberate double $$
     while ($par = get_parent_class($class)) {
       if(! property_exists($par, $attributeName)) {
         break;
       }
      $retArr[]=$par::$$attributeName;
      $class=$par;
    }
    #Now merge. Reverse order so child settings override ancestors...
    $retArr = array_reverse($retArr);
    $mgArr = call_user_func_array('array_merge',$retArr);
    if ($idx) { #Indexed array, return only unique values. For 'memberDirects'
      #Mainly to save the developer who respecifies 'id' in the derived direct
      $mgArr = array_unique($mgArr);
    }
    return $mgArr;
   }

}
