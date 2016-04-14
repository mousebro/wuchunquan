<?php
use Library\Resque\Resque\Resque_Worker as Resque_Worker;
use Library\Resque\Resque\Resque_Log as Resque_Log;
use Library\Resque\Resque\Resque_Redis as Resque_Redis;
use Library\Resque\Resque as Resque;

/**
 *  通过cli模式开启队列
 *
 * @author dwer
 * @date 2016-04-14 
 */

//包含初始化文件
include '/var/www/html/Service/init.php';

define('CONF_DIR', dirname(__DIR__) . '/Conf/');
define('JOBS_DIR', dirname(__DIR__) . '/Jobs/');

//加载配置文件
$conf = CONF_DIR . 'jobs.conf.php';
if(!file_exists($conf)) {
    die('队列配置文件不存在');
}
$config  = include($conf);
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
    if($val) {
        $tmp = "{$key}={$val}";
        putenv($tmp);
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
    $tmp = "REDIS_BACKEND={$dsn}";
    putenv($tmp);
}

//开启队列进程
$QUEUE = getenv('QUEUE');

if(empty($QUEUE)) {
    die("Set QUEUE env var containing the list of queues to work.\n");
}

/**
 * REDIS_BACKEND can have simple 'host:port' format or use a DSN-style format like this:
 * - redis://user:pass@host:port
 *
 * Note: the 'user' part of the DSN URI is required but is not used.
 */
$REDIS_BACKEND = getenv('REDIS_BACKEND');

// A redis database number
$REDIS_BACKEND_DB = getenv('REDIS_BACKEND_DB');
if(!empty($REDIS_BACKEND)) {
    if (empty($REDIS_BACKEND_DB))
        Resque::setBackend($REDIS_BACKEND);
    else
        Resque::setBackend($REDIS_BACKEND, $REDIS_BACKEND_DB);
}

$logLevel = false;
$LOGGING = getenv('LOGGING');
$VERBOSE = getenv('VERBOSE');
$VVERBOSE = getenv('VVERBOSE');
if(!empty($LOGGING) || !empty($VERBOSE)) {
    $logLevel = true;
}
else if(!empty($VVERBOSE)) {
    $logLevel = true;
}

$APP_INCLUDE = getenv('APP_INCLUDE');
if($APP_INCLUDE) {
    if(!file_exists($APP_INCLUDE)) {
        die('APP_INCLUDE ('.$APP_INCLUDE.") does not exist.\n");
    }

    require_once $APP_INCLUDE;
}

// See if the APP_INCLUDE containes a logger object,
// If none exists, fallback to internal logger
if (!isset($logger) || !is_object($logger)) {
    $logger = new Resque_Log($logLevel);
}

$BLOCKING = getenv('BLOCKING') !== FALSE;

//包含初始化文件

$interval = 5;
$INTERVAL = getenv('INTERVAL');
if(!empty($INTERVAL)) {
    $interval = $INTERVAL;
}

$count = 1;
$COUNT = getenv('COUNT');
if(!empty($COUNT) && $COUNT > 1) {
    $count = $COUNT;
}

$PREFIX = getenv('PREFIX');
if(!empty($PREFIX)) {
    $logger->log(Psr\Log\LogLevel::INFO, 'Prefix set to {prefix}', array('prefix' => $PREFIX));
    Resque_Redis::prefix($PREFIX);
}

if($count > 1) {
    for($i = 0; $i < $count; ++$i) {
        $pid = Resque::fork();
        if($pid == -1) {
            $logger->log(Psr\Log\LogLevel::EMERGENCY, 'Could not fork worker {count}', array('count' => $i));
            die();
        }
        // Child, start the worker
        else if(!$pid) {
            $queues = explode(',', $QUEUE);
            $worker = new Resque_Worker($queues);
            $worker->setLogger($logger);
            $logger->log(Psr\Log\LogLevel::NOTICE, 'Starting worker {worker}', array('worker' => $worker));
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

    $PIDFILE = getenv('PIDFILE');
    if ($PIDFILE) {
        file_put_contents($PIDFILE, getmypid()) or
            die('Could not write PID information to ' . $PIDFILE);
    }

    //$logger->log(Psr\Log\LogLevel::NOTICE, 'Starting worker {worker}', array('worker' => $worker));
    $worker->work($interval, $BLOCKING);
}