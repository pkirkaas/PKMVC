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
/** Renders templates based on Controller data */
namespace PKMVC;
use PKMVC\BaseController;
Class ViewRenderer {
  /**
   * @var Array of CSS paths to be included
   */
  protected static $csss = array();
  /**
   * @var Array of JS Paths to be included
   */
  protected static $jss = array();

  public $controller;
  public $template; #A string that leads to a file in the form 'controller/view'
  public $data; //Data to render in template

  /**
   * @var String -- Same name as instance member/attribute. Allowed? No complaint
   */
  //public static $templateRoot;
  public static $defaultTemplateRoot;
  public $templateRoot;

  #This section adds & manages CSS & JS files, removes dups, etc
  /**
   * Add a CSS include to the array of includes, if it doesn't already exist.
   * @param string $css: Adds the CSS file to the list/array
   */
  public static function addCss($css) {
    if (in_array($css, static::$csss))  {
      return;
    }
    static::$csss[] = $css;
  }

  /** Sets the static CSS array totally
   * 
   * @param Array $csss: CSS Path array
   */
  public function setCsss($csss = array()) {
    static::$csss = $csss;
    return static::$csss;
  }

  public function getCsss() {
    return static::$csss;
  }

  /**
   * As above with CSS - adds a JS filepath if it doesn't already exist
   * @param string $js: JS File path
   * @return type
   */
  public static function addJs($js) {
    if (in_array($js, static::$jss))  {
      return;
    }
    static::$jss[] = $js;
    return static::$jss;
  }

  public static function setJss($jss = array()) {
    static::$jss = $jss;
    return static::$jss;
  }
  public static function getJss() {
    return static::$jss;
  }


  /** 
   * Returns a string for all CSS or JS includes, depending on $type.
   * Assumes the given path is either relative to the site root, or absolute
   * with full HTTP & URL.
   * 
   */
  public static function getIncludes($type) {
    if (!in_array($type,array('css','js'))) {
      throw new \Exception("Bad include type: [$type]");
    }
    $retStr = "\n";
    $varArr = $type.'s';
    if (!is_array(static::$$varArr) || empty(static::$$varArr)) {
      return false;
    }
    foreach (static::$$varArr as $item) {
      $pref = substr($item,4);
      if ($pref !== 'http') {
        $p = $item[0];
        if ($p != '/') {
          $item = '/'.$item;
        }
        $item = getBaseUrl().$item;
      }
      if ($type === 'css') {
        $retStr .= "<link type='text/css' rel='stylesheet' href='$item' />\n";
      } else {
        $retStr .= "<script language='javascript' src='$item'></script>\n";
      }
    }
    static::$$varArr = array();
    return $retStr;
  }

  public static function getCss() {
    return static::getIncludes('css');
  }
  public static function getJs() {
    return static::getIncludes('js');
  }

  /** Makes a menu based on the $menuArray -- see docs for format.
   * By default, based on a "bootstrap" based layout, but can be changed with
   * template
   * 
   * @param Array $menuArray
   * @param String $menuTemplate: Name of menu template without .phtml suffix
   * @return String -- Menu for bootstrap
   */
  /** Maybe not -- move to partial controller & slots...?
  public static function makeMenu($menuArray, $menuTemplate = null) {
    if (!$menuTemplate) {
      $menuTemplate = "default-menu";
    }
    $templatePath = static::$templateRoot.'/'.$menuTemplate.'.phtml';
    if (!file_exists($templatePath)) {
      throw new \Exception("Building menu; template [$templatePath] not found");
    }
    

  }
  */

  public function __construct(BaseController $controller = null,
          $templateRoot = null, $template = null) {
    $this->controller = $controller;
    if ($templateRoot) {
      $this->templateRoot = $templateRoot;
    } else if (isset(static::$defaultTemplateRoot)) {
      $this->templateRoot = static::$defaultTemplateRoot;
    } else {
    $this->templateRoot = BASE_DIR . '/templates';
    }

    if ($template) {
      $this->template = $template;
    }
  }

  public function setTemplateRoot($templateRoot) {
    $this->templateRoot = $templateRoot;
  }


  public function setTemplate($template) {
    $this->template = $template;
  }

  public function getTemplate() {
    return $this->template;
  }

  public function setData($data) {
    $this->data = $data;
  }

  public function getData() {
    return $this->data;
  }

  public function render($data=array(),$template = null) {
    if (empty($template)) {
      if (empty($data)) { 
        return '';
      }
      if ( is_string($data) ) {
        return $data;
      } else {
        throw new \Exception("No template in this ViewRenderer [" . class_name($this) . "]");
      }
    }
    $fpath = $this->getFileFromTemplateName($template);
    if (!file_exists($fpath)) {
      throw new \Exception("Template file [$fpath] not found");
    }
    if (is_array($data)) extract($data);
    ob_start();
    include ($fpath);
    $out = ob_get_contents();
    ob_end_clean();
    return $out;
  }

  /** The standard templateName would be "$controllerName/$actionName"
   * 
   * @param type $templateName
   * @return string full template file system path
   * @throws \Exception
   */
  public function getFileFromTemplateName($templateName) {
    $root = $this->templateRoot;
    $fpath = $root . "/$templateName" . '.phtml';
    if (!file_exists($fpath)) {
      throw new \Exception("Loading template [$templateName], File [$fpath] not found");
    }
    return $fpath;
  }

  #static methods and members

  /**
   * Takes an array of route path segments and returns a URL
   * @param array $segments: Indexed array of path components:
   * array('controllerName', 'actionName', 'argN...');
   * @return String
   */
  public static function makeUrl(Array $segments) {
    $url = getBaseUrl();
    foreach ($segments as $segment) {
      $url .= "/$segment";
    }
    return $url;
  }

  /**
   * Returns active or inactive for menu item class to show if current
   * @param array $segments: Idx array of route segments
   * @param int $cnt - How many segments to include to match?
   * Default: 2, for controller/action
   * @return string: active if match, else inactive
   */
  public static function activeRoute($segments, $cnt=2) {
    if (static::isActiveRoute($segments, $cnt)) {
      return "active";
    }
    return "inactive";
  }

  /**
   * Just used by activeRoute above to determine to return string active
   * @param type $segments
   * @param type $cnt
   * @return boolean: Does the route match or not?
   */
  public static function isActiveRoute ($segments, $cnt = 2 ) {
    $currentSegments = getRouteSegments(true);
    for ($i = 0 ; $i < $cnt; $i++) {
      if (($i < 2) && (!isset($segments[$i]) || !$segments[$i])) {
        $segments[$i] = 'index';
      }
      if ($segments[$i] != $currentSegments[$i]) {
        return false;
      }
    }
    return true;
  }


}

