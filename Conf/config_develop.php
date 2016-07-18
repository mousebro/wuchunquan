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
            'db_host' => '192.168.20.138',
            'db_user' => 'develop',
            'db_pwd'  => 'develop%',
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
           'db_port'   => 3307,
           'db_name'   => 'myuu',
        ),
        'pft001'=>array(
            'db_type'   => 'mysql',
            'db_host'   => '192.168.20.138',
            'db_user'   => 'develop',
            'db_pwd'    => 'develop%',
            'db_port'   => 3307,
            'db_name' => 'pft001',
        ),
        'summary'   => array(//统计数据库
             'db_type'   => 'mysql',
             'db_host'   => '192.168.20.138',
             'db_user'   => 'develop',
             'db_pwd'    => 'develop%',
             'db_port'   => 3307,
             'db_name'   => 'summary',
        ),
        'terminal'  => array(
            'db_type'   => 'mysql',
            'db_host'   => '192.168.20.138',
            'db_user'   => 'cat',
            'db_pwd'    => 'cat123',
            'db_port'   => 3307,
            'db_name'   => 'orderdata',
        ),
    ),
    'redis'=>array(
        'main'=> array(
            'db_host'  =>'192.168.20.138',
            'db_port'  => 6379,
            'db_pwd'   => '123666',
            'db_queue' => 10, //队列使用的数据库
        ),
        'master'=> array(
            'db_host'=>'192.168.20.139',
            'db_port'=> 6379,
            //'db_pwd' => '123666',
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
                    'host' => '192.168.20.139',
                    'port' => 6379,
                    'db'=>10,
                    'alias'=>'master',
                    'master'=>true
                ],
                [
                    'host' => '192.168.20.139',
                    'port' => 6380,
                    'db'    =>10,
                    'alias'=>'slave'
                ],
            ],
            'REDIS_BACKEND_DATABASE' => 10,
        ),
        //定义需要加载的Job
        'jobs' => array(
            'Dog_Job', //小狗队列
            'Mail_Job', //邮件队列
            'OrderNotify_Job',//订单消息通知队列
            'WxNotify_Job', //微信通知队列
            'OrderCancel_Job',//订单取消队列
        )
    ),
);
