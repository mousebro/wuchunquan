<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 2/18-018
 * Time: 15:13
 */
namespace AutoLoading;
define('DIR', dirname(__FILE__));
class loading {
    /**
     * 向PHP注册在自动载入函数
     */
    public static function register(){
        spl_autoload_register(array(new self, 'autoload'));
    }
    public static function autoload($className)
    {
        //根据PSR-O的第4点 把 \ 转换层（目录风格符） DIRECTORY_SEPARATOR ,
        //便于兼容Linux文件找。Windows 下（/ 和 \）是通用的
        //由于namspace 很规格，所以直接很快就能找到
//        $fileName = str_replace('\\', DIRECTORY_SEPARATOR,  DIR . '\\'. $className) . '.php';
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR,  DIR . '\\'. $className) . '.class.php';
        if (is_file($fileName)) {
            include_once $fileName;
        }
        else {
//            echo $fileName .' not found';
           return false;
        }
    }
}