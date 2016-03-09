<?php
/**
 * 简单的控制器类
 * @author dwer
 *
 * @date 2016-03-04
 * 
 */

namespace Library;

class Controller {

    /**
     *  
     * @author dwer
     * @date   2016-03-04
     *
     * @param  string $modelName 模型名称 Product/SellerStorage
     * @return [type]
     */
    public function model($modelName){
        $realModel = 'Model\\' . str_replace('/', '\\', $modelName);
        if(!class_exists($realModel)) {
            return false;
        } else {
            return new $realModel();
        }
    }

    /**
     * 接口数据返回
     * @author dwer
     * @DateTime 2016-02-16T13:48:27+0800
     * 
     * @param    int                      $code 返回码
     * @param    array                    $data 接口返回数据
     * @param    string                   $msg  错误说明，默认为空
     * @return                           
     */
    public function apiReturn($code, $data = array(), $msg = '') {
        $data = array(
            'code' => $code,
            'data' => $data,
            'msg'  => $msg
        );

        header('Content-type:text/json');
        $res = json_encode($data);
        echo $res;
        exit();
    }

    /**
     * 获取参数
     * 
     * @author dwer
     * @DateTime 2016-02-18T17:34:13+0800
     * @param    [type]                   $key  获取的key
     * @param    string                   $type 类型 post, get
     * 
     * @return   [type]                         如果值不存在，返回false
     */
    public function getParam($key, $type = 'post') {
        $typeArr = array('get', 'post');
        if(!in_array($type, $typeArr)) {
            $type = 'post';
        }

        if($type == 'post') {
            $tmp = $_POST;
        } else {
            $tmp = $_GET;
        }

        if(!isset($tmp[$key])) {
            return false;
        } else {
            return $tmp[$key];
        }
    }

    /**
     * 判断是不是已经登陆
     * @author dwer
     * @DateTime 2016-02-16T13:55:07+0800
     * 
     * @param    string  $type 指定请求类型
     *                         ajax : ajax请求
     *                         html : 页面请求
     *                         auto : 自动判定
     * @return   mixed
     *  
     */
    public function isLogin($type = 'auto') {
        $typeArr = array('ajax', 'html');

        if($type == 'auto') {
            $r = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
            if($r == 'xmlhttprequest') {
                $type = 'ajax';
            } else {
                $type = 'html';
            }
        }

        if(!in_array($type, $typeArr)) {
            $type = 'ajax';
        }

        //判断登录
        $memberSID = isset($_SESSION['sid']) && $_SESSION['sid'] ? $_SESSION['sid'] : false;

        if($type == 'ajax') {
            if($memberSID) {
                return $memberSID;
            } else {
                $this->apiReturn(102, array(), '未登录');
            }
        } else {
            if($memberSID) {
                return $memberSID;
            } else {
                //跳转到首页
                $backUrl = '/';
                $stript = "<script>location.href='{$backUrl}';</script>";
                echo $stript;
                exit();
            }
        }
    }
}
?>