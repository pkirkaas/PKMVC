<?php
###
/**
 * PKMVC Framework 
 *
 * @author    Paul Kirkaas
 * @email     p.kirkaas@gmail.com
 * @link     
 * @copyright Copyright (c) 2012-2014 Paul Kirkaas. All rights Reserved
 * @license   http://opensource.org/licenses/BSD-3-Clause  
 */
namespace PKMVC;
/** Application Base Path */
/** Root of everything 
 */
Class ApplicationBase {
  public static $renderArr = array();
  public static $controllers = array();
  public static $depth = 0;
  //public static function exec($controller=null, $action=null, $args=null, $arg2=null, $arg3=null, $arg4=null) {
  //Can take extra arguments
  public static function exec(/*$controller, $action, $argN...*/) {
    $args = func_get_args();
    $controller = array_shift($args);
    $action = array_shift($args);

    if (!$controller) $controller = 'index';
    if (!$action) $action = 'index';
    $controllerName = $controller.BaseController::CONTROLLER;
    if (!class_exists($controllerName)) {
      throw new \Exception("Controller [$controllerName] Not Found");
    }
    $actionName = $action.BaseController::ACTION;
    $partialName = $action.BaseController::PARTIAL;
    if (method_exists($controllerName,$actionName)) {
      $methodName = $actionName;
    } else if (method_exists($controllerName,$partialName)) {
      $methodName = $partialName;
    } else {
      throw new exception ("Partial or Action [$action] " .
       "for Controller [$controllerName] not found");
    }
    //$controller = new ControllerWrapper(new $controllerName());
    $controller =  $controllerName::get();
    $result = call_user_func_array(array($controller,$methodName), $args);
    $template = $controller->getTemplate();
    $newResult = new RenderResult($result,$template);
    return $newResult;
  }
  
  public static function layout($controller=null, $action=null, $args=null) {
    $wrapper = LayoutController::get();
    $result = $wrapper->layoutAction($controller, $action, $args);
    $template = $wrapper->getLayout();
    $newResult = new RenderResult($result,$template);
    return $newResult;
  }
}

/**
 * The initializing object
 */
Class Application extends ApplicationBase {
  public $controller;
  public $action;
  public function __construct(Array $args = null) {
  }

  public function run( $action = null, $controller = null, $args=null) {
    $results = ApplicationBase::layout($controller, $action, $args);
    echo $results;
  }
}
