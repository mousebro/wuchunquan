<?php

/**
 * User: Fang
 * Time: 15:10 2016/3/8
 */
namespace Action;

use Library\Controller;

class BaseAction extends Controller
{
    public function isLogin()
    {
        $USER = \session();
        if (empty($USER) || ($USER['sid'] == '')) {
            $this->responseError(201, '用户未登录');
        } else {
            return $USER;
        }

    }

    public function successReturn(
       $data='', $msg = '操作成功'
    ){
        $requestType = $this->getRequestType();
        if ($requestType == 'ajax') {
            $this->ajaxReturn(200, $data, $msg);
        } else {
            if (!empty($errorHandler) && function_exists($errorHandler)) {
                call_user_func($errorHandler);
            }else{
               print_r($data);
               exit;
            }
        }
    }
    public function errorReturn(
        $code = 203,
        $msg = '参数错误',
        $errorHandler = '',
        $time=0
    ) {
        $requestType = $this->getRequestType();
        if ($requestType == 'ajax') {
            $this->ajaxReturn($code, '', $msg);
        } else {
            if (!empty($errorHandler) && function_exists($errorHandler)) {
                call_user_func($errorHandler);
            }else{
                $this->redirectPage('home.html',$time,$msg);
            }
        }
        exit;
    }

    private function getRequestType()
    {
        $r = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
        if ($r == 'xmlhttprequest') {
            $type = 'ajax';
        } else {
            $type = 'html';
        }

        return $type;
    }

    /**
     * @param string $url 网站根目录相对地址
     */
    public function redirectPage($url = 'dlogin_n.html',$time=0,$msg='')
    {

        $url = MAIN_DOMAIN.$url;
        if (!headers_sent()) {
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo ($msg);
            }
            exit();
        } else {
            $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            if (0 != $time) {
                $str .= $msg;
            }
            exit($str);
        }
    }

    /**
     * 返回json格式的数据
     *
     * @param mixed $code
     * @param string $data
     * @param string $msg
     * @param string $type
     * @param int $json_option
     *
     * @return string
     */
    public function ajaxReturn(
        $code,
        $data = '',
        $msg = '',
        $type = 'JSON',
        $json_option = 0
    ) {
        $return = array(
            'code' => $code,
            'data' => $data,
            'msg'  => $msg,
        );

        parent::ajaxReturn($return, $type, $json_option);
    }

    /**
     * 测试时记录日志
     */
    public static function writeLog($word, $file)
    {
        $rootpath = '/var/www/html/log/site';
        $filepath = $rootpath . $file . '.txt';
        if (is_array($word)) {
            $word = implode('|', $word);
        }
        $word = date("Y-m-d H:i:s") . "|" . $word . ".";
        if (file_exists($filepath)) {
            $maxsize = 1024 * 1024 * 10;
            $filesize = filesize($filepath);
            $tmp = explode(".", $filepath);
            $filetype = end($tmp);
            $filename = substr($filepath, 0, strpos($filepath, '.'));
            if ($filesize > $maxsize)
                rename($filepath, $filename . '_' . date('YmdHis') . '.' . $filetype);
        }
        $fp = fopen($filepath, "a");
        flock($fp, LOCK_EX);
        fwrite($fp, $word . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
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