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
use \ArrayObject;

/** Takes submitted post data and populates an object(s).
 * 
 * By default, 
 * Assumes the "form" data is in the form of an array possibly 
 * multi-dimensional for objects with collections (eg, shopping cart with 
 * items)
 * 
 * For example, if the object is of class "ShoppingCart", the underlying
 * table will be "shopping_cart". The POST array will contain the keys:
 * shopping_cart[id], shopping_cart[name], etc. If the ShoppingCart class 
 * contains a collection member "items", the form/POST array will contain:
 * "shopping_cart[items][0][id], shopping_cart[items][0][price]...."
 * 
 * If the user deleltes all the items in the cart, "shopping_cart[items]" index
 * in the post array won't be set, so you might want to delete all the items
 * from the cart. 
 * 
 * However, another form might also manipulate the cart, but not deal with the
 * items at all, in which case the "[items]" index will also not be set, but
 * in this case you DON'T want to delete the items. 
 * 
 * We would like to have the same form handling code as much as possible,
 * however, so our convention is on forms that are meant to add/delete items,
 * create a hidden input name="shopping_cart[items]" value="".
 * 
 * This way, if the cart has been emptied, the "items" index will exist but be
 * empty, indicating deletion of all items in the cart. If there are items in 
 * the cart, those inputs will override the empty "items", and can be persisted.
 * 
 * And in case of another cart manipulation form that doesn't deal with items,
 * the items index will never be set, and therefore not deleted from the object.
 * 
 * BaseForm can be extended to include particular controls, and a dedicated
 * template.
 * 
 * The BaseForm class also tries to do some clever things to handle common
 * situations - like if the form maps to an object/class, you can instantiate
 * the base form with an object. In which case it will create a default
 * hidden input element with the name "objName[id]", set it to the value of the
 * object ID, and output it with the ->openForm() method.
 * 
 * IMPORTANT: A form can be a top-level form, or subform -- including 
 * scrolling one-to-many subform collection of repeating elements. 
 * 
 * To initialize a form in creation,($form = new BaseForm($argArray), 
 *  or by creating an empty ($form = new BaseForm())
 * and then calling $form->setAttributeVals($argArray), the $argArray accepts the 
 * following format:
 * An associative array of key values. The primare keys are any valid HTML
 * form attributes ('class', 'id', 'name', etc), as well as these additional
 * primary keys:
 * 'elements': An associative array of BaseElement names with BaseElement 
 * constructor arrays. Like: ... '
 * elements' = array('myelname' => array('input'=>'textarea', 'class'=>'txt-a',
 *   'placeholder'=>'Tell us about yourself', ....)
 * 
 * If a 'label' key is present, the control/element will be wrapped in a div
 * and the label will be a component.
 * 
 * If 'name_segment' is given, it will be used to build the control name with 
 * the 'name_segements' array that is passed automatically.
 * 
 * TODO::: First make forms and scrolling subforms work bound to objects/models,
 * then generalize with arbitrary names/values
 * 
 */

class BaseForm extends BaseFormComponent {
  /**
   *
   * @var String: The template to use with the particular form instance
   */
  protected $template = '';
  /**
   * @var BaseModel: Often, a form will be based on a BaseModel object. In
   * which case, set it and we do some assistance.
   */
  protected $base_object = null;
  /**
   *
   * @var String: If this form/subform is tied to a class/object - the className
   */
  protected $base_model = null;

  /**
   *
   * @var Boolean: Has this form been bound yet?
   */
  protected $bound = false;
  protected $el_tag = "form";
  protected static $classDefaultAttributes = array(
      'enctype'=>'multipart/form-data',
      'method' => 'POST',
      );
  protected $renderResult = null;

  /** An array of valid HTML FORM attributes - if they are included as param
   * keys, will be part of the form element attribute set..
   * This array shouldn't be accessed directly, accessed instead by the function
   * "static::isValidAttribute($attrName);" to account for the special cases of
   * 'data-XXX' attributes. isValidAttribute also checks a separate list of
   * HTML Global attributes which are not specific to form elements and not
   * in this array.
   */
  protected static $validAttributeNames = array (
      'action', 'enctype', 'method', 'name', 
  );

