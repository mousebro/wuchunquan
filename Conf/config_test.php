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
            'db_user' => 'mainS',
            'db_pwd' => 'su*7645901',
            'db_port' => 3308,
            'db_name' => 'myuu',
        ),
    ),
    'redis'=>array(
        'main'=> array(
            'db_host'=>'127.0.0.1',
            'db_prot'=> 6379,
            'db_pwd' => 'myPft!12301!&',
        ),
    ),

);
