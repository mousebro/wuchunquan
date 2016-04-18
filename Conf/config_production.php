<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/19-019
 * Time: 11:57
 */
return array(
    'db'=> array(
        'localhost' => array(
            'db_type'=>'mysql',
            'db_host' => '10.160.4.140',
            'db_user' => 'admin',
            'db_pwd' => 'adm*753951',
            'db_port' => 3306,
            'db_name' => 'myuu',
        ),
        'localhost_wsdl' => array(
            'db_type'=>'mysql',
            'db_host' => '10.160.4.140',
            'db_user' => 'wsdl',
            'db_pwd' => 'ws*753951',
            'db_port' => 3306,
            'db_name' => 'myuu',
        ),
        'remote_1' => array(//s.12301.cc
            'db_type'=>'mysql',
            'db_host' => '10.169.9.198',
            'db_user' => 'admin_140',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3306,
            'db_name' => 'myuu',
        ),
        //10.160.4.140
        'remote_2' => array(
            'db_type'=>'mysql',
            'db_host'=>'10.117.7.197',
            'db_user' => 'pft_user_140',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3306,
            'db_name' => 'myuu',
        ),
        'slave'   => array(//slave db
            'db_type'=>'mysql',
            'db_host'=>'10.51.26.214',//内网ip：10.51.26.214
            'db_user' => 'mainS',
            'db_pwd' => 'su*7645901',
            'db_port' => 3306,
            'db_name' => 'myuu',
        ),
        'summary'   => array(//统计数据库
            'db_type'   => 'mysql',
            'db_host'   => '10.160.4.140',
            'db_user'   => 'summary',
            'db_pwd'    => 'su*7645901',
            'db_port'   => 3306,
            'db_name'   => 'summary',
        ),
        'terminal'  => array(
            'db_type'   => 'mysql',
            'db_host'   => '10.171.194.212',
            'db_user'   => 'app_user',
            'db_pwd'    => 'app@12301$*#',
            'db_port'   => 3306,
            'db_name'   => 'orderdata',
        ),
    ),
    'redis'=>array(
        'main'=> array(
            'db_host'=>'10.169.9.198',
            'db_port'=> 6379,
            'db_pwd' => 'myPft!12301!&',
        ),
    ),
);
