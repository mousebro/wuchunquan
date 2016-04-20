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

    const  UN_LOGIN         = 0;//未登录
    const  CODE_SUCCESS     = 200;//200 OK
    const  CODE_CREATED     = 201;//sql execute fail
    const  CODE_NO_CONTENT  = 204;//没有数据
    const  CODE_INVALID_REQUEST  = 400;//Bad Request
    const  CODE_AUTH_ERROR       = 401;//认证失败
    const  CODE_METHOD_NOT_ALLOW = 405;

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
     * 是否为POST提交
     *
     * @author Guangpeng Chen
     * @return bool
     */
    public static function isPost()
    {
        return $_SERVER['REQUEST_METHOD']==='POST';
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
        $res = json_encode($data, JSON_UNESCAPED_UNICODE);
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
    /**
     * Ajax方式返回数据到客户端
     *
     * @access protected
     *
     * @param mixed  $data        要返回的数据
     * @param String $type        AJAX返回数据格式
     * @param int    $json_option 传递给json_encode的option参数
     *
     * @return void
     */
    protected function ajaxReturn($data, $type = '', $json_option = 0)
    {
        if (empty($type)) {
            $type = C('DEFAULT_AJAX_RETURN');
        }
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data, $json_option));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $handler = isset($_GET[C('VAR_JSONP_HANDLER')])
                    ? $_GET[C('VAR_JSONP_HANDLER')]
                    : C('DEFAULT_JSONP_HANDLER');
                exit($handler . '(' . json_encode($data, $json_option) . ');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
            default     :
                exit;
        }
    }
    /**
     * 通过curl提交数据
     * @param $url
     * @param $data
     *
     * @return mixed
     */
    public function raw_post($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $rt=curl_exec($ch);
        curl_close($ch);
        return $rt;
    }
}
?>