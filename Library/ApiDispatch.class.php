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

        $res = self::_auth();
        if($res === false) {
            self::error(401, '未授权');
        }

        //设置传过来的参数
        $_POST = $res;

        //处理路由
        $_routeAr = self::_getRequestRoute();
        $key      = $_routeAr['c'] . '_' . $_routeAr['a'];
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
        $controller = strval(I('get.c'));
        $action     = strval(I('get.a'));

        if(!$controller || !$action) {
            return false;
        }
        
        $jsonData = self::_getPostData();
        if(!$jsonData) {
            return false;
        }

        $jsonArr = Auth::resolveData($jsonData);
        if(!$jsonArr) {
            return false;
        }

        $appId = $jsonArr['app_id'];
        $secret = self::_getSecret($appId);
        if(!$secret) {
            return false;
        }

        $timestamp = $jsonArr['timestamp'];
        $params    = $jsonArr['params'];
        $signature = $jsonArr['signature'];
        $paramsArr = $jsonArr['paramsArr'];
        $res = Auth::checkSignature($controller, $action, $secret, $timestamp, $params, $signature);

        //记录访问日志
        unset($jsonArr['params']);
        $jsonArr['auth'] = $res ? 1 : 0;
        pft_log('pft_api_access/log', json_encode($jsonArr));

        if(!$res) {
            return false;
        } else {
            return $paramsArr;
        }
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

    /**
     * 读取post过来的原始数据
     * @author dwer
     * @date   2016-04-17
     *
     * @return 
     */
    private static function _getPostData() {
        $data = file_get_contents("php://input");
        return $data;
    }

    /**
     * 根据用户的APPID获取密钥
     * @author dwer
     * @date   2016-04-17
     *
     * @param  $appId
     * @return
     */
    private static function _getSecret($appId) {
        $authFile = dirname(__DIR__) . '/Conf/auth.conf.php';

        if(!file_exists($authFile)) {
            return false;
        }

        $authArr = include($authFile);
        if(!$authArr || !isset($authArr[$appId])) {
            return false;
        }

        return $authArr[$appId];
    } 
}