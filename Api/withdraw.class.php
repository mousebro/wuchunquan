<?php
namespace Api;

if(!defined('PFT_API')) {exit('Access Deny');}

/**
 * 现金提现提供给民生专线的接口
 *
 * @author dwer
 * @date 2016-04-13
 */

use Library\Controller;
use Model\Finance\Withdraw as Withdraw;

class withdraw extends Controller{
    /**
     * 获取需要进行提现的列表
     * @author dwer
     * @date   2016-04-14
     *
     * @return
     */
    public function getList() {
        $limit = intval($this->getParam('limit'));
        $limit = $limit < 1 ? 100 : ($limit > 100 ? 100 : $limit);

        $withdrawModel = $this->model('Finance/Withdraw');
        $list = $withdrawModel->getAutoTransferList($limit);

        $res = array();
        foreach($list as $item) {
            $tmp = array(
                'order_id' => $this->_handleOrderId($item['id']),
                'acc_no'   => $item['bank_accuont'],
                'acc_name' => $item['wd_name'],
                'acc_type' => $item['accType'],
                'ins_name' => $item['bank_name'],
                'ins_code' => $item['bank_ins_code'],
                'txn_amt'  => $item['wd_money'],
            );

            $res[] = $tmp;
        }

        $this->apiReturn(200, $res);
    }

    /**
     * 接受民生订单的反馈信息
     * @author dwer
     * @date   2016-04-14
     *
     * @return
     */
    public function feedback() {
        $orderId = $this->getParam('order_id');
        $status  = $this->getParam('status');
        $queryId = $this->getParam('query_id');

        $platformOrderId = $this->_handleOrderId($orderId);
        

        pft_log('withdraw_error/api', json_encode($_POST));



        //通知提现的数据已经收到



        //通知外付的结构

        $this->apiReturn(200, []);
    }

    /**
     * 因为提现ID太短了，这边统一做处理
     * @author dwer
     * @date   2016-04-20
     *
     * @param  $orderId 订单ID
     * @param  $type 操作：1=平台订单处理成民生订单，2=民生订单处理成平台订单
     * @return
     */
    private function _handleOrderId($orderId, $type = 1) {
        $orderId = strval($orderId);
        if(!$orderId) {
            return false;
        }

        $prefix    = 'pft' . @date('Ym');
        $prefixLen =  strlen($prefix);

        if($type == 1) {
            return $prefix . $orderId;
        } else {
            return substr($orderId, $prefixLen);
        }
    }

}
