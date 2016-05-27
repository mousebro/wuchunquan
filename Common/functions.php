<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * 为了兼容之前的类库，在这里统一载入之前的全局函数
 *
 * @author dwer
 * @date   2016-05-19
 */
$prevFunctionsFile = '/var/www/html/new/d/common/func.inc.php';
if(file_exists($prevFunctionsFile)) {
    include_once($prevFunctionsFile);
}

/**
 * Think 系统函数库
 */

function utf8Length($str)
{
    $length = strlen(preg_replace('/[\x00-\x7F]/', '', $str));
    if ($length) {
        return strlen($str) - $length + intval($length / 3);
    }
    return strlen($str);
}

function throw_exception($error) {
    if (!defined('IGNORE_EXCEPTION')) {
        showmessage($error, '', 'exception');
    } else {
        exit();
    }
}
/**
 * 向elk日志系统记录日志[elk.12301dev.com]
 *
 * @author Guangpeng Chen
 * @param string $log_name 日志文件名
 * @param mixed $log_message 日志内容，可以为字符串或数组
 */
function write_to_logstash($log_name, $log_message)
{
    $log_dir = BASE_LOG_DIR . '/logstash/' . $log_name .'_' . date('ymd') .'.log';
    $word = json_encode([
        'time'  => date("Y-m-d H:i:s"),
        'client'=> $_SERVER['REMOTE_ADDR'],
        'domain'=> $_SERVER['HTTP_HOST'],
        'status'=> 200,
        'words' => $log_message,
    ],JSON_UNESCAPED_UNICODE);
    file_put_contents($log_dir, $word . "\n", FILE_APPEND);
}
/**
 * 取上一步来源地址
 *
 * @param
 * @return string 字符串类型的返回结果
 */
function getReferer(){
    return empty($_SERVER['HTTP_REFERER'])?'':$_SERVER['HTTP_REFERER'];
}
/**
 * 输出信息
 *
 * @param string $msg 输出信息
 * @param string/array $url 跳转地址 当$url为数组时，结构为 array('msg'=>'跳转连接文字','url'=>'跳转连接');
 * @param string $show_type 输出格式 默认为html
 * @param string $msg_type 信息类型 succ 为成功，error为失败/错误
 * @param string $is_show  是否显示跳转链接，默认是为1，显示
 * @param int $time 跳转时间，默认为2秒
 * @return string 字符串类型的返回结果
 */
function showMessage($msg,$url='',$show_type='html',$msg_type='succ',$is_show=1,$time=2000){
    /**
     * 如果默认为空，则跳转至上一步链接
     */
    $url = ($url!='' ? $url : getReferer());
    $msg_type = in_array($msg_type,array('succ','error')) ? $msg_type : 'error';
    /**
     * 输出类型
     */
    switch ($show_type) {
        case 'json':
            $return = '{';
            $return.= '"msg":"' . $msg . '",';
            $return.= '"url":"' . $url . '"';
            $return.= '}';
            echo $return;
            break;

        case 'exception':
            echo '<!DOCTYPE html>';
            echo '<html>';
            echo '<head>';
            echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            echo '<title></title>';
            echo '<style type="text/css">';
            echo 'body { font-family: "Verdana";padding: 0; margin: 0;}';
            echo 'h2 { font-size: 12px; line-height: 30px; border-bottom: 1px dashed #CCC; padding-bottom: 8px;width:800px; margin: 20px 0 0 150px;}';
            echo 'dl { float: left; display: inline; clear: both; padding: 0; margin: 10px 20px 20px 150px;}';
            echo 'dt { font-size: 14px; font-weight: bold; line-height: 40px; color: #333; padding: 0; margin: 0; border-width: 0px;}';
            echo 'dd { font-size: 12px; line-height: 40px; color: #333; padding: 0px; margin:0;}';
            echo '</style>';
            echo '</head>';
            echo '<body>';
            echo '<h2>' . $lang['error_info'] . '</h2>';
            echo '<dl>';
            echo '<dd>' . $msg . '</dd>';
            echo '<dt><p /></dt>';
            echo '<dd>' . $lang['error_notice_operate'] . '</dd>';
            echo '<dd><p /><p /><p /><p /></dd>';
            echo '<dd><p /><p /><p /><p />Copyright 2013-2016 www.12301.cc , All Rights Reserved </dd>';
            echo '</dl>';
            echo '</body>';
            echo '</html>';
            exit();
            break;

        case 'javascript':
            echo '<script>';
            echo 'alert(\'' . $msg . '\');';
            echo 'location.href=\'' . $url . '\'';
            echo '</script>';
            exit();
            break;
        default:
            break;
    }
    exit;
}
if (!function_exists('C')) {
    /**
     * 获取和设置配置参数 支持批量定义
     * @param string|array $name 配置变量
     * @param mixed $value 配置值
     * @param mixed $default 默认值
     * @return mixed
     */
    function C($name = null, $value = null, $default = null)
    {
        static $_config = array();
        // 无参数时获取所有
        if (empty($name)) {
            return $_config;
        }
        // 优先执行设置获取或赋值
        if (is_string($name)) {
            if (!strpos($name, '.')) {
                $name = strtoupper($name);
                if (is_null($value)) {
                    return isset($_config[$name]) ? $_config[$name] : $default;
                }

                $_config[$name] = $value;
                return null;
            }
            // 二维数组设置和获取支持
            $name    = explode('.', $name);
            $name[0] = strtoupper($name[0]);
            if (is_null($value)) {
                return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : $default;
            }

            $_config[$name[0]][$name[1]] = $value;
            return null;
        }
        // 批量设置
        if (is_array($name)) {
            $_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
            return null;
        }
        return null; // 避免非法参数
    }
}