  /**
   * @var array: Names of key attributes from the initialization array that
   * direct members of this class, and assigned directly. 
   * 'type' here is ?
   * subform: default false, top level form, so have form open/close tags,
   * scrolling: default false; if true, repeating form, array of objs
   */
  protected static $instancePropertyNames = array(
       'subform', 'scrolling', 'template', 'base_object', 'type',
      'base_model',
      );

  /**
   * @var type Is this the top level form - that is, not a subform? 
   * The subform value can be boolean "true", in which case nothing automatic
   * is done. Or, if we are creating a subform baased on an object with a
   * collection property, the passed in arg value of subform="$collectionName",
   * in which case automatic stuff will be done, like creating a hidden input
   * with the foreign key name set to the baseObject id....
   */
  protected $subform = false;
  protected $scrolling = false;

  /**
   * @var boolean|String: If string, contains a copy of the rendered form as
   * a JS template, for scrolling forms
   */
  protected $js_template = false;

  /**
   *
   * @var array: $key=>$value pairs of form attributes & values (like,
   * class, action, method, etc)
   */
  protected $attributes = array(); 

  /**
   *
   * @var Array: An optional associative array of data to be rendered with the
   * associated template, if rendering directly from the form instance.
   */
  protected $renderData = array();

  /**
   * @var Array: Ass Array of PKMVC Element/Form Input elements as name=>$el
   * Elements may be atomic HTML elements, or subforms containing other elements
   * One of the BaseElement types you can add is input='html', which just
   * outputs the raw HTML as content - so you can add HTML like div's and
   * other content as elements, and maybe skip the need for a form template
   * 
   * A few special element types: 
   * 'input'=>'collection' & 'input'=>'object' are subforms/special elements.
   * 'collection' input is a repeating fieldset of elements corresponding to
   * a one-to-many collection (like items in shopping carts). By default
   * with delete and new buttons, the name-segment supplied to the collection
   * subform by default refers to the corresponding "collections" field name
   * of the parent model (with foreignkey and foreignclass defined),
   * and values will be default bound accordinly
   * 
   * An 'input'=>'object' subform will by default be transformed to a select
   * box, segment name relating to parent model memberObject definition
   * 
   * $elements_inst: PartialSet of the instances instantiated eleents. This
   * means with values set and collections filled in with whatever N values 
   * Initialized from base_object with bind().
   * 
   * $elements_prot: The prototype elements as they were created initially --
   * with values and collections if that's how they were created, else not.

   */
  protected $elements_inst = '';
  protected $elements_prot = '';

  /**
   * Returns a set of form elements, either the prototype/template elements,
   * or instantiated elements based on "bind()" data.
   * @param boolean $proto: Return the prototype elements, or instance elements?
   * @return PartialSet: elements/instances of BaseFormComponent
   */
  protected function &getElementsX($proto = false) {

    if ((!$this->elements_prot) || !sizeOf($this->elements_prot)) {
    if (get_class($this) == "PKMVC\\BaseForm") {
      //pkdebug("BASEFORM: Making new empty elements_prot, but existing elements_prot is:", $this->elements_prot, "EXISTING INST:", $this->elements_inst);
    }
      $this->elements_prot = new PartialSet();
    } else {
      //pkdebug("XXX existing elements_prot is:", $this->elements_prot);

    }
    if ($proto) {
      return $this->elements_prot;
    }
    if ((!$this->elements_inst) || !sizeOf($this->elements_inst)) {
      $this->elements_inst = $this->elements_prot->copy();
      //pkdebug("Copying elements_prot and assigning to elements_inst, elements_inst:",$this->elements_inst);
    }
    return $this->elements_inst;
  }

  protected function &getElementsInst($proto = false) {
    return $this->getElementsX($proto);
  }

  protected function &getElementsProto($proto = true) {
    return $this->getElementsX($proto);
  }

  protected function &getElementsDefaultX($proto = false) {
    if ($this->getElements(false) && sizeOf($this->getElements(false))) {
      return $this->getElements(false);
    }
    return $this->getElements(true);
  }

