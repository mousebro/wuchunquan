<?php
use Library\Resque\Resque\Resque_Worker as Resque_Worker;
use Library\Resque\Resque\Resque_Log as Resque_Log;
use Library\Resque\Resque\Resque_Redis as Resque_Redis;
use Library\Resque\Resque as Resque;
use Library\Resque\Log\LogLevel as LogLevel;

/**
 *  通过cli模式开启队列
 *
 * @author dwer
 * @date 2016-04-14 
 */

//包含初始化文件
include '/var/www/html/Service/init.php';
define('JOBS_DIR', dirname(__DIR__) . '/Jobs/');

//加载配置文件
$config  = C('queue');
if(!$config) {
    die('队列配置文件不存在');
}
$setting = $config['setting'];
$jobs    = $config['jobs'];
//包含需要执行的Job
foreach ($jobs as $value) {
    $jobFile = JOBS_DIR . $value . '.php';
    if(file_exists($jobFile)) {
        include_once($jobFile);
    }
}

//设置变量
foreach($setting as $key => $val) {
    switch($key) {
        case 'QUEUE' :
            $QUEUE = $val;
            break;
        case 'PIDFILE' :
            $PIDFILE = $val;
            break;
        case 'COUNT' :
            $COUNT = $val;
            break;
        case 'VERBOSE' :
            $VERBOSE = $val;
            break;
        case 'INTERVAL' :
            $INTERVAL = $val;
            break;
        case 'BLOCKING' :
            $BLOCKING = $val;
            break;
        case 'PREFIX' :
            $PREFIX = $val;
            break;
    }
}

//如果配置文件里面没有，就使用pft.confg.php 里面的配置
if(!isset($setting['REDIS_BACKEND']) || !$setting['REDIS_BACKEND']) {
    $redisConfig = C('redis');
    if(!isset($redisConfig['main'])) {
        die('Redis配置文件错误');
    }
    $con = $redisConfig['main'];
    $dsn = "tcp://user:{$con['db_pwd']}@{$con['db_host']}:{$con['db_port']}/{$con['db_queue']}";
    
    $REDIS_BACKEND = $dsn;
} else {
    $REDIS_BACKEND = $setting['REDIS_BACKEND'];
    $REDIS_BACKEND_DATABASE = $setting['REDIS_BACKEND_DATABASE'];
}

if(empty($QUEUE)) {
    die("Set QUEUE env var containing the list of queues to work.\n");
}

// A redis database number
Resque::setBackend($REDIS_BACKEND, $REDIS_BACKEND_DATABASE);

$logLevel = false;
if(!empty($LOGGING) || !empty($VERBOSE)) {
    $logLevel = true;
}

// See if the APP_INCLUDE containes a logger object,
// If none exists, fallback to internal logger
if (!isset($logger) || !is_object($logger)) {
    $logger = new Resque_Log($logLevel);
}

$BLOCKING = $BLOCKING !== FALSE;

//包含初始化文件
$interval = 5;
if(!empty($INTERVAL)) {
    $interval = $INTERVAL;
}

$count = 1;
if(!empty($COUNT) && $COUNT > 1) {
    $count = $COUNT;
}

if(!empty($PREFIX)) {
    $logger->log(LogLevel::INFO, 'Prefix set to {prefix}', array('prefix' => $PREFIX));
    Resque_Redis::prefix($PREFIX);
}

if($count > 1) {
    for($i = 0; $i < $count; ++$i) {
        $pid = Resque::fork();
        if($pid == -1) {
            $logger->log(LogLevel::EMERGENCY, 'Could not fork worker {count}', array('count' => $i));
            die();
        }
        // Child, start the worker
        else if(!$pid) {
            $queues = explode(',', $QUEUE);
            $worker = new Resque_Worker($queues);
            $worker->setLogger($logger);
            $logger->log(LogLevel::NOTICE, 'Starting worker {worker}', array('worker' => $worker));
            $worker->work($interval, $BLOCKING);
            break;
        }
    }
}
// Start a single worker
else {
    $queues = explode(',', $QUEUE);
    $worker = new Resque_Worker($queues);
    $worker->setLogger($logger);

    if ($PIDFILE) {
        file_put_contents($PIDFILE, getmypid()) or
            die('Could not write PID information to ' . $PIDFILE);
    }

    $logger->log(LogLevel::NOTICE, 'Starting worker {worker}', array('worker' => $worker));
    $worker->work($interval, $BLOCKING);
}