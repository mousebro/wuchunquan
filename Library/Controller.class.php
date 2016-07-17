<?php
/**
 * 简单的控制器类
 * @author dwer
 *
 * @date 2016-03-04
 * 
 */

namespace Library;
use Library\Tools\Helpers;

class Controller {

    const  UN_LOGIN         = 0;//未登录
    const  CODE_SUCCESS     = 200;//200 OK
    const  CODE_CREATED     = 201;//sql execute fail
    const  CODE_NO_CONTENT  = 204;//没有数据
    const  CODE_INVALID_REQUEST  = 400;//Bad Request
    const  CODE_AUTH_ERROR       = 401;//认证失败
    const  CODE_METHOD_NOT_ALLOW = 405;

    /**
     * 模板参数信息
     *
     * @var array
     */
    protected $setOptions   = array();

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
     * 判断登陆用户是不是管理员
     * @author dwer
     * @date   2016-07-16
     *
     * @return
     */
    protected function isSuper() {
        $sid = isset($_SESSION['sid']) && $_SESSION['sid'] ? $_SESSION['sid'] : false;

        if($sid && $sid == 1) {
            return true;
        } else {
            return false;
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
     * 判断是否ajax请求
     * @return boolean [description]
     */
    protected function isAjax() {
       if ( (isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
            || !empty($_POST[C('VAR_AJAX_SUBMIT')]) 
            || !empty($_GET[C('VAR_AJAX_SUBMIT')]) ) {

            return true;
       }
       return false;
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

    public static function getSoap()
    {
        $ac     = '16ucom';
        $pw     = 'c33367701511b4f6020ec61ded352059';
        $soap = new \SoapClient(null,array(
            "location" => "http://localhost/open/openService/pft_insideMX.php",
            "uri" => "www.16u.com?ac_16u={$ac}|pw_16u={$pw}|auth_16u=true"));
        return $soap;
    }

    /**
     * 设置视图变量
     *
     * @access public
     * @param mixed $key    视图变量名
     * @param string $value 视图变量数值
     * @return mixed
     */
    protected function assign($key, $value = null){
        if(!$key) {
           return false;
        }
        if(is_array($key)) {
           foreach ($key as $k=>$v){
             $this->setOptions[$k] = $v;
           }
        }else{
           $this->setOptions[$key] = $value;
        }

        return true;
    }

    /**
     * 缓存重写分析
     *
     * 判断缓存文件是否需要重新生成. 返回true时,为需要;返回false时,则为不需要
     * @access protected
     * @param string $view_file     视图文件名
     * @param string $compile_file  视图编译文件名
     * @return boolean
     */
    protected function isCompile($view_file, $compile_file) {
        if(is_file($compile_file) && (filemtime($compile_file) >= filemtime($view_file))){
           return false;;
        }else{
           return true;
        }
    }

    /**
     * 生成视图编译文件
     *
     * @access protected
     * @param string $compile_file 编译文件名
     * @param string $content   编译文件内容
     * @return void
     */
    protected function createCompileFile($compileFile, $content) {
        //分析编译文件目录
        $compileDir = dirname($compileFile);

        if(!is_dir($compileDir)) {
            mkdir($compileDir, 0777, true);
        }else if(!is_writable($compileDir)) {
           chmod($compileDir, 0777);
        }

        return file_put_contents($compileFile, $content, LOCK_EX);
    }

    /**
     * 显示视图文件
     *
     * @access public
     * @param string $fileName 视图名 - card/index
     * @return void
     */
    public function display($fileName = null) {
        //视图变量
        if(!empty($this->setOptions)) {
           extract($this->setOptions, EXTR_PREFIX_SAME, 'data');
           $this->setOptions = array();
        }

        if(is_null($fileName)) {
           exit("文件名不能为空");
        }

        $viewPath       = defined('HTML') ? HTML . '/Views/' : '/var/www/html/Views/';
        $compilePath    = defined('HTML') ? HTML . '/Compile/' : '/var/www/html/Compile/';

        //如果视图文件命名包含'/'下划线，加载子目录视图，一层目录已满足大部分应用，因此框架这里只支持一层目录
        if(strpos($fileName, '/')) {
           $_tmpAr      = explode('/', $fileName);
           $path        = $_tmpAr[0];
           $fileName    = $_tmpAr[1];
           $viewFile    = $viewPath . $path . '/' . $fileName . '.html';
           $compileFile = $compilePath . $path . '/' . $fileName . '.cache.php';
        }else {
           $viewFile    = $viewPath . $fileName . '.html';
           $compileFile = $compilePath . $fileName . '.cache.php';
        }

        if($this->isCompile($viewFile, $compileFile)) {
           $viewContent = file_get_contents($viewFile);
           $this->createCompileFile($compileFile, $viewContent);
        }

        //加载编译缓存文件
        include $compileFile;
    }

    /**
     * 加载头部和尾部的定制信息
     * @author dwer
     * @date   2016-06-19
     *
     * @return 
     */
    protected function loadHConfig() {
        $httphost   = $_SERVER['HTTP_HOST'];
        $host       = str_replace('www.', '', $httphost);

        //加载配置
        $h_config = load_config('h_config', 'hconfig');

        if (!isset($h_config[$httphost])) {
            $cnt_dot = substr_count($httphost, '.');
            $isSubDomain = false;
            if ($cnt_dot==2) {
                if ( strpos($_SERVER['HTTP_HOST'], 'www') ===false
                        && strpos($_SERVER['HTTP_HOST'], '12301.cc')!==false ) {
                    $isSubDomain = true;
                }
            } elseif ($cnt_dot==3) {
                $isSubDomain = true;
            }

            if ($isSubDomain) {
                if (!isset($redis)) {
                    $redis = \Library\Cache\RedisCache::Connect();
                }

                //二级域名处理
                //step1:获取供应商账号，再获取id
                $applyAccount   = substr($host, 0, strpos($host, '12301')-1);
                $redis_key      = "shop_{$applyAccount}";
                $shop           = $redis->get($redis_key);

                if (!$shop) {
                    //从数据库获取二级店铺信息
                    $subModel = $this->model('Subdomain/SubdomainInfo');
                    $shop = $subModel->getBindedSubdomainInfo($applyAccount, 'account');
                    unset($subModel);

                    if ($shop) {
                        $redis->setex($redis_key, 1800, serialize($shop));
                    } else {
                        exit('404');
                    }
                } else {
                    $shop = unserialize($shop);
                }

                $_SESSION['is_sub_domain'] = $shop['fid'];

                $h_config[$httphost]['name'] = $shop['M_name'];
                $h_config[$httphost]['logo'] = $shop['M_logo1'];
                $h_config[$httphost]['navi'] = 0;
                $h_config[$httphost]['tel']  = $shop['M_tel'] .'&nbsp;&nbsp;&nbsp;'. $shop['M_qq'];
                $h_config[$httphost]['host'] = ($shop['M_host'])? $shop['M_host']:$shop['M_domain'].'.12301.cc';
                $h_config[$httphost]['addr'] = $shop['M_addr'];
            }
        }

        $showQQ = load_config('showQQ', 'hconfig');
        $unshowAccountQQ = load_config('unshowAccountQQ', 'hconfig');

        //将配置信息设置到模板中
        $this->assign('h_config', $h_config);
        $this->assign('httphost', $httphost);
        $this->assign('host', $host);

        $this->assign('showQQ', $showQQ);
        $this->assign('unshowAccountQQ', $unshowAccountQQ);
    }

    /**
     * 初始化页面信息
     * @author dwer
     * @date   2016-06-20
     *
     * @return 
     */
    protected function initPage() {
        //登陆判断
        $this->isLogin('html');

        //加载头部和尾部的定制信息
        $this->loadHConfig();

        //加载面包屑
        $breadCrumb = Helpers::getBreadcrumb();

        //加载侧边栏
        $leftBar = Helpers::getLeftBar();
        
        $this->assign('breadcrumb', $breadCrumb);
        $this->assign('leftbar', $leftBar);
    }
}
?>