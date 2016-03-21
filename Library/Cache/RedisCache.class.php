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
        $db_name = strval($db_name);
        $conf = C('redis');
        if (isset($conf[$db_name])) $redisConf = $conf[$db_name];
        else $redisConf = $conf['main'];
        //PHP Fatal error:  Cannot use object of type Redis as array in /var/www/html/Service/Library/Cache/RedisCache.class.php on line 21
        $redis = new Redis();
        $redis->connect($redisConf['db_host'], $redisConf['db_port']);
        $redis->auth($redisConf['db_pwd']);
        return $redis;
    }
}