<?php
/**
 * Created by PhpStorm.
 * User: guangpeng
 * Date: 4/19-019
 * Time: 22:24
 * file 缓存
 */
namespace Library\Cache;

defined('PFT_INIT') or exit('Access Invalid!');

class CacheFile extends Cache{

    public function __construct($params = array()){
        $this->params['path'] = BASE_LOG_DIR.'/cache';
        $this->enable = true;
    }
    private function write_file($filepath, $data, $mode = NULL) {
        if (!is_array($data) && !is_scalar($data)) {
            return false;
        }
        $data = var_export($data, true);
        $data = '<?php defined(\'PFT_INIT\') or exit(\'Access Invalid!\'); return ' . $data . ';';
        $mode = ($mode == 'append' ? FILE_APPEND : NULL);
        if (false === file_put_contents($filepath, $data, $mode)) {
            return false;
        } else {
            return true;
        }
    }

    private function init(){
        return true;
    }

    private function isConnected(){
        return $this->enable;
    }

    public function get($key, $path=null){
        $filename = realpath($this->_path($key));
        if (is_file($filename)){
            return require($filename);
        }else{
            return false;
        }
    }

    public function set($key, $value, $path=null, $expire=null){
        $filename = $this->_path($key);
        if (false == $this->write_file($filename,$value)){
            return false;
        }else{
            return true;
        }
    }

    public function rm($key, $path=null){
        $filename = realpath($this->_path($key));
        if (is_file($filename)) {
            @unlink($filename);
        }else{
            return false;
        }
        return true;
    }

    private function _path($key){
        switch (strtolower($key)) {
//            case '':
//                $path = BASE_DATA_PATH.'/cache';
//                break;
            default:
                $path = BASE_LOG_DIR.'/cache';
                break;
        }
        return $path.'/'.$key.'.php';
    }
}
?>