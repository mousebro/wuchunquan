<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/11-011
 * Time: 10:18
 */

namespace Library;


class Dispatch
{
    private static function _getRequestRoute()
    {
        $controller = I('get.c');
        $action     = I('get.a');
        $controller = 'Controller\\' . str_replace('/', '\\', $controller);
        return [
            'c'=>$controller,
            'a'=>$action
        ];
    }

    public static function run()
    {
        static $_codeAr = array();
        $_routeAr = self::_getRequestRoute();
        $key = $_routeAr['c'] . '_' . $_routeAr['a'];
        if (class_exists($_routeAr['c']))
            $_object = new $_routeAr['c']();
        else self::error("Controller Not Exist");
        if (method_exists($_routeAr['c'], $_routeAr['a']))
            $_codeAr[$key] = $_object->$_routeAr['a']();
        else self::error("Action Not Exist");
    }
    /**
     * 错误处理
     *
     * @access private
     * @return void
     */
    public static function error($message='') {
        exit($message);
    }
}