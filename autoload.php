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
    public static function autoload($className)
    {
        //根据PSR-O的第4点 把 \ 转换层（目录风格符） DIRECTORY_SEPARATOR ,
        //便于兼容Linux文件找。Windows 下（/ 和 \）是通用的
        //由于namspace 很规格，所以直接很快就能找到
//        $fileName = str_replace('\\', DIRECTORY_SEPARATOR,  DIR . '\\'. $className) . '.php';
        $fileName = str_replace('\\', DIRECTORY_SEPARATOR,  DIR . '\\'. $className) . '.class.php';
        if (is_file($fileName)) {
            require $fileName;
        }
        elseif (is_file($fileName)) {
            require $fileName;
        }
        else {
            echo $fileName . ' is not exist'; die;
        }
    }
}
spl_autoload_register("\\AutoLoading\\loading::autoload");