  public function setBaseObject($base_object) {
    $this->base_object = $base_object;
    if (is_object($base_object)) {
      $this->base_model = class_name($base_object);
    }
    return $this->base_object;
  }

  public function getBaseObject() {
    if (!($this->base_object) && $this->base_model){
      $base_model = $this->base_model;
      $this->base_object = $base_model::get();
    }
    return $this->base_object;
  }

  /** Overrides base method just for special treatment of ->base_object, 
   * (in case class name & not object makes new), then hands off to parent.
   * @param Array $args: The initialization args
   */
  public function setInstancePropertyVals($args) {
    if (isset($args['base_object'])) {
      $args['base_object'] = $this->returnObject($args['base_object']);
      $args['base_model'] = get_class($args['base_object']);
    }
    return parent::setInstancePropertyVals($args);
  }

  /** Takes the argument and returns it if instance of BaseModel, or tries
   * to create it if $obj is a string ClassName.
   * For sure going to have Namespace issues here. Namespaces in PHP suck...
   * @param String|BaseModel: A BaseModel instance or BaseModel class name
   * @return BaseModel: instance of a BaseModel derived class
   */
  public function returnObject($obj) {
    if ($obj instanceOf BaseModel) {
      return $obj;
    }
    if (is_string($obj)) {
      $className = toCamelCase($obj, true);
      if (!class_exists($className)) { #Bad Model Class
        throw new \Exception("Class [$className] not defined!");
      }
      $newObj = $className::get();
    } else {
      throw new \Exception("Bad argument type");
    }
    if (!$newObj instanceOf BaseModel) {
      throw new \Exception("base_object [$className] not instanceOf BaseModel!");
    }
    return $newObj;
  }

  public function getBaseModel() {
    if ($this->base_model) {
      return $this->base_model;
    } else if ($this->base_object && ($this->base_object instanceOf BaseModel)) {
      $this->base_model = get_class($this->base_object);
      return $this->base_model;
    } else {
      throw new Exception ("Could not determine BaseModel for Form");
    }
  }

  /**
   * Sets the attributes of the form, and the elements, if that key is set.
   * If the attribute key "name" is set, the value must either be a string,
   * or an instance of BaseModel, in which case the base class name of the 
   * instance will be used as the form name and root name of the elements, and
   * the base_object of the form will be set to the instance.
   * 
   * @param array $attributes: Associative array of attribute names/values:
   *   [elements]: An associative array of the input elements for the form
   *   [instance]: The BaseModel derived instance associated with the form.
   *       If there is no 'name' key in args, and if 'name' is not already set,
   *       the base class name of the instance will be used for the name of
   *       the form and the root of the element names in the form (for posting)
   *   [{attribute names}]: Other HTML Attribute names, to be included in the
   *        form tag
   *   [{otherAttributes}]: Any keys not otherwise matched are added to the 
   *        $this->otherAttributes array
   * 
   * If "elements" is a key, the value must be an assoc array of name/value
   * pairs, where the name key is the form element name and the value is either
   * an element instance or an array that can be used to create the element
   * @return \PKMVC\BaseForm
   */
  public function setAttributeVals(Array $args = array(), $exclusions = array(), $useDefaults = true) {
    static $count = 0;
    if (isset($args['subform'])) {
      $useDefaults = false;
    }
    parent::setAttributeVals($args, $exclusions, $useDefaults); #Takes care of the regular attributes

    #Set elements if present....
    if (isset($args['elements'])) {
      $elements = $args['elements'];
      foreach ($elements as &$element) {
        //$element['name_segments'] = $name_segments; //$this->getNameSegments();
        $element['name_segments'] = array("Hello", "Goodbye");

      }
      $this->addElementProto($elements);

    }
    return $this;
  }


