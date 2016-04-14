<?php
namespace Library\Resque;

use Library\Resque\Resque as Resque;

/**
 * 入队列
 * @author dwer
 * @date   2016-04-14
 *
 * @return
 */
class Queue {
    private $_dsn = '';

    public function __construct($dsn = false) {
        if($dsn) {
            $this->_dsn = $dsn;
        } else {
            //统一在这里获取数据配置
            $redisConfig = C('redis');
            if(!isset($redisConfig['main'])) {
                die('Redis配置文件错误');
            }
            $con = $redisConfig['main'];
            $this->_dsn = "tcp://user:{$con['db_pwd']}@{$con['db_host']}:{$con['db_port']}/{$con['db_queue']}";
        }
    }

    /**
     *  数据入队列
     * @author dwer
     * @date   2016-04-14
     *
     * @param  $queueName 队列的名称，在jobs.conf.php定义
     * @param  $jobName 处理的Job的名称，在Service/Jobs/里定义的
     * @param  $args 传给处理器的参数
     * @return 返回任务ID
     */
    public function push($queueName, $jobName, $args) {
        Resque::setBackend($this->_dsn);
        $jobId = Resque::enqueue($queueName, $jobName, $args, true);
        return $jobId;
    }
}

