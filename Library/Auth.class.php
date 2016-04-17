<?php
/**
 * 授权密钥生成接口
 * @author zdw
 * @date 2016-04-14
 * 
 */
namespace Library;

class Auth {
    private static $_validTime = 30 * 60; //30分钟内有效

    /**
     * 生成接口需要post的参数
     * @author dwer
     * @date   2016-04-17
     *
     * @param  $appId
     * @param  $secret
     * @param  $controller
     * @param  $action
     * @param  $paramArr
     * @return
     */
    public static function genPostData($appId, $secret, $controller, $action, $paramArr) {
        $timestamp = time();
        $params     = base64_encode(json_encode($paramArr));
        $signature = $this->genSignature($controller, $action, $secret, $timestamp, $params);

        $dataArr = array(
            'app_id'    => $appId,
            'signature' => $signature,
            'params'    => $params,
            'timestamp' => $timestamp
        );

        return json_encode($dataArr);
    }

    /**
     * 解析接口数据
     * @author dwer
     * @date   2016-04-17
     *
     * @param  $jsonData json数据
     * @return
     */
    public static function resolveData($jsonData) {
        $jsonArr = @json_decode($jsonData);

        if(!$jsonArr) {
            return false;
        }
        $jsonArr = (array)$jsonArr;

        if(!isset($jsonArr['app_id']) || !isset($jsonArr['signature']) || !isset($jsonArr['params']) || !isset($jsonArr['timestamp'])) {
            return false;
        }

        $paramsArr = @json_decode(base64_decode($jsonArr['params']));
        $paramsArr = (array)$paramsArr;
        if(!is_array($paramsArr)) {
            return false;
        }

        $jsonArr['paramsArr'] = $paramsArr;

        return $jsonArr;
    }

    /**
     * 生成签名
     * @author dwer
     * @date   2016-04-16
     *
     * @param  $action 控制器 url中的c参数
     * @param  $controller 方法 url中的a参数
     * @param  $secret 密钥
     * @param  $timestamp 时间戳 - 1460823195
     * @param  $params - 参数 - base64_encode(json_encode(参数数组))
     * @return
     */
    public static function genSignature($controller, $action, $secret, $timestamp, $params) {
        if(!$controller || !$action || !$secret || !$timestamp || !$params) {
            return false;
        }

        $controller = strval($controller);
        $action     = strval($action);
        $secret     = strval($secret);
        $timestamp  = strval($timestamp);
        $params     = strval($params);

        $signature = md5($controller . $action . $secret . $timestamp . $params);
        return $signature;
    }

    /**
     * 验证签名
     * @author dwer
     * @date   2016-04-16
     *
     * @param  $action 控制器 url中的c参数
     * @param  $controller 方法 url中的a参数
     * @param  $secret 密钥
     * @param  $timestamp 时间戳 - 1460823195
     * @param  $params 参数 - base64_encode(json_encode(参数数组))
     * @param  $signature 待验证的签名 
     * @return
     */
    public static function checkSignature($controller, $action, $secret, $timestamp, $params, $signature) {
        if(!$controller || !$action || !$secret || !$timestamp || !$params || !$signature) {
            return false;
        }

        $controller = strval($controller);
        $action     = strval($action);
        $secret     = strval($secret);
        $timestamp  = strval($timestamp);
        $params     = strval($params);
        $signature  = strval($signature);

        //判断时间
        $middleTime = time() - intval($timestamp);
        if($middleTime > self::$_validTime) {
            return false;
        }

        //判断签名
        $originSig = self::genSignature($controller, $action, $secret, $timestamp, $params);

        if($originSig == $signature) {
            return true;
        } else {
            return false;
        }
    }
}