  /** Returns an array of elements, as indexed by the element names
   * 
   * @return array: Associative array of elements with names as keys, as
   * would be seen by PHP $__POST
   */
  public function asArray($prot = false) {
    if ($prot) {
      $elements = & $this->elements_prot;
    } else {
      $elements = & $this->elements_inst;
    }
    $baseKeys = $this->getNameSegments();
    $retels = array();
    foreach ($elements as $element) {
      if (! ($element instanceOf BaseFormComponent)) {
        continue;
      }
      $name = $element->getName();
      if (!$name) {
        continue;
      }
      $keyarr = $element->getNameSegments();
      if (($keyarr && $retels) &&  arrayish_keys_exist($keyarr,$retels)) {
        throw new \Exception("Duplicate element from [$name]");
      }
      if (is_array($keyarr)) {
        insert_into_array($keyarr, $element, $retels);
      }
    }
    return $retels;
  }


  /**
   * If form based on underlying object, bind/set the values from the object
   * to the form element values. Copies the Protottype elements to the isntance
   * elements, sets the "$this->bound" to true, and recursively binds.
   * 
   * @param array|null |BaseModel $arg: If null, automatically bind to associated object
   * fields. If $data is an instantiated array, assume this is not auto form -
   * If an object, get the data array from that.
   * just try to match element names to data key/values
   */
  public function bind($src = null) {
    if ($this->elements_prot instanceOf PartialSet) {
      $this->elements_inst = $this->elements_prot->copy();
    } else {
      $this->elements_inst = new PartialSet();
      $this->elements_prot = new PartialSet();
    }
    $this->bound = true;

    $data = array();
    if (!$src ) { //|| ($arg instanceOf BaseModel)) {
      $baseObject =  $this->getBaseObject();
      if (! ($baseObject instanceOf BaseModel)) {
        pkdebug("Nothing to bind!");
        return;
      }
      $base_name = $baseObject->getTableName();
      $data[$base_name] = $baseObject;
    } else if ($src instanceOf BaseModel) {
      $base_name = $src->getTableName();
      $data[$base_name] = $src;
    } else if (is_array($src)) {
      $data = $src;
    } else if (is_a($src, BaseModel, true )) {#$src name of BaseModel Sublclass
      $emptyObj = new $src();
      $base_name = $emptyObj->getTableName();
      $data[$base_name] = $emptyObj; // ->getArrayCopy(); 
    } else {
      $msg = "Bad src to bind: [".print_r($src, true).']';
      // (Wait until PHP __toString can throw errors...
      // throw new \Exception("Bad src to bind: [".print_r($src, true).']');
      pkdebug("ERROR!!!! [$msg]");
      trigger_error($msg);
      die();
    }
    $elements = $this->getElementsInst();
    foreach ($elements as $key => &$element) {
      $element->bind($data);
    }
    return;
  }



  /** Initialize with a model object, or an assoc key/value array, or nothing.
   * If nothing, still sets the default values like method and enctype.
   * 
   * IF ARGS ARRAY IS GIVEN: Must include a 'name' key, which can be either
   * a string name, or an instance of BaseModel, in which case the object will
   * be stored with the form and the base class name will be used for both 
   * the form name, and the root of the element 'names', to set the keys
   * of the eventual "POST"/submission.
   * 
   * @param \PKMVC\BaseModel|Array $args: If assoc array, keys should
   * correspond to member attribute names. Can include array of elements
   * @return null;
   */
  public function __construct($args = array()) { //, $subform = false) {
    $this->elements_inst = new PartialSet();
    $this->elements_prot = new PartialSet();
    parent::__construct($args);
    #Set defaults if not set -- id field and submit button

    #Add default elements (hidden iD, Submit) if this is a top-level form
    #and if default elemments not specifically given or set to null...
    if (get_class($this) == get_class()) {#Called from BaseForm, not subclass
      $idEl = $this->getElementProto('id'); #Get the prototype element
      #If don't want default, set explicitly to null. Else, if undefined,
      #returns strict boolean false
      if ($idEl === false) {
        $className = $this->name;
        $id = '';
        if ($this->base_object) {
          $className = $this->base_object->getBaseName();
          $id = $this->base_object->getId();
        }
        $idEl = new BaseElement(array(
          'data-here'=>'auto-id', 
          'type'=>'hidden',
          'name'=>unCamelCase($className)."[id]", 'value'=>$id
        ));
        $this->setElementProto('id', $idEl, true);
      }
      $submitEl = $this->getElementProto('submit', true);
      #If don't want default, set explicitly to null. Else, if undefined,
      #returns strict boolean false
      if ($submitEl === false) {
        $submitEl = new BaseElement(array(
            'type' => 'submit',
            'name' => 'submit',
            'value' => 'Submit',
            'class' => 'submit button',
        ));
        $this->setElementProto('submit', $submitEl);
      }
    }
  }


