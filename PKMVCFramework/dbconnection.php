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



//Create PDO connection for new DB functionality
function getDb() {
  static $db = null;
  if ($db instanceof PDO) return $db;
  try {  
    $db = new PDO("mysql:host=".DB_SERVER.";dbname=".DB_NAME, DB_USER, DB_PASSWORD);  
  } catch (PDOException $e) {
    die  ($e->getMessage());
  }
  return $db;
}




/** Takes a mysql string with no parameters, or named or '?' for placeholders, and input which is
 * a single (string) value, or array of values, prepares the statement, and
 * executes it.
 * @param type $stmntstr
 * @param type $params
 * @return FALSE if failure, or the result statement
 */
function prepare_and_execute($stmntstr, $params = null) {
  //pkecho( $stmntstr, $params);

  if (!is_string($stmntstr)) {
    throw new Exception("Invalid Statement String: [ $stmntstr ]");
   // return ("Invalid Statement String: [$stmntstr]");
  }
  if (!is_array($params)) {
    $params = array($params);
  }
  $success = true;
  $db = getDb();
  $stmt = $db->prepare($stmntstr);
  if ($stmt instanceOf PDOStatement) {
    $success = $stmt->execute($params);
    if (!$success) {
      $errorInfo = pkvardump($stmt->errorInfo(), false);
    }
  } else {
    $errorInfo = pkvardump($db->errorInfo(), false);
    $success = false;

  }
  if (!$success) {
    $debugDumpParams = pkcatchecho(array($stmt, 'debugDumpParams'));
    throw new Exception("DB error in prepare_and_execute;"
            . "Error and debug output:\n\n$errorInfo\n\n$debugDumpParams");
   // return false;
  }
  return $stmt;
}
