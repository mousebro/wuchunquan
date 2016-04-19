<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 4/19-019
 * Time: 22:18
 */
include '/var/www/html/Service/init.php';

$cache = \Library\Cache\Cache::getInstance('redis');
var_dump($cache);
//$cache = new \Library\Cache\CacheRedis();
//var_dump($cache);

//$cache->connect('redis');
$res = $cache->set('foo', 'bar','test',1800);
//var_dump($res);
var_dump($cache->get('foo','test'));