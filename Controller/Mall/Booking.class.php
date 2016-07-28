<?php

namespace Controller\Mall;

use Library\Controller;
use Model\Product\Ticket;
use Model\Member\Member;

class Booking extends Controller {

    /**
     * 获取产品库存
     * @return [type] [description]
     */
    public function getStorage() {
        $pids = explode('-', I('pids'));

        if (count($pids) == 0) {
            $this->apiReturn(400, [], '参数错误');
        }

        if (I('date')) {
            $today_timestamp = strtotime(date('Y-m-d'));
            $submit_timestamp = strtotime(I('date'));

            if ($submit_timestamp < $today_timestamp) {
                $this->apiReturn(400, [], '请选择正确的日期');
            }
        }

        $ptype = $this->model('Product\Ticket')->getProductType($pids[0]);

        $date = I('date') ?: date('Y-m-d');

        if ($ptype == 'A') {
            //普通产品获取库存
            $storages = $this->_getStorageForCommon($pids, $date);
        } elseif ($ptype == 'H') {
            //获取演出类库存
            $storages = $this->_getStorageForShow($pid[0], 6970);
        }

        $this->apiReturn(200, $storages, '');

    }

    /**
     * 获取普通产品的库存
     * @param  array  $pids 产品id数组，[3026, 3027]
     * @param  string $date 日期
     * @return [type]       [description]
     */
    private function _getStorageForCommon($pids, $date) {

        $TicketModel = $this->model('Product\Ticket');

        $memberid = $aid = 0;
        $shop_id = $this->_getSupplyId();

        $storages = [];
        foreach ($pids as $pid) {
            if (!$TicketModel->isSelfApplyProduct($shop_id, $pid)) {

                $tinfo = $TicketModel->getTicketInfoByPid($pid);
                $memberid = $shop_id;
                $aid = $tinfo['apply_did'];

            } else {
                //分销商库存
                if (isset($_SESSION['dtype']) && in_array($_SESSION['dtype'], [0,1])) {

                    $memberid = $_SESSION['memberID'];
                    $aid = I('aid', '', 'intval');

                } else {

                    $memberid = $aid = I('aid', '', 'intval');

                }
            }

            $storage = $TicketModel->getMuchStorage([$pid], $date, $memberid, $aid);
            $storages[$pid] = (int)$storage[$pid];

        }
        return $storages;
    }

    /**
     * 根据域名解析微商城所属供应商id
     * @return [type] [description]
     */
    private function _getSupplyId() {

        $shop_account = (int)explode('.', $_SERVER['HTTP_HOST'])[0];
        $shop_own = $this->model('Member/Member')->getMemberInfo($shop_account, 'account');

        return $shop_own['id'];

    }


    private function _getStorageForShow($pid, $date) {
        //pass
    }

    /**
     * 根据时间获取演出信息
     * @return [type] [description]
     */
    public function getShowInfo() {
        $pid    = I('pid', '', 'intval');
        $aid    = I('aid', '', 'intval');
        $date   = I('date');

        if (!$pid || !$aid || !$date) {
            $this->apiReturn(200, [], '参数错误');
        }

        $soap = $this->getSoap();

        $productInfo = $this->_getShowProductInfo($pid, $aid, $soap);

        $rounds = $this->_getRoundsInfo($productInfo['venus_id'], $date);

        $rounds = $this->_fillStorage($rounds);

        if (count($rounds) < 1) {
            $this->apiReturn(200, [], '暂无演出场次信息');
        }

        $this->apiReturn(200, $rounds);

    }

    /**
     * 获取演出产品信息
     * @param  [type] $pid  产品id
     * @param  [type] $aid  上级供应商id
     * @param  [type] $soap SoapClient
     * @return [type]       [description]
     */
    private function _getShowProductInfo($pid, $aid, $soap) {
        include BASE_WWW_DIR . '/class/ProductInfo.php';

        if (!isset($GLOBALS['le'])) {
            include_once("/var/www/html/new/conf/le.je");
            $le = new \go_sql();
            $le->connect();
            $GLOBALS['le'] = $le;
        }

        $p = new \ProductInfo($s, $pid, $aid);

        return $p->pInfo();//返回产品信息
    }

    /**
     * 获取产次信息
     * @param  [type] $venus_id [description]
     * @param  [type] $date     [description]
     * @return [type]           [description]
     */
    private function _getRoundsInfo($venus_id, $date) {
        include BASE_WWW_DIR . '/class/abc/Product_H.class.php';
        include BASE_WWW_DIR . '/module/common/Db.class.php';

        $dbConf = include BASE_WWW_DIR . '/module/common/db.conf.php';

        \PFT\Db::Conf($dbConf['remote_1']);

        $p = new \abc777\Product_H(\PFT\Db::Connect());
        $rounds = $p->rounds($venus_id, $date, 1);

        $time = time(); $return = array();
        foreach($rounds as $key=>$row){

            if(strtotime($row['use_date'].' '.$row['et'])<$time) continue;

            $return[] = $row;
        }

        return $return;
    }

    /**
     * 填充库存数据
     */
    private function _fillStorage($rounds) {

        $storageModel = new \Model\Product\YXStorage();

        foreach($rounds as $key => $row){

            foreach($row['area_storage'] as $areaId => $areaStorage){
                
                if (isset($_SESSION['memberID'])) {
                    $memberID = $_SESSION['memberID'];
                } else {
                    $memberID = -1;
                }
                
                $sellerStorage = $storageModel->getResellerStorage($memberID, $row['id'], $areaId);

                if($sellerStorage === false) {
                    continue;
                }

                // 如果分销库存的数量超过的情况
                if($sellerStorage > $areaStorage) {
                    $sellerStorage = $areaStorage;
                }

                $row['area_storage'][$areaId] = $sellerStorage;
            }

            $rounds[$key] = $row;

        }

        return $rounds;

    }


}