/**
 * Renders results into a template. Normally called with a the results of a
 * controller action, but can be used to render any data - the first argument
 * to the constructor ($result) is an associative array of key/value pairs,
 * the secocond is the name of a phtml template which presents the content of
 * the $result array with local variables that have the name of the array keys.
 * 
 * Important to note the instance of RenderResult does not evaluate the content
 * until it is echoed and the "__toString()" magic method is called. Which means
 * if a member of the "$result" array is an object which has a reference
 * elsewhere in the application, if some value of that object is changed AFTER
 * the RenderResult instance is created, but BEFORE it is actually rendered, the
 * output of RenderResult will reflect the modified value, not the value it was
 * created with. 
 */

class RenderResult {

  /**
   * @var Array: Associative array of key/value pairs, to be rendered into 
   * the template -- ONLY WHEN THE __toString() magic method is called...
   */
  public $result;
  /**
   * @var String: The name of the phtml template to be used in the rendering.
   */
  public $template;
  public $viewRenderer;

  public function __construct($result = null, $template = null, $templateRoot = null) {
    $this->result = $result;
    $this->template = $template;
  }

  public function __toString() {
    try {
      if (!$this->viewRenderer instanceOf ViewRenderer) {
        $this->viewRenderer = new ViewRenderer();
        //return $this->viewRenderer($this->result, $this->template);
        return $this->viewRenderer->render($this->result, $this->template);
      }
    } catch (\Exception $e) {
      pkdebug("Exception:", $e);
      $stack = \pkstack_base();
      $exceptionMsg = "Exception: ".$e->getMessage();
      return "<pre>".$exceptionMsg . "\n" . $stack . "</pre>";
    }
  }

}

/** Make an array of partials to pass to the view, or in fact ANY array of
 * elements that have a String representation (that is __toString if obj) --
 * AND CAN BE ECHOED IN ANY CONTEXT, output text, and beauty part is, the 
 * objects are ONLY EVALUATED WHEN OUTPUT -- so can add an object here, change
 * the content elsewhere, and only evaluated when echoed. I LOVE THIS SIMPLE
 * CLASS I INVENTED!
 */
class PartialSet extends \ArrayObject {
  public $separator = '';
  public function __construct($separator = '') {
    $this->separator = $separator;
  }
  public function __toString () {
    $str = ' ';
    foreach ($this as $item) {
      $str.= ' '.$item.$this->separator;
    }
    return $str;
  }

  /** Deep object copy. Can do something more clever with __clone() at some
   * point, but for now...
   * @return static: (that is, static in the sense of current class): copy of $this
   */
  public function copy() {
    return unserialize(serialize($this));
  }
}

  
