<?php
/**
 *  测试入队列
 *
 * @author dwer
 * @date 2016-04-14 
 */
use Library\Resque\Queue as Queue;

//包含初始化文件
include '/var/www/html/Service/init.php';

$queue = new Queue();

$jobId = $queue->push('default', 'Mail_Job', array());

echo $jobId;