<?php
include dirname(__FILE__) . '/env.php';
if (!defined('ENV')) define('EVN', 'PRODUCTION');
if (ENV=='PRODUCTION') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/alidata/log/site');
    define('IMAGE_UPLOAD_DIR', '/alidata/images/');
    define('MAIN_DOMAIN', 'http://www.12301.cc/');
    define('PAY_DOMAIN', 'http://pay.12301.cc/');
    define('MOBILE_DOMAIN', 'http://wx.12301.cc/');
    define('IMAGE_URL', 'http://images.12301.cc/');
    define('STATIC_URL', 'http://static.12301.cc/');
    define('LOCAL_DIR', '');
    define('OPEN_URL', 'http://open.12301.cc/');
    define('MOBILE_URL', 'http://12301.cc/');
    define('IP_INSIDE', '10.132.33.244');//内网IP地址
    define('IP_TERMINAL', '121.40.69.184');//终端服务器IP
}
elseif (ENV=='TEST') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/data/log/site');
    define('IMAGE_UPLOAD_DIR', '/databak/images/');
    define('MAIN_DOMAIN', 'http://www.12301dev.com/');
    define('PAY_DOMAIN', 'http://pay.12301dev.com/');
    define('MOBILE_DOMAIN', 'http://wx.12301dev.com/');
    define('IMAGE_URL', 'http://images.12301dev.com/');
    define('STATIC_URL', 'http://static.12301dev.com/');
    define('LOCAL_DIR', '');
    define('OPEN_URL', 'http://open.12301dev.com/');
    define('MOBILE_URL', 'http://12301dev.com/');
    define('IP_INSIDE', '10.117.7.197');
    define('IP_TERMINAL', '121.43.119.39');
}
elseif (ENV=='DEVELOP') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/var/www/log/site');
    define('IMAGE_UPLOAD_DIR', '/var/www/images/');
    define('PAY_DOMAIN', 'http://pay.12301.test/');
    if (strpos($_SERVER['HTTP_HOST'], 'test')) {
        define('MAIN_DOMAIN', 'http://www.12301.test/');
        define('MOBILE_DOMAIN', 'http://wx.12301.test/');
        define('IMAGE_URL', 'http://images.12301.test/');
        define('STATIC_URL', 'http://static.12301.test/');
        define('LOCAL_DIR', '');
        define('MOBILE_URL', 'http://12301.test/');
        define('OPEN_URL', 'http://open.12301.test/');
    }
    else {
        define('MOBILE_DOMAIN', 'http://wx.12301.local/');
        define('MAIN_DOMAIN', 'http://www.12301.local/');
        define('IMAGE_URL', 'http://images.12301.test/');
        define('STATIC_URL', 'http://static.12301.test/');
        define('LOCAL_DIR', '');
        define('MOBILE_URL', 'http://12301.local/');
        define('OPEN_URL', 'http://open.12301.local/');
    }
    define('IP_INSIDE', '192.168.20.138');
    define('IP_TERMINAL', '192.168.20.138');
}

//定义html目录的路径，方便后面的文件查找
define('HTML_DIR', '/var/www/html');
//定义配置文件路径
define('CONF_DIR', HTML_DIR . '/Service/Conf');
//定义新的模板路径
define('VIEWS', HTML_DIR . '/Views');

//定义前端新页面需要用到的域名 - 因为二级店铺是这样的
if(isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/new/d/') !== false) {
    define('PREFIX_DOMAIN', 'http://' . $_SERVER['HTTP_HOST'] . '/new/d/');
} else {
    define('PREFIX_DOMAIN', MAIN_DOMAIN);
}