  /** The $formData should be a clean array of data, with relevant
   * class names as the array keys to the data
   * 
   * IF THE FORM CONSISTS OF ADDITIONAL SUBFORMS AND REPEATING SUBFORMS, ONLY
   * THE TOP LEVEL FORM SHOULD EXECUTE THIS METHOD!
   * 
   * @param array $formData
   * @param string $action: method - what should be done with this data? 
   * Default: if empty/false, create object/save. 
   * @param boolean $save (default: true): Should the object be saved now?
   * In some cases, like user registration, might need additional processing 
   * before saving?
   * @return type array of saved objects
   */
  public static function submitToClass(Array $formData, $action=null, $save=true) {
    $results = array();
    $formData = htmlclean($formData);
    $classNames = array_keys($formData); 
    foreach ($classNames as $className) {
      $id = 0;
      if (isset($formData[$className]['id'])) {
        $id = $formData[$className]['id'];
      }
      $obj = $className::get($id);
      $obj->update($formData[$className]);
      if ($save) {
        $obj->save();
      }
      $results[]= $obj;
      //Debug....
      break;
    }
    return $obj;
  }

  /**
   * Return the open form string, based on attributes
   * If don't want to automatically echo the ID element, $echoId = false;
   * @param boolean $echoId: Should the openForm method automatically echo
   * a hidden ID element, and generate it if it's not already set?
   */
  public function openForm($echoId = true) {
    $attrStr = $this->makeAttrStr();
    $formTag = "\n<{$this->el_tag} $attrStr >\n";
    return $formTag;
  }

  /** Close tag, and default "submit" button if none set. Can negate outputting
   * any submit button by adding an empty/null element named "submit".
   * 
   * @return string:  HTML  form close tag, and submit button...
   */
  public function closeForm() { return "\n</{$this->el_tag}>\n"; }


  /**
   * Get form data as an associative array
   * @return array: Return the element values as named array, suitable for
   * updating an object (nested) or whatever data you are updating...
   */
  public function getAsArray() {
    $retarr = array();
    $retarr[$this->getName()] = $this->getValuesRecursive();
    return $retarr;
  }

  /**
   * Updates form element values with data array, with names as keys, and 
   * implements collections, etc.
   * @param array $data: The assoc array of data
   * (usu. from a model object, but whatever) to
   * update the values of the form
   */
  public function setAsArray(Array $data) {
  }
  public function addElementInst($key = null, $val=null, $proto=false) {
    return $this->addElementX($key, $val, $proto);
  }
  public function addElementProto($key = null, $val=null, $proto=true) {
    return $this->addElementX($key, $val, $proto);
  }
  public function setElementInst($key = null, $val=null, $proto=false) {
    return $this->addElementX($key, $val, $proto);
  }
  public function setElementProto($key = null, $val=null, $proto=true) {
    return $this->addElementX($key, $val, $proto);
  }

  /** Add a PKMVC Form element to the assoc array collection, as name=>object
   * pairs. Can be one at a time (if $key is a string) or multiple (if array)
   * @param String|Array $key: Either the string key name, or an array of keys
   * and values. Values can be either instances of BaseFormComponent
   * (eg, BaseForm or BaseElement) or arrays of values used to build an
   * element or subform.
   * @param BaseFormComponent|null $val: Individual BaseFormComponent
   * instance, or null if the $key parameter is an array
   */
  public function setElementX($key = null, $val=null, $proto=true) {
    return $this->addElementX($key,$val, $proto);
  }


