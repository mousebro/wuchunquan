<?php
/**
 * API接口进行统一的路由和校验
 * @author zdw
 * @date 2016-04-14
 * 
 */
namespace Library;

class ApiDispatch{

    public static function run(){
        static $_codeAr = array();

        $isAuth = self::_auth();
        if(!$isAuth) {
            self::error(401, '未授权');
        }

        $_routeAr = self::_getRequestRoute();
        $key      = $_routeAr['c'] . '_' . $_routeAr['a'];
// var_dump($_routeAr['c']);die;
        if (class_exists($_routeAr['c'])){
            $_object = new $_routeAr['c']();
        } else {
            self::error(400, 'Controller Not Exist');
        }

        if (method_exists($_routeAr['c'], $_routeAr['a'])) {
            $_codeAr[$key] = $_object->$_routeAr['a']();
        } else {
            self::error(400, 'Action Not Exist');
        }
    }

    /**
     * 错误处理
     *
     * @access private
     * @return void
     */
    public static function error($code, $message='') {
        $data = array(
            'code'    => $code,
            'message' => $message
        );

        echo json_encode($data);
        exit();
    }

    /**
     * api接口授权验证
     * @author dwer
     * @date   2016-04-14
     *
     * @return
     */
    private static function _auth() {
        
        
    }

    private static function _getRequestRoute(){
        $controller = I('get.c');
        if (strpos($controller, '_')!==false) {
            list($namespace, $controller) = explode('_', $controller);
            $controller = $namespace . '/' . $controller;
        }
        $action     = I('get.a');
        $controller = 'Api\\' . str_replace('/', '\\', $controller);
        return [
            'c'=>$controller,
            'a'=>$action
        ];
    }
}