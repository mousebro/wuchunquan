<?php
/**
 * 测试入队列
 * 需要入队列的地方，只需要实例化Library\Resque\Queue，然后调用push方法就可以了
 *
 * @author dwer
 * @date 2016-04-14 
 */
use Library\Resque\Queue as Queue;
define('CONF_DIR', dirname(__DIR__) . '/Conf/');
//加载配置文件
$conf = CONF_DIR . 'jobs.conf.php';
//包含初始化文件
include '/var/www/html/Service/init.php';
$queue = new Queue($conf);
$jobId = $queue->push('default', 'Mail_Job', array('time' => '2020-10-12 12:11:11'));

echo $jobId;