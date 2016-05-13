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
    private $typeArr = array(
        'withdraw' => 'PFT001', //提现的前缀
        'check'    => 'PFT002'  //转账的前缀
    );

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

        $withdrawModel = $this->model('Finance/Withdraws');
        $list = $withdrawModel->getAutoTransferList($limit);

        $res = array();
        foreach($list as $item) {
            $tmp = array(
                'order_id' => $this->_handleOrderId($item['id'], 'withdraw', 1),
                'acc_no'   => $item['bank_accuont'],
                'acc_name' => $item['wd_name'],
                'acc_type' => $item['accType'],
                'ins_name' => $item['bank_name'],
                'ins_code' => $item['bank_ins_code']
            );

            //计算实际需要转的金额
            $fee1     = $item['wd_money'] / 100 / (1+$item['fee_bank_once']/1000);
            $wm       = $item['cut_fee_way'] ? (($item['wd_money']/100-$fee1<1)?($item['wd_money']/100-1):$fee1) : ($item['wd_money']/100);
            $payMoney = $wm * $item['fee_bank_once'] / 1000;
            $pm       = ($payMoney < 1 && $payMoney > 0) ? 1 : $payMoney;

            //最后的金额转换成分为单位
            $txnAmt   = sprintf("%.2f", ($item['wd_money']/100 - $pm)) * 100;

            $tmp['txn_amt'] = $txnAmt;

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

        $platformOrderId = $this->_handleOrderId($orderId, 'withdraw', 2);
        if(!$platformOrderId) {
            $this->apiReturn(400, [], '参数错误');
        }

        //看是不是存在记录
        $withdrawModel = $this->model('Finance/Withdraws');
        $info = $withdrawModel->getWithdrawInfo($platformOrderId);
        if(!$info) {
            $this->apiReturn(400, [], '参数错误 - 订单ID错误');
        }
        
        if($status == 2) {
            //通知已经推送成功
            $res = $withdrawModel->feedbackPushed($platformOrderId);

        } else if($status == 3) {
            //支付失败
            $errorMsg = strval($errorMsg);
            if(!$errorMsg) {
                $this->apiReturn(400, [], '参数错误 - 错误信息缺失');
            }

            $res = $withdrawModel->feedbackFail($platformOrderId, $errorMsg);

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
     * @param  $type 类型 : 参照typeArr的定义
     * @param  $isRecover 操作：1=平台订单处理成民生订单，2=民生订单处理成平台订单
     * @return
     */
    private function _handleOrderId($orderId, $type = 'withdraw', $isRecover = 1) {
        $orderId = strval($orderId);
        if(!$orderId || !isset($this->typeArr[$type])) {
            return false;
        }

        $prefix    = $this->typeArr[$type] . date('Ym');
        $prefixLen =  strlen($prefix);

        if($type == 1) {
            $tmp = $prefix . $orderId;
            return $this->_cryptOrderId($tmp);
        } else {
            $tmp = $this->_cryptOrderId($orderId, true);
            return substr($tmp, $prefixLen);
        }
    }

    /**
     * 需要处理的字符串
     * @author dwer
     * @date   2016-05-12
     *
     * @param  $str
     * @param  $isRecover
     * @return string
     */
    private function _cryptOrderId($str, $isRecover = false) {
        if(!$str) {
            return false;
        }

        //映射数组
        $codeMap = array(1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd', 5 => 'e', 6 => 'f', 7 => 'g', 8 => 'z', 9 => 'i', 0 => 'm');
        if($isRecover) {
            $codeMap = array_flip($codeMap);
        }

        $orderTmp = str_split($str);
        $orderRes = array();

        //映射字符替换
        foreach($orderTmp as $key => $val) {
            if(array_key_exists($val, $codeMap)) {
                $orderRes[$key] = $codeMap[$val];
            } else {
                $orderRes[$key] = $val;
            }
        }

        //前后字符对调
        $flipNum = 3;
        $tmpStr  = implode('', $orderRes);
        $res     = substr($tmpStr, strlen($tmpStr) - $flipNum) . substr($tmpStr, $flipNum, strlen($tmpStr) - 2 * $flipNum) . substr($tmpStr, 0, $flipNum);

        return $res;
    }

}
