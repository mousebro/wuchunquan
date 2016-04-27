<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/27-027
 * Time: 17:12
 */

namespace Library\MessageNotify;


class MessageNotify
{
    protected $params = [];
    public function __construct($args)
    {
        $this->params = $args;
    }

    public function init($class)
    {
        $class = '\\Library\\MessageNotify\\' . $class;
        if (!class_exists($class)){
            E("[$class]Cache Not Exist");
        }
        return new $class();
    }
    public function getParams()
    {
        print_r($this->params);
    }
    /**
     * 取得实例
     *
     * @return object
     */
    public static function getInstance($type, $args=array()){
        $args = func_get_args();
        return get_obj_instance(__CLASS__,'init',$args);
    }
}