<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/18-018
 * Time: 17:13
 */
include_once __DIR__ . '/autoload.php';
include_once __DIR__ .'/Conf/pft.conf.php';
include_once __DIR__ .'/Common/functions.php';
C(include  __DIR__ .'/Conf/config_'.strtolower(ENV).'.php');
spl_autoload_register("\\AutoLoading\\loading::autoload");
define('__TIMESTAMP__', date('Y-m-d H:i:s'));
//添加全局的初始化常量标识
define('PFT_INIT', true);