if (!function_exists('load_config')) {
    /**
     * 动态加载业务配置数据
     * @param $key 配置的键
     * @param $type 配置文件类型，默认business，业务配置

     * @return mixed
     */
    function load_config($key, $type = 'business') {
        static $_load_config = array();

        $key  = strval($key);
        $type = strval($type);

        // 无参数时获取所有
        if (empty($key) || empty($type)) {
            return null;
        }

        //获取配置文件的所有配置
        if(isset($_load_config[$type])) {
            $configArr = $_load_config[$type];
        } else {
            $configFile = HTML_DIR . "Service/Conf/{$type}.conf.php";
            if(file_exists($configFile)) {
                $configArr = include($configFile);
            } else {
                $configArr = array();
            }

            $_load_config[$type] = $configArr;
        }

        if(isset($configArr[$key])) {
            return $configArr[$key];
        } else {
            return null;
        }
    }
}

/**
 * 抛出异常处理
 * @param string $msg 异常消息
 * @param integer $code 异常代码 默认为0
 * @throws Library\Exception
 * @return void
 */
function E($msg, $code = 0)
{
    throw new Library\Exception($msg, $code);
}


/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.'); 获取$_GET
 * </code>
 * @param string $name 变量的名称 支持指定类型
 * @param mixed $default 不存在的时候默认值
 * @param mixed $filter 参数过滤方法
 * @param mixed $datas 要获取的额外数据源
 * @return mixed
 */
