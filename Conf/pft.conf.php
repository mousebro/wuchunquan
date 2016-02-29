<?php
include dirname(__FILE__) . '/env.php';
if (!defined('ENV')) define('EVN', 'PRODUCTION');
if (ENV=='PRODUCTION') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/mnt/log/site');
    define('IMAGE_UPLOAD_DIR', '/databak/images/');
    define('MAIN_DOMAIN', 'http://www.12301.cc/');
    define('IMAGE_URL', 'http://images.12301.cc/');
    define('STATIC_URL', 'http://static.12301.cc/');
    define('MOBILE_URL', 'http://12301.cc/');
    define('IP_INSIDE', '10.160.4.140');//内网IP地址
}
elseif (ENV=='TEST') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/data/log/site');
    define('IMAGE_UPLOAD_DIR', '/databak/images/');
    define('MAIN_DOMAIN', 'http://www.12301dev.com/');
    define('IMAGE_URL', 'http://images.12301dev.com/');
    define('STATIC_URL', 'http://static.12301.cc/');
    define('MOBILE_URL', 'http://12301dev.com/');
    define('IP_INSIDE', '10.117.7.197');
}
elseif (ENV=='DEVELOP') {
    define('PFT_WECHAT_APPID', 'wxd72be21f7455640d');
    define('BASE_WWW_DIR', '/var/www/html/new/d');
    define('BASE_WX_DIR', '/var/www/html/wx');
    define('BASE_LOG_DIR', '/var/www/log/site');
    define('IMAGE_UPLOAD_DIR', '/var/www/images/');
    define('MAIN_DOMAIN', 'http://www.12301.local/');
    define('IMAGE_URL', 'http://images.12301.local/');
    define('STATIC_URL', 'http://static.12301.cc/');
    define('MOBILE_URL', 'http://12301.local/');
    define('IP_INSIDE', '10.0.2.15');
}