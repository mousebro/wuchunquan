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
        $_POST  = $res['paramsArr'];
        $_POST['app_id'] = $res['app_id'];
        $method = $res['method'];

        //处理路由
        $_routeAr = self::_handleRoute($method);
        if(!$_routeAr) {
            self::error(400, 'Method Not Exist');
        }

        $key      = $_routeAr['c'] . '_' . $_routeAr['a'];
        if (class_exists($_routeAr['c'])){
            $_object = new $_routeAr['c']();
        } else {
            self::error(400, 'Method Not Exist');
        }

        if (method_exists($_routeAr['c'], $_routeAr['a'])) {
            $_codeAr[$key] = $_object->$_routeAr['a']();
        } else {
            self::error(400, 'Method Not Exist');
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
        $method    = $jsonArr['method'];

        $res = Auth::checkSignature($method, $secret, $timestamp, $params, $signature);

        //记录访问日志
        unset($jsonArr['params']);
        $jsonArr['auth'] = $res ? 1 : 0;
        $jsonArr['access_ip'] = $_SERVER['REMOTE_ADDR'];
        pft_log('pft_api_access/log', json_encode($jsonArr));

        if(!$res) {
            return false;
        } else {
            return array('paramsArr' =>$paramsArr, 'method' => $method, 'app_id'=>$appId);
        }
    }

    /**
     * 从传过来的参数中获取路由信息
     * @author dwer
     * @date   2016-04-18
     *
     * @param $method
     * @return 
     */
    private static function _handleRoute($method){
        $method = strval($method);
        if(!$method) {
            return false;
        }

        $methodArr = explode('_', $method);
        $length = count($methodArr);
        if($length <= 1 || $length > 3) {
            return false;
        }

        $controller = '';
        $action     = '';
        for($i = 0; $i < $length; $i++) {
            if(!$methodArr[$i]) {
                return false;
            }

            if($i < ($length-1)) {
                $controller .= '/' . $methodArr[$i];
            } else {
                $action = $methodArr[$i];
            }
        }

        $controller = trim($controller, '/');
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