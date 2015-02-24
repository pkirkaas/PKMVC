<?php
namespace PKMVC;
/**
 * PKMVC Framework -- User Module:
 * Combines/Extends the otherwise independent components of 
 * PKMVCORM -- (the Object Data Model) and PKMVCFramework (the MVC Framework),
 * to provide a basic user registration/login/profile functionality
 *
 * !!! CAUTION !!!!:
 * These are currently just the basic function/method signatures to implement
 * user management. The security/encryption algorithms used at this point
 * are pretty primitive and not very secure. 
 *
 * @author    Paul Kirkaas
 * @email     p.kirkaas@gmail.com
 * @link     
 * @copyright Copyright (c) 2012-2014 Paul Kirkaas. All rights Reserved
 * @license   http://opensource.org/licenses/BSD-3-Clause  
 */

/**
 * The basic object data model for the base user, which can/must be extended to particular
 * purpose. But the derived class can be empty.
 */

Abstract Class BaseUser extends BaseModel {
  /**
   * Assuming that particular implementations might want to use different fields
   * for identity (eg, username, or email, or ...? So can be overriden in 
   * a derived "MyUser" class
   */
  protected static $idfield = 'uname'; #Can be overriden in derived class
  #public /*protected*/ static $memberDirects = array('id', 'uname', 'password', 'salt');
  #public /*protected*/ static $memberDirects = array('id', 'uname', 'password', 'salt');
  #protected static $memberDirects = array( 'uname', 'password', 'salt',);
  protected static $memberDirects = array(
      'uname'=> array('dbtype'=>'varchar', 'collength'=> 255, 'key'=>'unique', 'eltype'=>'text'),
      'password'=>array('dbtype'=>'varchar', 'collength'=>999, 'eltype'=>'password'),
      'salt' => array('dbtype'=>'varchar', 'collength'=>999, 'eltype'=>'text'),
      );
  /*
  */
  protected static $memberObjects = array();
  protected static $memberCollections = array();

  /**
   * @var String: The username. Can be empty if derived class uses another field
   */
  protected $uname;
  /**
   * @var String: The encrypted password
   */
  protected $password;
  /**
   * @var String: The generated salt
   */
  protected $salt;

  /**
   * Registers and creates a new User
   * @param String $idvalue: The VALUE to be used as the "ID", as specified by the 
   * static "$idfield" of the class. Almost always just one of uname or email, but
   * that's up to the implementor. So if the static attribute $idfield of the 
   * derived class is "email", this argument would contain the email address of 
   * the new user, "jblow@example.com". If "static::$idfield" = uname, the
   * value of this param would be like: "jblow"
   *
   * @param String $cpassword: The cleartext password, which is never saved.
   *
   * @param Array $optargs: Optional argument array. If the BaseUser class is
   * subclassed by an application user class with additional fields (say, email
   * or fname & lname, etc, these should be here...
   *
   *@return: The new user object if successful, else an error.
   *
   */

  public static function register($idvalue, $cpassword=null, $optargs = array()) {
    $directs = static::getMemberDirectNames();
    $class = get_called_class();
    $user = static::get();
    foreach ($optargs as $key => $val) {
      if (in_array($key, $directs)) {
        $user->$key = $val;
      }
    }
    $salt = static::makeSalt();
    $idfield = static::$idfield;
    $setIdField = "set".toCamelCase($idfield);
    $user->$setIdField($idvalue);
    $user->setSalt($salt);
    $password = static::makePassword($cpassword, $salt);
    $user->setPassword($password);
    #Should validate user data before trying to save...
    $user->save();
    return $user;

  }

  /** Overrides the base Object Model method if we are creating a new user-
   * need to register first ...
   * @param Array|Int|Null $idOrArray: If null, int ID, or array with an 
   * ID key field set, just pass to parent. If an array of data without an
   * ID, it's a new user, so must register first.
   * 
   * 
   * Alternate: SUBMIT button is named 'do_reg' -- check for that in 
   * input array...
   * 
   * @return BaseModel - the retrieved or created object
   */
  public static function get($idOrArray = null) {
    //if (!is_array($idOrArray) || isset($idOrArray['id'])) {
    if (!is_array($idOrArray) || !isset($idOrArray['do_reg'])) {
      #Whatever it is, not registering a new user, pass up
      return parent::get($idOrArray);
    }
    #New User - register. Build Args
    $cpassword = $idOrArray['cpassword'];
    $idfield = static::$idfield;
    $idvalue = $idOrArray[$idfield];
    $user = static::register($idvalue,$cpassword,$idOrArray);
    static::cacheObj($user);
    return $user;
  }


/** Takes cleartext password and salt and returns hashed password
*/
  public static function makePassword($cpassword, $salt=null) {
    $password = hash('sha256', $salt . $cpassword);
    return $password;
  }

  /**
   * User Login. Password can be null so derived class can use alternate login
   * method. 
   * @param String $idvalue: As for registration, above.
   * @param String $cpassword: As above. Can be null for derived login method
   * @return: The newly logged in User object, else error
   */
  public static function login($idvalue, $cpassword=null, $args = null) {
    #First check if username exists, if so, get salt, compute password
    #and see if match...
    //die("Trying to log in...");
    $idfield = static::$idfield;
    $class = get_called_class();
    $paramArr = array($idfield => $idvalue);
    $users = static::getObjectsThatMatch($paramArr);
    if (!is_array($users) || !sizeOf($users) || 
            !(($users[0] instanceOf $class))) {
      return "User Name [$idfield] Not Found";
    }
    #User exists, check password
    $tstUser = $users[0];
    $salt = $tstUser->getSalt();
    $tstPassword = static::makePassword($cpassword,$salt);
    $storedPassword = $tstUser->getPassword();

    if (!($tstPassword === $tstUser->getPassword())) { #no match
      return "Passwords didn't match!";
    }
    #We're good? Persist and return
    $user = $tstUser;
    $id = $user->getId();
    Application::setSessionVal('userId', $id);
    Application::setSessionVal('userClass', get_called_class());
    return $user;
  }

  /** Returns the logged in user object (if logged in), else null
   * 
   */
  public static function getCurrent() {
    $userId = Application::getSessionVal('userId'); #Just abstracts session
    $userClass = Application::getSessionVal('userClass'); #Just abstracts session
    if (!$userId) {
      return null;
    }
    $user = $userClass::get($userId);
    if ($user instanceOf BaseUser) {
      return $user;
    } else {
      return null;
    }
  }

  public function logMeOut() {
    static::logout();
  }

  public static function logout() {
    Application::setSessionVal('userId');
  }

  /**
   * Return string of username/handle
   */
  public function getHandle() {
    $handleField = static::$idfield; 
    return $this->$handleField;
  }

  /**
   * To change the passwowrd for an existing user. Typically both $oldPassword
   * and $newPassword would be required, but who knows, so give default null.
   */
  public function changePassword($newPassword=null, $newPassword = null) {
    $salt = static::makeSalt(); #If changing pwd, might as well salt
  }

  public static function makeSalt() {
    $salt = base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM));
    return $salt;
  }

}
##########  END Class BaseUser ################

###### START User support functions ############

function sec_session_start() {
    $session_name = 'sec_session_id';   // Set a custom session name
    $secure = SECURE;
    // This stops JavaScript being able to access the session id.
    $httponly = true;
    // Forces sessions to only use cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        header("Location: ../error.php?err=Could not initiate a safe session (ini_set)");
        exit();
    }
    // Gets current cookies params.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"],
        $cookieParams["path"], 
        $cookieParams["domain"], 
        $secure,
        $httponly);
    // Sets the session name to the one set above.
    session_name($session_name);
    session_start();            // Start the PHP session 
    session_regenerate_id();    // regenerated the session, delete the old one. 
}