function I($name, $default = '', $filter = null, $datas = null)
{
    static $_PUT = null;
    if (strpos($name, '/')) {
        // 指定修饰符
        list($name, $type) = explode('/', $name, 2);
    } elseif (C('VAR_AUTO_STRING')) {
        // 默认强制转换为字符串
        $type = 's';
    }
    if (strpos($name, '.')) {
        // 指定参数来源
        list($method, $name) = explode('.', $name, 2);
    } else {
        // 默认为自动判断
        $method = 'param';
    }
    switch (strtolower($method)) {
        case 'get':
            $input = &$_GET;
            break;
        case 'post':
            $input = &$_POST;
            break;
        case 'put':
            if (is_null($_PUT)) {
                parse_str(file_get_contents('php://input'), $_PUT);
            }
            $input = $_PUT;
            break;
        case 'param':
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input = $_POST;
                    break;
                case 'PUT':
                    if (is_null($_PUT)) {
                        parse_str(file_get_contents('php://input'), $_PUT);
                    }
                    $input = $_PUT;
                    break;
                default:
                    $input = $_GET;
            }
            break;
        case 'path':
            $input = array();
            if (!empty($_SERVER['PATH_INFO'])) {
                $depr  = C('URL_PATHINFO_DEPR');
                $input = explode($depr, trim($_SERVER['PATH_INFO'], $depr));
            }
            break;
        case 'request':
            $input = &$_REQUEST;
            break;
        case 'session':
            $input = &$_SESSION;
            break;
        case 'cookie':
            $input = &$_COOKIE;
            break;
        case 'server':
            $input = &$_SERVER;
            break;
        case 'globals':
            $input = &$GLOBALS;
            break;
        case 'data':
            $input = &$datas;
            break;
        default:
            return null;
    }
    if ('' == $name) {
        // 获取全部变量
        $data    = $input;
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                $filters = explode(',', $filters);
            }
            foreach ($filters as $filter) {
                $data = array_map_recursive($filter, $data); // 参数过滤
            }
        }
    } elseif (isset($input[$name])) {
        // 取值操作
        $data    = $input[$name];
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                if (0 === strpos($filters, '/')) {
                    if (1 !== preg_match($filters, (string) $data)) {
                        // 支持正则验证
                        return isset($default) ? $default : null;
                    }
                } else {
                    $filters = explode(',', $filters);
                }
            } elseif (is_int($filters)) {
                $filters = array($filters);
            }

            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    $filter = trim($filter);
                    if (function_exists($filter)) {
                        $data = is_array($data) ? array_map_recursive($filter, $data) : $filter($data); // 参数过滤
                    } else {
                        $data = filter_var($data, is_int($filter) ? $filter : filter_id($filter));
                        if (false === $data) {
                            return isset($default) ? $default : null;
                        }
                    }
                }
            }
        }
        if (!empty($type)) {
            switch (strtolower($type)) {
                case 'a': // 数组
                    $data = (array) $data;
                    break;
                case 'd': // 数字
                    $data = (int) $data;
                    break;
                case 'f': // 浮点
                    $data = (float) $data;
                    break;
                case 'b': // 布尔
                    $data = (boolean) $data;
                    break;
                case 's': // 字符串
                default:
                    $data = (string) $data;
            }
        }
    } else {
        // 变量默认值
        $data = isset($default) ? $default : null;
    }
    is_array($data) && array_walk_recursive($data, 'think_filter');
    return $data;
}

function array_map_recursive($filter, $data)
{
    $result = array();
    foreach ($data as $key => $val) {
        $result[$key] = is_array($val)
            ? array_map_recursive($filter, $val)
            : call_user_func($filter, $val);
    }
    return $result;
}


/**
 * 判断是否SSL协议
 * @return boolean
 */
function is_ssl()
{
    if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
        return true;
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    }
    return false;
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false)
{
    $type      = $type ? 1 : 0;
    static $ip = null;
    if (null !== $ip) {
        return $ip[$type];
    }

    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }

            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 发送HTTP状态
 * @param integer $code 状态码
 * @return void
 */
function send_http_status($code)
{
    static $_status = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily ', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded',
    );
    if (isset($_status[$code])) {
        header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
        // 确保FastCGI模式下正常
        header('Status:' . $code . ' ' . $_status[$code]);
    }
}

function think_filter(&$value)
{
    // TODO 其他安全过滤

    // 过滤查询特殊字符
    if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN)$/i', $value)) {
        $value .= ' ';
    }
}

