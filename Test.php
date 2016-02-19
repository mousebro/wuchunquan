<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/18-018
 * Time: 15:26
 */
define('DIR', dirname(__FILE__));
include 'autoload.php';
//echo __DIR__;
//$classLoader = new SplClassLoader('', __DIR__);
//$classLoader->register();
//include 'Member/Member.php';
\Service\Member\Member::say();