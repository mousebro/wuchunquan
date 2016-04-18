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
     * @param  $method
     * @param  $paramArr
     * @return
     */
    public static function genPostData($appId, $secret, $method, $paramArr) {
        $timestamp = time();
        $params     = base64_encode(json_encode($paramArr));
        $signature = $this->genSignature($method, $secret, $timestamp, $params);

        $dataArr = array(
            'app_id'    => $appId,
            'signature' => $signature,
            'params'    => $params,
            'timestamp' => $timestamp,
            'method'    => $method
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

        if(!isset($jsonArr['app_id']) || !isset($jsonArr['signature']) || !isset($jsonArr['params']) || !isset($jsonArr['timestamp']) || !isset($jsonArr['method'])) {
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
     * @param  $method 方法名
     * @param  $secret 密钥
     * @param  $timestamp 时间戳 - 1460823195
     * @param  $params - 参数 - base64_encode(json_encode(参数数组))
     * @return
     */
    public static function genSignature($method, $secret, $timestamp, $params) {
        if(!$method || !$secret || !$timestamp || !$params) {
            return false;
        }
        
        $method    = strval($method);
        $secret    = strval($secret);
        $timestamp = strval($timestamp);
        $params    = strval($params);

        $signature = md5(md5($method . $secret . $timestamp . $params));
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
    public static function checkSignature($method, $secret, $timestamp, $params, $signature) {
        if(!$method || !$secret || !$timestamp || !$params || !$signature) {
            return false;
        }

        $method    = strval($method);
        $secret    = strval($secret);
        $timestamp = strval($timestamp);
        $params    = strval($params);
        $signature = strval($signature);

        //判断时间
        $middleTime = time() - intval($timestamp);
        if($middleTime > self::$_validTime) {
            return false;
        }

        //判断签名
        $originSig = self::genSignature($method, $secret, $timestamp, $params);

        if($originSig == $signature) {
            return true;
        } else {
            return false;
        }
    }
}