<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/18-018
 * Time: 17:13
 */
include_once 'autoload.php';
include_once 'Common/functions.php';
include_once 'Conf/pft.conf.php';
C(include  'Conf/config_'.strtolower(ENV).'.php');
spl_autoload_register("\\AutoLoading\\loading::autoload");

//spl_autoload_register("\\AutoLoading\\loading::autoload");
//spl_autoload_register("\\AutoLoading\\loading::load_lane_wechat");
//\LaneWeChat\Autoloader::register();
//AutoLoading\loading::register();
# include '/var/www/html/new/d/common/func.inc.php';

