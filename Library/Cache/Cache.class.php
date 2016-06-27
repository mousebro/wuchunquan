<?php
/**
 * Created by PhpStorm.
 * User: guangpeng
 * Date: 4/19-019
 * Time: 22:01
 */

namespace Library\Cache;
/**
 * 缓存操作
 *
 */
use Library\Exception;

defined('PFT_INIT') or exit('Access Invalid!');

class Cache {

    protected $params;
    protected $enable;
    /**
     * @var \Redis
     */
    protected $handler;

    /**
     * 实例化缓存驱动
     *
     * @param string $type
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    public function connect($type,$args = array()){
        //if (empty($type)) $type = C('cache_open') ? 'redis' : 'file';
        $type = strtolower($type);
        $class = 'Cache' . ucwords($type);
        $class = '\\Library\\Cache\\' . $class;
        if (!class_exists($class)){
            throw new Exception("[$type]Cache Not Exist");
        }
        return new $class($args);
    }

    /**
     * 取得实例
     *
     * @return object
     */
    public static function getInstance($type, $args=array()){
        $args = func_get_args();
        return get_obj_instance(__CLASS__,'connect',$args);
    }
}