  public function addElementX($key = null, $val=null, $proto=true) {
    if (!$key) {
      return $this->getElementsX($proto);
    }
    if ($proto) {
      $elements = & $this->elements_prot;
    } else {
      $elements = & $this->elements_inst;
    }

    #Is $key array of names/elements, or a string name, with an element value?
    #Either way, convert to array so we only deal with one method
    $setArr = array();
    if (is_array($key)) {
      $setArr = $key;
    } else if (is_string($key)) {
      $setArr[$key] = $val;
    } else if ($key instanceOf ElementInterface) {#Element w/o key, create one
      $setArr[max_idx($elements, true)] = $key;
    } else { #Bad key
      $keyType = typeOf($key);
      throw new \Exception("Bad key type [$keyType] to add Element value");
    }

    #Okay, have an array. But good element name/element value (El or arr) pairs?
    foreach ($setArr as $skey => $sval) {
      if (!is_string($skey) && !is_numeric($skey)) { #String -- or num?
        $skeyType = typeOf($skey);
        throw new \Exception("Bad key type [$skeyType] to add Element value");
      }
      if ($sval instanceOf BaseFormComponent) {#Is already el or subform...
        $elements[$skey] = $sval;
      } else if (is_array($sval)) { #Make element or subform from data array
        $sval['name_segments'] = $this->getNameSegments();
        #Make Element or FormSet:
        if (isset($sval['subform'])   || # { #Making a subform...
         (isset($sval['input']) && ($sval['input'] == 'formset'))) { #Make formset
          //$sval['class'] .= ' '.$sval['formset'];
          $sval['class'] .= ' formset';
          $elements[$skey] = new FormSet($sval);
        } else {
          $elements[$skey] = new BaseElement($sval);
        }
      } else { #Bad element value 
        throw new \Exception("Bad El [".print_r($sval,1)."] for Key: [$skey]");
      }
    }
    if (get_class($this) == "PKMVC\\BaseForm") {
      //pkdebug("THIS ELEMENTS_PROT:", $this->elements_prot, "THIS ELEMENTS_INST:", $this->elements_inst,"ELEMENTS:", $elements);
    } else {
     // pkdebug("Class of this: [".get_class($this)."]");
    }
    /*
     * 
     */
    return $elements;
  }

  public function setTemplate($template) {
    return ($this->template = $template);
  }
  public function getTemplate() {
    return $this->template;
  }
  public function setRenderResult(RenderResult $renderResult) {
    return ($this->renderResult = $renderResult);
  }
  public function getRenderResult() {
    return $this->renderResult;
  }

  /**
   * If the instance has an instantiated ::$renderResult member, or $resultData
   * and $templateMembers, render.
   */
  public function __toString() {
    try {
      if ($this->getRenderResult()) {
        return $this->renderResult;
      }
      if ($this->template) {
        if (!($this->renderResult)) {
          $this->renderResult = new RenderResult($this->renderData, $this->template);
        }
        return $this->renderResult;
      }
      #No template, no renderResult - output default, which is all elements  
      #BUT -- if it is topLevel form, output open & close tags as well....
      $elements = $this->getElementsInst();
      return $this->openForm().$elements.$this->closeForm();
    } catch (Exception $e) {
      $msg = $e->getMessage();
      pkdebug("toString Exception: [$msg]");
      trigger_error($msg);
      return "ERROR: $msg";
    }
  }

  public function &getElementInst($elName, $proto = false) {
    return $this->getElementX($elName, $proto);
  }
  public function &getElementProto($elName, $proto = true) {
    return $this->getElementX($elName, $proto);
  }

  /** Returns an input element by name in assoc array. Can be empty/null if
   * explicitly set to null, or returns boolean false if not set at all.
   * 
   * @param String $name
   */
  public function &getElementX($elName, $proto = false) {
    $elements = $this->getElementsX($proto);
    $retval = false;
    if (arrayish_key_exists($elName, $elements)) {
      $retval = $elements[$elName];
    } 
    return $retval; #Distinguish between explicitly set NULL and not set at all
  }


