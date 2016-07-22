<?php
/**
 * 助手类
 * 获取一些之前封装的类
 *
 * @author dwer
 * @date   2016-05-18
 */

namespace Library\Tools;

use \SoapClient;

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

    /**
     * 获取面包屑
     * @author dwer
     * @date   2016-06-19
     *
     * @param $currentPage 当前所处的页面 - plist.html / new/card_index.html
     *
     * @return
     */
    public static function getBreadcrumb($currentPage = false) {
        //加载配置文件
        if($_SERVER['HTTP_HOST']=='yyd.12301.cc'){
            $navFile = HTML_DIR . '/new/d/common/nav_yd.php';
        } else {
            $navFile = HTML_DIR . '/new/d/common/nav.php';
        }

        @include($navFile);
        $pages = isset($pages) ? $pages : [];
        $depth = isset($depth) ? $depth : [];

        //外部没有传当前页的时候去获取
        if($currentPage === false) {
            $tmp         = parse_url($_SERVER['REQUEST_URI']);
            $currentPage = trim($tmp['path'], '/');
        }

        $res = [
            ['pageTitle' => '首页', 'pageUrl' => '/home.html']
        ];

        $parent = isset($pages[$currentPage]) ? $pages[$currentPage]['parent'] : false;
        if($parent !== false) {
            if($currentPage != 'home.html') {
                if(is_numeric($parent)) {
                    $res[] = ['pageTitle' => $depth[$parent], 'pageUrl' => ''];
                    $res[] = ['pageTitle' => $pages[$currentPage]['pageTitle'], 'pageUrl' => ''];
                }
                else {
                    $tmp = $pages[$parent];

                    $res[] = ['pageTitle' => $depth[$tmp['parent']], 'pageUrl' => ''];
                    $res[] = ['pageTitle' => $tmp['pageTitle'], 'pageUrl' => '/' . $parent];
                    $res[] = ['pageTitle' => $pages[$currentPage]['pageTitle'], 'pageUrl' => ''];
                }
            }
        }

        return $res;
    }

    /**
     * 获取左侧菜单栏
     * @author dwer
     * @date   2016-06-19
     *
     * @return
     */
    public static function getLeftBar() {
        $authFile = HTML_DIR . '/new/d/common/auth_config.php';

        @include($authFile);
        $_auth_group = isset($_auth_group) ? $_auth_group : [];
        $_auth       = isset($_auth) ? $_auth : [];

        $dtype    = $_SESSION['sdtype'];
        $qxs      = $dtype == 6 ? explode(",",$_SESSION['qx']) : "all";
        $memberID = $_SESSION['memberID'];

        $qxurls=array();
        foreach($_auth as $model=>$row){
            /*会员类型权限判定*/
            if(!in_array($dtype,explode(",",$row['limit']))) {
                continue;
            }

            /*员工权限判定*/
            if($qxs != "all" && !in_array($model, $qxs)) {
                continue;
            }

            $qxurls = array_merge($qxurls, $row['url']);
        }

        //云顶账号
        $ydArr         = [7132, 7133, 7134, 7135, 7136, 7137, 7157, 27583, 27584 ,27585, 35666, 35668];
        $leftNaviArray = [];

        foreach($_auth as $mod => $row){ 
            if(in_array($memberID, $ydArr)) {
                if($row['url'][0] == 'orderReport.html' || $row['url'][0] == 'buyOrderReport.html' ) {
                    continue;
                }
            }

            if(isset($row['url'][0]) && !in_array($row['url'][0], $qxurls)) {
                continue;
            }

            if(!in_array($dtype, explode(",", $row['limit']))) {
                continue;
            }

            if(isset($row['left']) && $row['left']=="none") {
                continue;
            }

            //地址处理 - 为了兼容二级店铺地址
            if(strpos($_SERVER['REQUEST_URI'], '/new/d/') === false) {
                $row['url'][0] = '/' . $row['url'][0];
            } else {
                //二级店铺比较奇葩都带有/new/d/
                $row['url'][0] = '/new/d/' . $row['url'][0];
            }

            if(isset($leftNaviArray[$row['group']])) {
                $leftNaviArray[$row['group']]['row'][] = $row;
            } else {
                $leftNaviArray[$row['group']] = ['row' => [$row], 'title' => $_auth_group[$row['group']]];
            }
        }

        return $leftNaviArray;
    }

}