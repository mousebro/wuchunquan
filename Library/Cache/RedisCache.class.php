<?php

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/9-009
 * Time: 9:22
 */
namespace Library\Cache;
use Redis;
class RedisCache
{
    /**
     * 获取Redis连接实例
     *
     * @param string $db_name
     * @return Redis
     */
    public static function Connect($db_name='main')
    {
        $redisConf = C('redis')[$db_name];
        $redis = new Redis();
        $redis->connect($redisConf['db_host'], $redisConf['db_port']);
        $redis->auth($redisConf['db_pwd']);
        return $redis;
    }
}