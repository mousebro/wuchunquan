<?php
/**
 * 队列任务的配置文件
 *
 * @author dwer
 * @date 2016-04-14 
 */
return array(
    //开启队列的配置
    'setting' => array(
        'QUEUE'         => '*',  //先后顺序的队列名称 mail,default,log
        'PIDFILE'       => '',   //如果是一个进程时指定的进程PID存放文件
        'COUNT'         => 1,    //开启几个进程
        'VERBOSE'       => true, //是否显示出调试信息
        'INTERVAL'      => 2     //检查队列的时间间隔
    ),
    //定义需要加载的Job
    'jobs' => array(
        'Dog_Job', //小狗队列
        'Mail_Job' //邮件队列
    )
);