if(!function_exists('pft_log')) {

    /**
     * 统一写日志函数，日志统一写入BASE_LOG_DIR下面
     * @author dwer
     * @date   2016-03-07
     *
     * @param string $path 在BASE_LOG_DIR这个下面的目录 - product/reseller_storage
     * @param string $content 需要写入的内容
     * @param string $pathMode 目录分隔模式：
     *                      day：按日切分 - product/reseller_storage/2016/03/23.log
     *                      month：按月切分 - product/reseller_storage/2016/03.log
     *                      year：按年切分 - product/reseller_storage/2016.log
     * @return
     */
    function pft_log($path, $content, $pathMode = 'day') {
        $path    = strval($path);
        $path    = str_replace("\\", '/', trim($path, '/'));
        $content = strval($content);
        if(!$path || !$content) {
            return false;
        }

        $pathMode = in_array($pathMode, array('day', 'month', 'year')) ? $pathMode : 'day';

        $tmpPath = BASE_LOG_DIR . '/' . $path . '/';
        $fileName = date('Y') . '.log';
        if($pathMode == 'day') {
            $tmpPath .= date('Y') . '/' . date('m') . '/';
            $fileName = date('d') . '.log';
        } elseif($pathMode == 'month') {
            $tmpPath .= date('Y') . '/';
            $fileName = date('m') . '.log';
        }

        //如果文件不存在，就创建文件
        if(!file_exists($tmpPath)) {
            $res = mkdir($tmpPath, 0777, true);
            if(!$res) {
                return false;
            }
        }

        //内容写入日志文件
        $file    = $tmpPath . $fileName;
        $content = date('Y-m-d H:i:s') . ' # ' . $content . "\r\n";
        $res     = file_put_contents($file, $content, FILE_APPEND);

        if($res) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * session管理函数
     * @param string|array $name session名称 如果为数组则表示进行session设置
     * @param mixed $value session值
     * @return mixed
     */
    function session($name = '', $value = '')
    {
        $prefix = C('SESSION_PREFIX');
        if (is_array($name)) {
            // session初始化 在session_start 之前调用
            if (isset($name['prefix'])) {
                C('SESSION_PREFIX', $name['prefix']);
            }

            if (C('VAR_SESSION_ID') && isset($_REQUEST[C('VAR_SESSION_ID')])) {
                session_id($_REQUEST[C('VAR_SESSION_ID')]);
            } elseif (isset($name['id'])) {
                session_id($name['id']);
            }
            if ('common' == APP_MODE) {
                // 其它模式可能不支持
                ini_set('session.auto_start', 0);
            }
            if (isset($name['name'])) {
                session_name($name['name']);
            }

            if (isset($name['path'])) {
                session_save_path($name['path']);
            }

            if (isset($name['domain'])) {
                ini_set('session.cookie_domain', $name['domain']);
            }

            if (isset($name['expire'])) {
                ini_set('session.gc_maxlifetime', $name['expire']);
                ini_set('session.cookie_lifetime', $name['expire']);
            }
            if (isset($name['use_trans_sid'])) {
                ini_set('session.use_trans_sid', $name['use_trans_sid'] ? 1 : 0);
            }

            if (isset($name['use_cookies'])) {
                ini_set('session.use_cookies', $name['use_cookies'] ? 1 : 0);
            }

            if (isset($name['cache_limiter'])) {
                session_cache_limiter($name['cache_limiter']);
            }

            if (isset($name['cache_expire'])) {
                session_cache_expire($name['cache_expire']);
            }

            if (isset($name['type'])) {
                C('SESSION_TYPE', $name['type']);
            }

            if (C('SESSION_TYPE')) {
                // 读取session驱动
                $type   = C('SESSION_TYPE');
                $class  = strpos($type, '\\') ? $type : 'Think\\Session\\Driver\\' . ucwords(strtolower($type));
                $hander = new $class();
                session_set_save_handler(
                    array(&$hander, "open"),
                    array(&$hander, "close"),
                    array(&$hander, "read"),
                    array(&$hander, "write"),
                    array(&$hander, "destroy"),
                    array(&$hander, "gc"));
            }
            // 启动session
            if (C('SESSION_AUTO_START')) {
                session_start();
            }

        } elseif ('' === $value) {
            if ('' === $name) {
                // 获取全部的session
                return $prefix ? $_SESSION[$prefix] : $_SESSION;
            } elseif (0 === strpos($name, '[')) {
                // session 操作
                if ('[pause]' == $name) {
                    // 暂停session
                    session_write_close();
                } elseif ('[start]' == $name) {
                    // 启动session
                    session_start();
                } elseif ('[destroy]' == $name) {
                    // 销毁session
                    $_SESSION = array();
                    session_unset();
                    session_destroy();
                } elseif ('[regenerate]' == $name) {
                    // 重新生成id
                    session_regenerate_id();
                }
            } elseif (0 === strpos($name, '?')) {
                // 检查session
                $name = substr($name, 1);
                if (strpos($name, '.')) {
                    // 支持数组
                    list($name1, $name2) = explode('.', $name);
                    return $prefix ? isset($_SESSION[$prefix][$name1][$name2]) : isset($_SESSION[$name1][$name2]);
                } else {
                    return $prefix ? isset($_SESSION[$prefix][$name]) : isset($_SESSION[$name]);
                }
            } elseif (is_null($name)) {
                // 清空session
                if ($prefix) {
                    unset($_SESSION[$prefix]);
                } else {
                    $_SESSION = array();
                }
            } elseif ($prefix) {
                // 获取session
                if (strpos($name, '.')) {
                    list($name1, $name2) = explode('.', $name);
                    return isset($_SESSION[$prefix][$name1][$name2]) ? $_SESSION[$prefix][$name1][$name2] : null;
                } else {
                    return isset($_SESSION[$prefix][$name]) ? $_SESSION[$prefix][$name] : null;
                }
            } else {
                if (strpos($name, '.')) {
                    list($name1, $name2) = explode('.', $name);
                    return isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
                } else {
                    return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
                }
            }
        } elseif (is_null($value)) {
            // 删除session
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                if ($prefix) {
                    unset($_SESSION[$prefix][$name1][$name2]);
                } else {
                    unset($_SESSION[$name1][$name2]);
                }
            } else {
                if ($prefix) {
                    unset($_SESSION[$prefix][$name]);
                } else {
                    unset($_SESSION[$name]);
                }
            }
        } else {
            // 设置session
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                if ($prefix) {
                    $_SESSION[$prefix][$name1][$name2] = $value;
                } else {
                    $_SESSION[$name1][$name2] = $value;
                }
            } else {
                if ($prefix) {
                    $_SESSION[$prefix][$name] = $value;
                } else {
                    $_SESSION[$name] = $value;
                }
            }
        }
        return null;
    }
}
if (!function_exists('curl_post')) {
    /**
     * CURL 提交请求数据
     *
     * @author dwer
     * @date   2016-04-11
     * @param string $url 请求URL
     * @param string $postData 请求发送的数据
     * @param int $port 请求端口
     * @param int $timeout 超时时间
     * @param string $logPath 错误日志文件
     * @return bool|mixed
     */
    function curl_post($url,$postData, $port=80, $timeout=15, $logPath='/api/curl_post.log', $http_headers=[]) {
        $ch = curl_init();
        $basePath = strpos($logPath, BASE_LOG_DIR)!==false ?  '' : BASE_LOG_DIR;
        $logPath = $basePath . $logPath;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PORT, $port);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if (count($http_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $res = curl_exec($ch);
        //错误处理
        $errCode = curl_errno($ch);
        if ($errCode > 0) {
            //记录日志
            $logData = json_encode(array(
                'err_code' => $errCode,
                'err_msg'  => curl_error($ch)
            ));
            pft_log($logPath, $logData);
            curl_close($ch);
            //返回false
            return false;
        } else {
            //获取HTTP码
            $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if($httpCode != 200) {
                //接口错误
                $logData = json_encode(array(
                    'err_code' => $httpCode,
                    'err_msg'  => $res
                ));
                pft_log($logPath, $logData);
                curl_close($ch);
                return false;
            } else {
                curl_close($ch);
                return $res;
            }
        }
    }
}

if (!function_exists('get_obj_instance')) {
    /**
     * 取得对象实例
     *
     * @param string $class 类名
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     * @throws Exception
     */
    function get_obj_instance($class, $method = '', $args = array()) {
        static $_cache = array();
        $key = $class . $method . (empty($args) ? NULL : md5(serialize($args)));
        if (isset($_cache[$key])) {
            return $_cache[$key];
        }
        else if (class_exists($class)) {
            $obj = new $class();
            if (method_exists($obj, $method))
            {
                if (empty($args)) {
                    $_cache[$key] = $obj->$method();
                }
                else {
                    $_cache[$key] = call_user_func_array(array(&$obj,
                        $method
                    ) , $args);
                }
            }
            else {
                $_cache[$key] = $obj;
            }
            return $_cache[$key];
        } else {
            throw new Exception('Class ' . $class . ' isn\'t exists!');
        }
    }
}

