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
        'pft001'=>array(
            'db_type'=>'mysql',
            'db_host' => '127.0.0.1',
            'db_user' => 'admin',
            'db_pwd' => 'adm*753951',
            'db_port' => 3307,
            'db_name' => 'pft001',
        ),
        'summary'   => array(//统计数据库
            'db_type'   => 'mysql',
            'db_host'   => '127.0.0.1',
            'db_user'   => 'admin_sum',
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
            'db_host'  =>'127.0.0.1',
            'db_port'  => 6379,
            'db_pwd'   => 'pft666',
            'db_queue' => 10, //队列使用的数据库
        ),
        'master'=> array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
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
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password'=>'pft666',
                    'db'=>10,
                ],
            ],
            'REDIS_BACKEND_DATABASE' => 10,
        ),
        //定义需要加载的Job
        'jobs' => array(
            'OrderNotify_Job',//订单消息通知队列
            'WxNotify_Job', //微信通知队列
        )
    ),

);
