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