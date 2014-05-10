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



/*Example Configuration file for PKMVC Framework
 */
define('DB_NAME',	'mydb');
define('DB_SERVER','localhost');
define('DB_USER','root');
define('DB_PASSWORD','');

define('BASE_DIR',__DIR__);

require_once BASE_DIR.'/dbconnection.php';
require_once BASE_DIR.'/Application.php';
require_once BASE_DIR.'/MagicController.php';
require_once BASE_DIR.'/MagicRenderer.php';
require_once BASE_DIR.'/MagicORM.php';
require_once BASE_DIR.'/MVCLib.php';
ViewRenderer::$templateRoot = BASE_DIR.'/templates';
