<?php
/**
 * 提现相关模型
 *
 * @author dwer
 * @date 2016-01-20 
 * 
 */
namespace Model\Finance;
use Library\Model;

class Withdraws extends Model{

    private $_withdrawTable = 'pft_wd_cash';   

    /**
     *  获取需要自动提现的列表
     * @author dwer
     * @date   2016-04-14
     *
     * @param  $limit 条目数
     * @return
     */
    public function getAutoTransferList($limit) {
        //需要自动转账的银行体现
        $where = array(
            'push_status' => 1,
            'type'        => 1,
            'wd_status'   => 5
        );

        $order = 'apply_time asc';
        $page  = "1,{$limit}";
        $field = 'id, wd_money, bank_name, bank_ins_code, bank_accuont, wd_name, accType, fee_bank_once, cut_fee_way,service_charge';

        $list = $this->table($this->_withdrawTable)->where($where)->field($field)->order($order)->page($page)->select();

        return $list === false ? array() : $list;
    }

    /**
     *  获取提现记录
     * @author dwer
     * @date   2016-04-20
     *
     * @param  $orderId
     * @return
     */
    public function getWithdrawInfo($orderId) {
        if(!$orderId) {
            return false;
        }

        $where = ['id' => $orderId];
        $res = $this->table($this->_withdrawTable)->where($where)->find();

        return $res;
    }

    /**
     * 返回已经推送成功
     * @author dwer
     * @date   2016-04-20
     *
     * @param  $orderId 提现表ID
     * @return
     */
    public function feedbackPushed($orderId) {
        $info = $this->getWithdrawInfo($orderId);
        if(!$info) {
            return false;
        }

        $where = ['id' => $orderId];
        $data = ['push_status' => 2];

        $res = $this->table($this->_withdrawTable)->where($where)->save($data);

        if($res !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 反馈代付成功
     * @author dwer
     * @date   2016-04-20
     *
     * @param  $orderId 提现表ID
     * @param  $queryId 民生银行流水ID
     * @return
     */
    public function feedbackSuccess($orderId, $queryId) {
        if(!$orderId || !$queryId) {
            return false;
        }

        //更新代付数据
        $data = [
            'push_status' => 3,
            'wd_status'   => 2,
            'memo'        => "民生银行代付成功，流水号【{$queryId}】",
            'batchno'     => $queryId,
            'wd_time'     => date('Y-m-d H:i:s')
        ];

        $res = $this->table($this->_withdrawTable)->where(['id' => $orderId])->save($data);
        return $res === false ? false : true;
    }

    /**
     * 反馈代付失败
     * @author dwer
     * @date   2016-04-20
     *
     * @param  $orderId 提现表ID
     * @param  $errMsg 代付的错误信息
     * @return
     */
    public function feedbackFail($orderId, $errMsg) {
        if(!$orderId || !$errMsg) {
            return false;
        }

        //更新代付数据
        $data = [
            'push_status' => 3,
            'wd_status'   => 1,
            'memo'        => $errMsg,
            'wd_time'     => date('Y-m-d H:i:s')
        ];

        $res = $this->table($this->_withdrawTable)->where(['id' => $orderId])->save($data);
        return $res === false ? false : true;
    }

    /**
     * 添加提现记录
     * @author dwer
     * @date   2016-06-16
     *
     * @param $fid 需要提现的账号
     * @param $wdMoney 需要提现的金额 - 分为单位
     * @param $serviceFee 提现手续费率(千分之几)
     * @param $feeCutWay 提现金额从哪里扣除  0=提现金额扣除 1=账户余额扣除
     * @param $accountType 账号类型：1=银行，2=支付宝
     * @param $accountInfo 账号信息数组  {"bank_name":"","bank_ins_code":"","bank_account":"","acc_type":"","account_name":""}
     * @param $isAuto 是否直接自动清分 
     * 
     */
    public function add($fid, $wdMoney, $serviceFee, $feeCutWay, $accountType, $accountInfo, $isAuto = false) {

        $memo = <<<MEMO
申请提现金额:{$withdraw_deposit_in_yuan}元,手续费:{$service_charge_in_yuan}元,{$cut_fee_from},实际提现金额:{$transfer_money_in_yuan}元
MEMO;

        $str = "insert pft_wd_cash set fid=$memberID,wd_name='$wd_name',wd_money=$withdraw_deposit_in_fen,apply_time=now(),bank_name='$bank_name',bank_ins_code='$bank_area_name',bank_accuont='$bank_accuont',batchno='{$batchno}',memo='{$memo}',fee_bank_once=$fee_bank_once,cut_fee_way=$charge_from_account,accType='$accType',type='$type',service_charge=$service_charge_in_fen";
    }

}