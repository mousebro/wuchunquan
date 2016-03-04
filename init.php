<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/18-018
 * Time: 17:13
 */
include_once 'autoload.php';
include_once 'Conf/pft.conf.php';
include_once 'Common/functions.php';
C(include  'Conf/config_'.strtolower(ENV).'.php');
spl_autoload_register("\\AutoLoading\\loading::autoload");


