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
/** The Base Controller & Wrapper
 *  Paul Kirkaas
 */

Class ControllerWrapper {
  protected $controller;
  public function __construct($controller = null, $args = null) {
    if ($controller instanceOf BaseController) {
      $this->setController($controller);
    } else if(is_string($controller) && class_exists($controller.'Controller')) {
      $controllerName = $controller."Controller";
      $this->setController(new $controllerName($args));
    }
  }
  

  public function getController() {
    return $this->controller;
  }

  public function setController(BaseController $controller) {

    $controller -> wrapper = $this;
    $this->controller = $controller;
  }

  public function __call($methodName, Array $argArr = array()) {
    if (empty($this->controller)) { 
      throw new Exception('No controller');
    }
    if (!is_callable(array($this->controller,$methodName))) {
      throw new Exception(get_class($this->controller).
      " doesn't implement $methodName");
    }


    $controllerName = get_class($this->controller);
    $controllerRoot =  MVCLib::endsWith($controllerName,BaseController::CONTROLLER);
    $act = '';
    $resarr=array();
    if ($base = MVCLib::endsWith($methodName,BaseController::ACTION)) {
        $methodRoot = $base;
        $act = $resarr['act'] = BaseController::ACTION;
    }
    if ($base = MVCLib::endsWith($methodName, BaseController::PARTIAL)) {
        $methodRoot = $base;
        $act = $resarr['act'] = BaseController::PARTIAL;
    }
    //$argArr['method']=$methodName;

    if ($act) {
      $this->controller->setTemplate("$controllerRoot/$methodRoot");
      $resarr['act'] = $act;
    }
    $resarr['preExecute'] =
        call_user_func_array(array($this->controller,'preExecute'),$argArr);
    //$argArr['preExecute'] = $resarr['preExecute'];
    //$resarr['actionResult']=call_user_func_array(array($this->controller,$methodName),$argArr);
    $resArr['actionResult']=call_user_func_array(array($this->controller,$methodName),$argArr);

    $argArr['actionResult'] = $resArr['actionResult'];
    $resArr['postExecute']=call_user_func_array(array($this->controller,'postExecute'),$argArr);
    return $resArr['actionResult'];

    #If method ends in Action, do the pre/post execute, else just execute & return
    return call_user_func_array(array($this->controller,$methodName),$argArr);
  }
}

class BaseController {
  const ACTION = "Action";
  const PARTIAL = 'Partial';
  const CONTROLLER = 'Controller';
  public $wrapper; #The wrapper object
  public $viewRenderer;
  public $templates = array();
  public $template;
  public static $layout = 'layout';

  public static function getLayout() {
    return static::$layout;
  }
  #Only get through get - to return the wrapper
  public static function get($args=null){
    $class = get_called_class();
    $controller = new $class($args);
    $wrapper = new ControllerWrapper($controller);
    return $wrapper;
  }

  public static function getControllerName() {
    $cclass = get_called_class();
    return substr($cclass,-1*strlen('Controller'));
  }
  public function setTemplate($template) {
    $this->template = $template;
  }
  public function getTemplate() {
    return $this->template;
  }

  public function preExecute($args = null) {
    /*
    pkecho ($args);
    if (!empty($args['template'])){ 
      $this->templates[$args['methodName']]=$args['template'];
    } //else if (empty($this->templates[$args['methodName']])) {
     * *
     */
      //$this->templates[$args['methodName']] = 


  }

  public function postExecute($args = null) {
  }



  //Make sure only get through "get" -- to return wrapper
  protected function __construct($args=null) {
  }

  public function getActionName() {
  }

}

/*
class IndexController extends BaseController {
  public function indexAction() {
    $rearr = array();
    $comp = array();
    $args = array('4', '7', 'hello dolly');
    for ($i = 0; $i < 4; $i++) {
      $args['key'] = $i;
      $comp[] = Base::exec('chart','test',$args);
    }
    $resarr['comp']= $comp;
    $resarr['msg']= "Who's the hero now?";
    return $resarr;
  }
}
*/

class LayoutController extends BaseController {
  public function layoutAction($controller,$action,$args) {
    #return array("content" => "Such a shortcut!");
    return array('content'=>Base::exec($controller,$action,$args));

  }
   
}

