<?php
use Library\Resque\Resque as Resque;

//包含初始化文件
include '/var/www/html/Service/init.php';

Resque::setBackend('tcp://user:123666@192.168.20.138:6379/10');

$dog = Resque::FailedSize();
var_dump($dog);

//$dog = Resque::FailedPop();
//var_dump($dog);