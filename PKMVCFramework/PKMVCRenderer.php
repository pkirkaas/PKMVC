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
Class ViewRenderer {

  public $controller;
  public $template; #A string that leads to a file in the form 'controller/view'
  public $data; //Data to render in template
  public static $templateRoot;

  public function __construct(BaseController $controller = null) {
    $this->controller = $controller;
    $this->templateRoot = BASE_DIR . '/templates';
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
        throw new Exception("No template in this ViewRenderer [" . class_name($this) . "]");
      }
    }
    $fpath = $this->getFileFromTemplateName($template);
    if (!file_exists($fpath)) {
      throw new Exception("Template file [$fpath] not found");
    }
    if (is_array($data)) extract($data);
    ob_start();
    include ($fpath);
    $out = ob_get_contents();
    ob_end_clean();
    return $out;
  }

  public function getFileFromTemplateName($templateName) {
    $root = $this->templateRoot;
    $fpath = $root . "/$templateName" . '.phtml';
    if (!file_exists($fpath)) {
      throw new Exception("Loading template [$templateName], File [$fpath] not found");
    }
    return $fpath;
  }

  #static methods and members
}

class RenderResult {

  public $result;
  public $template;
  public $viewRenderer;

  public function __construct($result = null, $template = null) {
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
    } catch (Exception $e) {
      pkdebug("Exception:", $e);
      return "Eception: ".$e->getMessage();
    }
  }

}

/** Make an array of partials to pass to the view */
class PartialSet extends ArrayObject {
  public $separator = ' ';
  public function __construct($separator = '') {
    $this->separator = $separator;
  }
  public function __toString () {
    //return "<h1>This is a real string</h1>";
    $str = ' ';
    foreach ($this as $item) {
      /*
        ob_start();
        echo $item;
        $tmpstr=ob_get_contents ();
        ob_end_clean();
      $str.= $tmpstr.$this->separator;
       * 
       */
      $str.= ' '.$item.$this->separator;
    }
    return $str;
  }
}

  
