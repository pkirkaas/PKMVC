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
namespace PKMVC;

/**
 * Wraps the base controller (& descendants), intercepts all actions so other 
 * methods can be called before and after.
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
  /**
   *
   * @var Array of keyed arrays containing HTML -- filled in by anyone who wants
   */
  public static $slots = array();

  /** Controllers normally only add content to their view templates. But by
   * filling "slots", anyone can insert arbitrary HTML, which can be retrieved
   * in any view. For example, a menu that appears above the controller/action
   * view, but should be created by the controller.
   * 
   * NOTE: If you populate a slot in a toString method (for debugging, say), and
   * display slots before the rendered content, the slot will be output before
   * it is filled by the content toString method, and so won't appear... 
   * 
   * @param array $keys: An indexed array of key names, to arbitrary depth, used
   * to index the array of slots. Like, array('controllername', 'submenu'). But
   * typically, only like "array('menu');", in which case the $keys arg can be
   * just the key string, eg: 'menu'.
  
   * @param String|toStringable $val: The HTML string or object with toString
   * method, to put in the slot
   */
  public static function setSlot($keys, $val = null) {
    if (is_string($keys)) {
      $keys = array($keys);
    }
    if (!is_array($keys)) { #That's weird...
      throw new \Exception("Bad key value");
    }
    //$subarr = &static::$slots;
    #Recursive function to fill the array to appropriate depth..
    //static::fillArray($subarr,$keys,$val);
    $slotval = new PartialSet();
    $slotval[] = $val;
    insert_into_array($keys,$slotval,static::$slots);
  }

  /** Like setSlot (above), but adds a value without overwriting. 
   * Uses a Partial Set, to allow objects to be gathered without evaluating
   * until rendering.
   * @param type $keys
   * @param type $val
   * @throws \Exception
   */
  public static function addSlot ($keys, $val = null) {
    if (is_string($keys)) {
      $keys = array($keys);
    }

    if (!is_array($keys)) { #That's weird...
      throw new \Exception("Bad key value");
    }
    //$subarr = &static::$slots;
    #Recursive function to fill the array to appropriate depth..
    //static::fillArray($subarr,$keys,$val);
    $slotContent = static::getSlot($keys);
    if ($slotContent instanceOf PartialSet) {
      $slotval = $slotContent;
    } else {
      $slotval = new PartialSet();
      if ($slotContent) {
        $slotval[] = $slotContent;
      }
    }
    $slotval[] = $val;
    insert_into_array($keys,$slotval,static::$slots);
  }
  /** Retrieves the value at the end of the key chain. If not
   * set, returns null. Do same here as set -- call recursive function...
   * @param array $keys: Sequential indexed array of key names, or string
   */
  public static function getSlot($keys) {
    if (is_string($keys)) {
      $keys = array($keys);
    }
    if (!is_array($keys)) { #That's weird...
      throw new \Exception("Bad key value");
    }
    $slotArr = static::$slots;
    $val = static::getArrayDepth($slotArr, $keys);
    return $val;
  }

  public static function renderSlot($keys, $class = "slot") {
    $res = static::getSlot($keys);
    if (!$res || !sizeof($res)) {
      return null;
    }
    return "<div class='$class'>$res</div>\n";
  }

  public static function getArrayDepth($slotArr, $keys) {
    if (empty($keys) || !sizeof($keys)) { #Shouldn't be here
      throw new \Exception("Empty Keys array");
    }
    $key = array_shift($keys);
    if (!isset($slotArr[$key])) { //Not set, done, return null;
      return null;
    }
    if (empty($keys) || !sizeOf($keys)) { #Hit bottom
      if (isset($slotArr[$key])) { 
        return $slotArr[$key];
      } else {
        return null;
      }
    } #keep trying...
    return static::getArrayDepth($slotArr[$key],$keys);
  }


  /**
   * Redirect to the specified route or none - optionally, pass on GET params
   * @param Array|String|Null $route: If empty, Home. If string, the controller
   * assuming "index" action. Otherwise, if array of strings, build full URL
   * @param boolean|String|Array $withGets: If false/empty, no gets. IF BOOLEAN true, the
   * current GET params. If String or Array, add the specified GETS
   * 
   * TODO: Run params through a "clean" function (urlencode)
   */
  public static function redirect($route = null, $withGets = null) {
    $url = getBaseUrl() .'/';
    $path = '';
    $getStr = '';
    if ($route) {
      if (is_string($route)) {
        $path = $route;
      } else if (is_array($route)) {
        $path = implode('/', $route);
      }
      if ($withGets) { #If true, current GETS; if string or array, with that
        if (is_string($withGets)) {
          if (!(substr($withGets,0,1)=='?')) {
            $getStr = '?'.$withGets;
          } else { #is a get string, already with '?'
            $getStr = $withGets;
          }
        } else if (is_array($withGets)) { #assoc array of get key/vals
          #$getStr = '?' . implode('&',$withGets);
          $getStr = '?';
          foreach ($withGets as $key=>$val) {
            $getStr .= "$key=$val&";
          }
          $getStr = substr($getStr, 0,-1);
        } else { #Just True? Use current gets...
          $getStr = '?'. $_SERVER['QUERY_STRING'];
        }
      }
    }
    $redirectUrl = $url.$path.$getStr;
    header("Location: $redirectUrl");
    return;
  }
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

  /**
   * Template path relative to the base template directory and without .phtml
   * extension.
   * By default 'controllerName/actionName', but for example could be just
   * 'menu', which would load the "TEMPLATEBASE/menu.phtml" file.
   * This is implemented by the ApplicationBase::exec method.
   * @param String $template: The template used by the current action, 
   */
  public function setTemplate($template) {
    $this->template = $template;
  }
  public function getTemplate() {
    return $this->template;
  }

  /** Does nothing in base controller, but any controller can define this
   * method, which will get called before each action in that controller
   * @param type $args
   */
  public function preExecute($args = null) {
  }

  /** Does nothing in base controller, but any controller can define this
   * method, which will get called after each action in that controller
   * @param type $args
   */
  public function postExecute($args = null) {
  }



  //Make sure only get through "get" -- to return wrapper
  protected function __construct($args=null) {
  }

  public function getActionName() {
  }
  
  /**
   * Conventionally, when a controller action presents a form, submitting that
   * form will return to the same action, with POST data. 
   * @param String|BaseModel $entity: The name of the "entity"/object/class --
   * like, 'user', or an instance of that object
   * @return BaseModel instance: The object updated by the Post data
   * TODO: (Or, with validation errors)
   * TODO: NOT SURE I LIKE THIS HERE....
   */
  public function processPost($entity, $entity_name = null) {
    if (is_string($entity)) {
      $obj = new $entity();
    } else if ($entity instanceOf BaseModel) {
      $obj = $entity;
      if (!$entity_name) {
        $entity_name = unCamelCase(get_class($entity));
      }
    } else {
      throw new \Exception ("Invalid Entity submitted to processPost");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') { //Save 
      $formData = array();
      if (isset($_POST[$entity_name])) {
        $formData[$entity_name] = $_POST[$entity_name];
        /*
        if (!$form) {
          $form = new BaseForm($obj);
        }
         * 
         */
        //Test Line
        //$form = new BaseForm();
        //$obj = $form->submitToClass($formData);
        $obj = BaseForm::submitToClass($formData);
        if (($obj instanceOf BaseModel) && ($obj->getId())) {
          $id = $obj->getId();
          pkdebug("ID: [$id]");
        }
        return $obj;
      }
    }
    return $obj;
  }

}


class LayoutController extends BaseController {
  protected $components = array();
  public function __construct($args = null) {
    parent::__construct($args);
    $this->setParams($args);
  }

  public function setParams($args = null) {
    if (!$args || !sizeof($args)) {
      return;
    }
    foreach ($args as $key=>$val) { #Check keys and do something...
      
    }
  }

  /** Specifies additional components to be included in the layout
   * -- like menus..
   * @param Array $component: one or more associative compound array of:
   * array('componentName'=>array('controllerName', 'actionName', $args);
   */
  public function setComponent(Array $component) {
    foreach ($component as $key => $compound) {
      $this->components[$key] = $compound;
    }
  }



  public function layoutAction($controller,$action,$args) {
    #return array("content" => "Such a shortcut!");
    $data = array();
    $data['content'] = ApplicationBase::exec($controller,$action,$args);
    $components = $this->components;
    #So absolutely NOT the way I want to leave this -- quick & dirty for now
    foreach ($components as $key => $compound) {
      $data[$key] = ApplicationBase::exec(array_shift($compound),
                                          array_shift($compound),
                                          array_shift($compound));
    }
    return $data;

  }
   
}

