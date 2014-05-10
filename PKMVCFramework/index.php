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


/** Sample index.php file to start an PKMVC Framework site
*/
require_once (__DIR__.'/config.php');
$app = new Application();
$app->run();
