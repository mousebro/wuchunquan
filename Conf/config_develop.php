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
            'db_host' => '192.168.20.138',
            'db_user' => 'develop',
            'db_pwd' => 'develop%',
            'db_port' => 3307,
            'db_name' => 'myuu',
        ),
        'localhost_wsdl' => array(
            'db_type'=>'mysql',
            'db_host' => '192.168.20.138',
            'db_user' => 'develop',
            'db_pwd' => 'develop%',
            'db_port' => 3307,
            'db_name' => 'myuu',
        ),
        'remote_1' => array(
            'db_type'=>'mysql',
            'db_host' => '192.168.20.138',
            'db_user' => 'adminSS',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3307,
            'db_name' => 'myuu_ss',
        ),
        //10.160.4.140
        'remote_2' => array(
            'db_type'=>'mysql',
            'db_host'=>'192.168.20.138',
            'db_user' => 'pft_user_140',
            'db_pwd' => '5f40019b80@pft',
            'db_port' => 3308,
            'db_name' => 'myuu',
        ),
        'slave'   => array(//slave db
            'db_type'   => 'mysql',
            'db_host'   => '192.168.20.138',
            'db_user'   => 'develop',
            'db_pwd'    => 'develop%',
            'db_port'   => 3308,
            'db_name'   => 'myuu',
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