  /**
   * Builds a default form from an object/class instanceOf BaseModel
   * @param null|BaseModel|String $obj: An object instance of BaseModel, or
   * a String which is the name of a class descendent from BaseModel. If
   * null, looks for the current form instance $this->base_object;
   * @param array $args: Optional key/value arguments -- like, maybe a template?
   */
  #TODO: Cancel! Nice notion, not sustainable now....
  /*
  public function spinFormFromObject($obj = null, $args = array()) {
    $base_object = $this->base_object; #default
    if ($obj) { #override the member default base_object
      $base_object = $this->returnObject($obj);
    }
    if (!$base_object instanceOf BaseModel) {
      throw new \Exception("Couldn't get a valid base_object");
    }
    #Have the object, now the hard part....
    #Build the array of names and values
    

  }
   * 
   */


  ########## Methods to support repeating/scrolling forms/subforms - with
  ### support for templates. Uses supporting JS library

  
/**
 * Sets up repeating subforms/collections for form display/processing, and
 * calls $this->editSubformItem() n+1 times to present the n existing items
 * in the collection, and a blank template to add additional items (implemented
 * through JavaScript). Adds a "New Item" button for the collection.
 *
 * Whatever calls this method should have a view template to inject the results
 * into.
 *
 * @param String $collName: The name of the collection as represented in
 * the containing object. Ex, containing class is "Mom", collection is
 * "Mom->$kids", $collName arg is "kids".
 * @param String $itemType: The Model Class/type. If collection name is "kids", the
 * item type might be: "Person"
 * @param String $itemTemplate: the name of the template to use to display the 
 * subform of $item components.
 *
 * TODO: Make generally recursive, for deep nesting, if desired. Totally missing 
 * an abstraction layer which needs to be implemented at some point.
 *
 * @param Array $items: Array/Collection of subform objects
 * @return Array: The associative data array of results. Each key should be echoed 
 * in a template, each value should be echoable -- that is, be a string or have a
 * __toString implementation. The class PartialSet, for example, extends ArrayObject,
 * and implements __toString by calling __toString on every element.

 
 
 The HTML string representing the subform, with existing items,
 * create/delete buttons, template, etc.
 */

public static function multiSubFormsSetup($collName, $itemType, $itemTemplate = null, Array $items = array()) {
  $data = array();
  $data[$collName] = new PartialSet();
  $seqName = $collName.'_idx';
  $idx = 0;
  if (!empty($items) && sizeof($items)) { #Got something...
    foreach ($items as $item) {
      if ($item instanceOf BaseModel) {
        $itemEls = static::editSubformItem($itemType, $item, $idx);
        $data[$collName][] = new RenderResult($itemEls, $itemTemplate);
          //ApplicationBase::exec('magicpartial', 'editfoodgroupconsideration', $foodgroupconsideration, $idx);
          //new static ($collName, $collType, $item, $idx);
        $idx++;
      }
    }
  }
  $data[$seqName] = $idx;
  $data['idx'] = $idx;
  #Make an item template:
  $item_tpl = static::editSubformItem($itemType);
  $data[$collName.'_template'] = new RenderResult(array("item_data"=>(new RenderResult($item_tpl, $itemTemplate)),'idx'=>'__template__'), 'forms/baseitem');
  $data['item_form_template'] = new RenderResult(array("item_data"=>(new RenderResult($item_tpl, $itemTemplate)),'idx'=>'__template__'), 'forms/baseitem');
  return $data;
}


/**
 * The individual row/subform management/display. Called by ::multiSubformsSetup(),
 * to display an individual component of the collection, and a hidden template.
 *
 * @return Array: Associative array of data, to be later included into a RenderResult with 
 * a view template.
 */

  public static function editSubformItem($itemType, BaseModel $item = null,  $idx='__template__') {
    $data = array();
    $seqName = $itemType."_idx";
    $data[$seqName] = $idx;
    $data['idx'] = $idx;
    //If no item object, make a subform template
    if (empty($item) || !($item instanceOf BaseModel)) { //make template
      $item =  $itemType::get(); #Make new empty instance
    }
    $data['item'] = $item;
    $data['id'] = $item->getId();
    //$data['fieldsToShow'] = $this->fieldsToShow;
    return $data;
  }
   
}

/**
 * Collection of identical forms for collections/scrolling/add/delete...
 * TODO: What are the args we should use here?
 * By default, will have one "Create" button, one invisible template containing
 * all the controls of a subform element instance (encoded in a "data-template"
 * attribute)
 */
