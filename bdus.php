<?php
/**
 * Example: ./bdus.php path/to/cfg/folder
 *    path/to/cfg/folder/ can be a local directory (both relative or absolute) or a remote directory (es. a GitHub repo)
 */

// Only CLI usage check
// https://stackoverflow.com/a/22490358/586449
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('This file can be used only through CLI');

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);



include('./libs/u.php');
include('./libs/Validate.php');
include('./libs/Create.php');
include('./libs/rb.php');


list($file, $action, $path2cfg, $path2dest) = $argv;




function getAction($action = false){
  $actions = ['validate', 'create'];
  if ($action && in_array($action, $actions)){
    return $action;
  }
  $action = trim(readline("Enter an action: [validate or create]: "));
  if (!in_array($action, $actions)){
    return getAction();
  } else {
    return $action;
  }
}
function getPath2cfg($path2cfg = false){
  if(!$path2cfg){
    $path2cfg = trim(readline("Enter path to configuration directory: "));
  }
  return $path2cfg;
}
function getPath2dest($path2dest = false){
  if(!$path2dest){
    $path2dest = trim(readline("Enter path to destination directory: "));
  }
  return $path2dest;
}

$action = getAction($action);
$path2cfg = getPath2cfg($path2cfg);

if ($action === 'create'){
  $path2dest = getPath2dest($path2dest);
  R::setup( "sqlite:./{$path2dest}/db/bdus.sqlite" );
}

try {

  if ($action === 'validate'){
    echo "\nValidating {$path2cfg}\n";
    \mngProject\Validate::all($path2cfg, true, true);
  } else if ($action === 'create'){
    echo "\nCreating from {$path2cfg} to {$path2dest}\n";
    \mngProject\Create::all($path2cfg, $path2dest, true);
  } else {
    throw new Exception("Invalid action {$action}");
  }

  echo "\nAll done!\n\n";

} catch (\Exception $e) {
  var_dump($e->getMessage());
}
