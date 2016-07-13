<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 16/3/26
 * Time: 22:33
 */
if (PHP_SAPI !='cli') exit('error');
if ($argc==1) {
    echo str_pad("*", 50, "*"), PHP_EOL;
    echo str_pad("*", 19, " ")," HELP TIPS ",str_pad("*", 20, " ", STR_PAD_LEFT), PHP_EOL;
    echo str_pad("*", 4, " ")," php -f run ControllerName ActionName ",str_pad("*", 8, " ", STR_PAD_LEFT), PHP_EOL;
    echo str_pad("*", 50, "*"), PHP_EOL;
    exit;
}

//定义cli处理入口
define('PFT_CLI', true);

include '/var/www/html/Service/init.php';
$controller = $argv[1];
if (strpos($controller, '_')!==false) {
    list($namespace, $controller) = explode('_', $controller);
    $controller = $namespace . '/' . $controller;
}
$controller = 'Controller\\' . str_replace('/', '\\', $controller);
$action     = $argv[2];

if (class_exists($controller))
    $_object = new $controller();
else exit("Controller Not Exist");

if (method_exists($controller, $action))
    $_object->$action($argv);
else exit('Action Not Exist');