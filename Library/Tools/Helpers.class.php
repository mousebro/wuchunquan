<?php
/**
 * 助手类
 * 获取一些之前封装的类
 *
 * @author dwer
 * @date   2016-05-18
 */

namespace Library\Tools;

class Helpers {
    static private $_prevPath = '/var/www/html/new/';

    /**
     * 获取之前的数据库连接
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public static function getPrevDb() {
        $dbFile = self::$_prevPath . 'conf/le.je';

        if(file_exists($dbFile)) {
            include_once($dbFile);
            $GLOBALS['le'] = new \go_sql();
            $GLOBALS['le']->connect();

            return $GLOBALS['le'];
        } else {
            return false;
        }
    }

    /**
     * 加载之前封装的类
     * @author dwer
     * @date   2016-05-18
     *
     * @param  $cls
     * @return
     */
    public static function loadPrevClass($cls) {
        $file = self::$_prevPath . 'd/class/' . $cls . '.class.php';
        if(file_exists($file)) {
            include_once($file);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取内部soap接口实例
     * @author dwer
     * @date   2016-05-18
     *
     * @return SoapClient
     */
    public static function GetSoapInside() {
        $ac = '16ucom';
        $pw = 'c33367701511b4f6020ec61ded352059';

        $param = array(
            "location"  => "http://localhost/open/openService/pft_insideMX.php",
            "uri"       => "www.16u.com?ac_16u={$ac}|pw_16u={$pw}|auth_16u=true");

        return new \SoapClient(null, $param);
    }

    /**
     * 票付通外部接口实例（wsdl版本）
     * @author dwer
     * @date   2016-05-18
     *
     * @return SoapClient
     */
    public static function GetSoapWsdl() {
        $url   = 'http://open.12301.cc/openService/MXSE.wsdl';
        $param = array('encoding' =>'UTF-8','cache_wsdl' => 0);

        return new \SoapClient($url, $param);
    }
}