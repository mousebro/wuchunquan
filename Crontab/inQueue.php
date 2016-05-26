<?php
/**
 * 测试入队列
 * 需要入队列的地方，只需要实例化Library\Resque\Queue，然后调用push方法就可以了
 *
 * @author dwer
 * @date 2016-04-14 
 */
//use Library\Resque\Queue as Queue;
//包含初始化文件
include '/var/www/html/Service/init.php';
$msg = '您好，去哪儿预定的1234567订单已取消，取票人:张三，电话:15911111111，预订产品：三坊七巷儿童票*2';
$jobId = Library\Resque\Queue::push('demo',
    'SmsNotify_Job', array(
        'mobile' => '18750193275',
        'msg'=>$msg,
        'mid'=>94)
);
echo $jobId;