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
 * The basic object data model for the base user, which can be extended to particular
 * purpose.
 */

Class BaseUser extends BaseModel {
  /**
   * Assuming that particular implementations might want to use different fields
   * for identity (eg, username, or email, or ...? So can be overriden in 
   * the derived "User" class
   */
  protected $idfield = 'uname'; #Can be overriden in derived class
  protected static $memberDirects = array('id', 'uname', 'password', 'salt');
  protected static $memberObjects = array();
  protected static $memberCollections = array();

  protected $uname;
  protected $password;
  protected $salt;

  /**
   * Registers and creates a new User
   * @param String $idfield: The value to be used as the "ID", as specified by the 
   * static "$idfield" of the class. Almost always just one of uname or email, but
   * that's up to the implementor. So if the static attribute $idfield of the 
   * derived class is "email", this argument would contain the email address of 
   * the new user, "jblow@example.com". If "static::$idfield" = uname, the
   * value of this param would be like: "jblow"
   *
   * @param String $password: The cleartext password, which is never saved.
   *
   * @param Array $optargs: Optional argument array.
   *
   *@return: The new user object if successful, else an error.
   *
   */

  public static function register($idfield, $password, $optargs = array()) {
    $salt = static::makeSalt();
  }

  /**
   * User Login
   * @param String $idfield: As for registration, above.
   * @param String #password: As above
   * @return: The newly logged in User object, else error
   */
  public static function login($idfield, $password) {
  }

  /**
   * To change the passwowrd for an existing user. Typically both $oldPassword
   * and $newPassword would be required, but who knows, so give default.
   */
  public function changePassword($newPassword=null, $newPassword = null) {
    $salt = static::makeSalt(); #If changing pwd, might as well salt
  }

  public static function makeSalt() {
    return base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM));
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






