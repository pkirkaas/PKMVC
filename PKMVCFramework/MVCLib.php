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
 * Description of PKMVCLib
 *
 * @author Paul Kirkaas
 */
class MVCLib {
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
}

/**
 * Creates a select box with the input
 * @param $name - String - The HTML Control Name. Makes class from 'class-$name'
 * #@param $label - String - The label on the control
 * #@param $key_str - The key of the select option array element
 * #@param $val_str - The key for the array element to display in the option
 * @param $arr - Array - The array of key/value pairs
 * @param $selected - String or Null - if present, the selected value
 * @param $none - String or Null - if present, the label to show for a new
 *   entry (value 0), or if null, only allows pre-existing options
 * @return String -- The HTML Select Box
 **/

function makePicker($name,$key,$val,$arr, $selected=null, $none=null) {
#function makePicker($name, $arr, $selected=null, $none=null) {
  $select = "<select name='$name' class='$name-sel'>\n";
  if ($none) $select .= "\n  <option value=''><b>$none</b></option>\n";
  foreach ($arr as $row) {
    $selstr = '';
    if ($selected == $row[$key]) $selstr = " selected='selected' ";
    $option = "\n  <option value='".$row[$key]."' $selstr>".$row[$val]."</option>\n";
    $select .= $option;
  }
  $select .= "\n</select>";
  return $select;
}

