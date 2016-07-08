<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/19-019
 * Time: 11:57
 */
return array(
    'DEFAULT_FILTER' => 'htmlspecialchars', // 默认参数过滤方法 用于I函数...
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
        'pft001'=>array(
            'db_type'=>'mysql',
            'db_host'=>'10.51.26.214',//内网ip：10.51.26.214
            'db_user' => 'LiLei@HanMM',
            'db_pwd' => 'll@HmmPft(12301$',
            'db_port' => 3316,
            'db_name' => 'pft001',
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
            'db_host'=> '10.51.26.214',
            'db_port'=> 6679,
            'db_pwd' => 'pft666',
        ),
        'master'=> array(
            'db_host'=>'10.51.26.214',
            'db_port'=> 6679,
            'db_pwd' => 'pft666',
        ),
        'slave' => array(
            'db_host'=>'10.160.4.140',
            'db_port'=> 6680,
            'db_pwd' => 'pft666',
        ),
    ),
    'queue' => array(
        'setting' => array(
            'QUEUE'         => '*',  //先后顺序的队列名称 mail,default,log
            'PIDFILE'       => '',   //如果是一个进程时指定的进程PID存放文件
            'COUNT'         => 1,    //开启几个进程
            'VERBOSE'       => false, //是否显示出调试信息
            'INTERVAL'      => 2,     //检查队列的时间间隔
            'BLOCKING'      => false,  //暂时不知道做什么的
            'REDIS_BACKEND' => [
                [
                    'host' => '10.51.26.214',
                    'port' => 6679,
                    'password'=>'pft666',
                    'alias'=>'master',
                    'master'=>true
                ],
                [
                    'host' => '10.160.4.140',
                    'port' => 6680,
                    'password'=>'pft666',
                    'alias'=>'slave'
                ],
            ],
            'REDIS_BACKEND_DATABASE' => 10,
        ),
        //定义需要加载的Job
        'jobs' => array(
            'Dog_Job', //小狗队列
            'Mail_Job', //邮件队列
            'WxNotify_Job', //微信通知队列
            'OrderNotify_Job',//订单消息通知队列
        )
    ),
);
