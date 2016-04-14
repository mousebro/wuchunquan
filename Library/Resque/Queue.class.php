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
    private $_dsn = 'redis://user:root@127.0.0.1:6379/10';

    public function __construct($dsn = false) {
        if($dsn) {
            $this->_dsn = $dsn;
        }
    }

    public function push($queueName, $jobName, $args) {
        Resque::setBackend($this->_dsn);
        $jobId = Resque::enqueue($queueName, $jobName, $args, true);
        return $jobId;
    }
}