class SubForm extends BaseForm {
  protected $el_tag = 'fieldset';
  protected static $classDefaultAttributes = array();
}

/** Specifically for repeating subforms -- the __toString method includes the
 * form template and wraps them all in a div/fieldset
 */
class FormSet extends SubForm {
  /**
   * @var PKMVC\PartialSet of individual subform sets, one per subitem
   */
  //protected $subforms;
  /** @var: Then number of subitems/subforms */
  protected $count = 0;
  protected $template_form;
  protected $base_form;
  protected static $instancePropertyNames = array('subforms', 'base_form');
  
  public function __construct($args = array()) {
    $this->scrolling = true;
    //$this->subforms = new PartialSet();
    #Customize args for building scrolling subform base_form/template:
    $subargs = $args;
    unset($args['elements']);
    parent::__construct($args);
    $name = $this->getName();
    $emptyHolder = new BaseElement(array(
        'type'=>'hidden',
        'name'=>$name,
      ));

    $name_segments = $this->getNameSegments();
    $name_segments[]=static::TPL_STR;
    $subargs['name_segments'] = $name_segments;
    $subargs['class'] = 'multi-subform';

    unset($subargs['name_segment']);

    #Add delete button if it isn't specified in subargs...
    if (!arrayish_key_exists('elements',$subargs) 
            ||!arrayish_key_exists('delete',$subargs['elements'])) {
      $subargs['elements']['delete']=array('input'=>'html', 
        'content' =>
          "<button type='button' class='pkmvc-button delete-row-button'>Delete</button>");
    }
    $this->base_form = new SubForm($subargs);
  //pkdebug("SubArgs:",$subargs,"base_form:", $this->base_form);
    unset($args['elements']);
    $args['scrolling'] = true;
    $this->addElementProto('formsetname', $emptyHolder);
    //$args['class'] = "TEST-CREATE-SUBFORM";
    //$args['name_segments'] = array("From","Formset", "Create");
    #Create default elements, like "Create Item" button
  }

  public function __toString() {
    if (empty($this->origArgs['create'])) {
      $count = sizeof($this->getElementsInst());
      $create_label = "New Profile";
      if (isset($this->origArgs['create_label'])) {
        $create_label = $this->origArgs['create_label'];
      }
      $htmlel = new BaseElement(
        array('input'=>'html', 'content'=> 
        "<div class='pkmvc-button new-from-formset-tpl' data-count='$count'>$create_label</div>",
              ));
      $this->addElementInst('create', $htmlel);
    } 
    return parent::__toString();
  }

  public function getJSTemplate() {
    $template = $this->base_form->copy();
    //$template->addNameSegment(static::TPL_STR);
    return $template;
  }

  /**
   * Bind the data for one-to-many subform
   * Creates the appropriate prototype elements based on data, then passes
   * up to parent to bind values
   * @param Arrayish $data: The input data array or ArrayAccess object
   */
  public function bind($data = null) {
    $name_segments = $this->getNameSegments();
    if (arrayish_keys_exist($name_segments, $data)) {
      $set = arrayish_keys_value($name_segments, $data);
      if (!$set || !sizeOf($set)) { #Nothing to set
        return;
      }
      if (!is_arrayish($set)) {
        $msg = "Bad src to bind: [".print_r($set, true).']';
        // (Wait until to string can throw errors...
        // throw new \Exception("Bad src to bind: [".print_r($src, true).']');
        pkdebug("ERROR!!!! [$msg]");
        trigger_error($msg);
        die();
      } #Okay, we have an array of data -- now create elements and bind
      $protoElements = new ArrayObject();
      $cnt = sizeOf($set);
      for ($i = 0 ; $i < $cnt ; $i ++ ) {
        $subfrm = $this->base_form->copy();
        $subfrm->replaceTemplateKey($i);
        $this->addElementProto($subfrm);
      }
      return parent::bind($data);
    }
  }


  public function additionalClassAttributes() {
    $template = $this->getJSTemplate();
    return "data-template='".html_encode($template)."'";
  }
}
