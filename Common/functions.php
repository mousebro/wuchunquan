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
 * Think 系统函数库
 */


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
     * @param  $path 在BASE_LOG_DIR这个下面的目录 - product/reseller_storage
     * @param  $content 需要写入的内容 
     * @param  $pathMode 目录分隔模式：
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

        $pathMode = in_array($pathMode, ['day', 'month', 'year']) ? $pathMode : 'day';

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
