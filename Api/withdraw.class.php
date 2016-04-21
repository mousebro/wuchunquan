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
        $orderId  = $this->getParam('order_id');
        $status   = $this->getParam('status');
        $queryId  = $this->getParam('query_id');
        $errorMsg = $this->getParam('error_msg');

        $orderId   = strval($orderId);
        $statusArr = array(2, 3, 4);
        if(!$orderId || !in_array($status, $statusArr)) {
            $this->apiReturn(400, [], '参数错误');
        }

        $platformOrderId = $this->_handleOrderId($orderId, 2);
        if(!$platformOrderId) {
            $this->apiReturn(400, [], '参数错误');
        }

        //看是不是存在记录
        $withdrawModel = $this->model('Finance/Withdraw');
        $info = $withdrawModel->getWithdrawInfo($platformOrderId);
        if(!$info) {
            $this->apiReturn(400, [], '参数错误 - 订单ID错误');
        }
        
        if($status == 2) {
            //通知已经推送成功
            $res = $withdrawModel->feedbackPushed($platformOrderId);

        } else if($status == 3) {
            //支付失败
            $res = $withdrawModel->feedbackFail($platformOrderId, $errMsg);

            $errorMsg = strval($errorMsg);
            if(!$errorMsg) {
                $this->apiReturn(400, [], '参数错误 - 错误信息缺失');
            }

        } else if($status == 4) {
            //支付成功
            $queryId = strval($queryId);
            if(!$queryId) {
                $this->apiReturn(400, [], '参数错误 - 流水ID缺失');
            }

            $res = $withdrawModel->feedbackSuccess($platformOrderId, $queryId);
        }

        if($res) {
            $this->apiReturn(200, [], '反馈成功');
        } else {
            $this->apiReturn(500, [], '反馈失败');
        }
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
