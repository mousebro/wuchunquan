<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Library;

/**
 * ThinkPHP 数据库中间层实现类
 */
class Db {

    static private  $instance   =  array();     //  数据库连接实例
    static private  $_instance  =  null;   //  当前数据库连接实例

    /**
     * 取得数据库类实例
     * @static
     * @access public
     * @param mixed $config 连接配置
     * @return Object 返回数据库驱动类
     */
    static public function getInstance($config=array()) {
        $md5    =   md5(serialize($config));

        if(!isset(self::$instance[$md5])) {
            // 解析连接参数 支持数组和字符串
            $options    =   self::parseConfig($config);

            // 兼容mysqli
            if('mysqli' == $options['db_type']) $options['type']   =   'mysql';

            $class = 'Library\\Db\\Driver\\' . ucwords(strtolower($options['type']));

            if (class_exists($class)) {
                self::$instance[$md5] = new $class($options);
            } else {
                // 类没有定义
                E('数据库驱动不存在');
            }
        }
        self::$_instance    =   self::$instance[$md5];
        return self::$_instance;
    }

    /**
     * 强制断开数据库连接
     * @author dwer
     * @date   2016-03-31
     *
     * @param  array $config 连接配置
     * @return [type]
     */
    static public function forceShutdown($config = array()) {
        $md5    =   md5(serialize($config));
        if(isset(self::$instance[$md5])) {
            self::$_instance      = null;
            self::$instance[$md5] = null;
        }

        return true;
    }

    /**
     * 数据库连接参数解析
     * @static
     * @access private
     * @param mixed $config
     * @return array
     */
    static private function parseConfig($config){
        if(!empty($config)){
            if(is_string($config)) {
                return self::parseDsn($config);
            }
            $config =   array_change_key_case($config);
            $config = array (
                'type'          =>  $config['db_type'],
                'username'      =>  $config['db_user'],
                'password'      =>  $config['db_pwd'],
                'hostname'      =>  $config['db_host'],
                'hostport'      =>  $config['db_port'],
                'database'      =>  $config['db_name'],
                'params'        =>  isset($config['params']) ? $config['params'] : array()
            );
        }

        return $config;
    }

    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @static
     * @access private
     * @param string $dsnStr
     * @return array
     */
    static private function parseDsn($dsnStr) {
        if( empty($dsnStr) ){return false;}
        $info = parse_url($dsnStr);
        if(!$info) {
            return false;
        }
        $dsn = array(
            'type'      =>  $info['scheme'],
            'username'  =>  isset($info['user']) ? $info['user'] : '',
            'password'  =>  isset($info['pass']) ? $info['pass'] : '',
            'hostname'  =>  isset($info['host']) ? $info['host'] : '',
            'hostport'  =>  isset($info['port']) ? $info['port'] : '',
            'database'  =>  isset($info['path']) ? substr($info['path'],1) : '',
            'charset'   =>  isset($info['fragment'])?$info['fragment']:'utf8',
        );
        
        if(isset($info['query'])) {
            parse_str($info['query'],$dsn['params']);
        }else{
            $dsn['params']  =   array();
        }
        return $dsn;
     }

    // 调用驱动类的方法
    static public function __callStatic($method, $params){
        return call_user_func_array(array(self::$_instance, $method), $params);
    }
}

