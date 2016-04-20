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
            'db_host' => '127.0.0.1',
            'db_user' => 'admin',
            'db_pwd' => 'adm*753951',
            'db_port' => 3307,
            'db_name' => 'myuu',
        ),
        'localhost_wsdl' => array(
            'db_type'=>'mysql',
            'db_host' => '127.0.0.1',
            'db_user' => 'wsdl',
            'db_pwd' => 'ws*753951',
            'db_port' => 3307,
            'db_name' => 'myuu',
        ),
        'remote_1' => array(
            'db_type'=>'mysql',
            'db_host' => '127.0.0.1',
            'db_user' => 'adminSS',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3307,
            'db_name' => 'myuu_ss',
        ),
        //10.160.4.140
        'remote_2' => array(
            'db_type'=>'mysql',
            'db_host'=>'10.117.7.197',
            'db_user' => 'pft_user_140',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3307,
            'db_name' => 'myuu',
        ),
        'slave'   => array(//slave db
            'db_type'=>'mysql',
            'db_host'=>'127.0.0.1',//内网ip：10.51.26.214
            'db_user' => 'peter',
            'db_pwd' => 'peter@12301HAHA',
            'db_port' => 3308,
            'db_name' => 'myuu',
        ),
        'summary'   => array(//统计数据库
            'db_type'   => 'mysql',
            'db_host'   => '127.0.0.1',
            'db_user'   => 'admin',
            'db_pwd'    => 'adm*753951',
            'db_port'   => 3307,
            'db_name'   => 'summary',
        ),
        'terminal'  => array(
            'db_type'   => 'mysql',
            'db_host'   => '127.0.0.1',
            'db_user'   => 'cat',
            'db_pwd'    => 'cat12301',
            'db_port'   => 3307,
            'db_name'   => 'orderdata',
        ),
    ),
    'redis'=>array(
        'main'=> array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
            'db_pwd' => 'pft666',
        ),
        'master'=> array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
            'db_pwd' => 'pft666',
        ),
    ),

);
