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
        'QUEUE'         => '*',
        'PIDFILE'       => '',
        'COUNT'         => 1,
    ),
    //定义需要加载的Job
    'jobs' => array(
        'Dog_Job', //小狗队列
        'Mail_Job' //邮件队列
    )
);

