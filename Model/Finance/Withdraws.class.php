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
use Library\Tools\Helpers as Helpers;

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

        //获取一下之前的备注信息
        $res = $this->table($this->_withdrawTable)->where(['id' => $orderId])->find();
        $memo = $res ? ' - ' . $res['memo'] : '';

        //更新代付数据
        $data = [
            'push_status' => 3,
            'wd_status'   => 2,
            'memo'        => "民生银行代付成功，流水号【{$queryId}】" . $memo,
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

        //获取一下之前的备注信息
        $res = $this->table($this->_withdrawTable)->where(['id' => $orderId])->find();
        $memo = $res ? ' - ' . $res['memo'] : '';

        //更新代付数据
        $data = [
            'push_status' => 3,
            'wd_status'   => 1,
            'memo'        => $errMsg . $memo,
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
    public function addRecord($fid, $wdMoney, $serviceFee, $feeCutWay, $accountType, $accountInfo, $isAuto = false) {
        $wdMoney    = intval($wdMoney);
        $serviceFee = intval($serviceFee);
        $cutwayArr  = ['0' => '提现金额扣除', '1' => '账户余额扣除'];

        if(!$fid || $wdMoney <= 0 || !in_array($feeCutWay, [0, 1]) || !in_array($accountType, [1, 2]) || !is_array($accountInfo)) {
            return false;
        }

        //手续费不足一元按一元计算
        $serviceCharge = intval($wdMoney * ($serviceFee / 1000));
        $serviceCharge = $serviceCharge < 100 ? 100 : $serviceCharge;

        $wdMoneyV       = round($wdMoney / 100, 2);
        $serviceChargeV = round($serviceCharge / 100, 2);
        $transMoneyV    = $feeCutWay == 1 ? $wdMoneyV : round(($wdMoney - $serviceCharge) / 100, 2);
        $cutwayV        = $cutwayArr[$feeCutWay];

        $memo = "申请提现金额:{$wdMoneyV}元,手续费:{$serviceChargeV}元,{$cutwayV},实际提现金额:{$transMoneyV}元";
        if($isAuto) {
            //直接自动提现
            $memo = '自动清分 - ' . $memo;
        }

        $data = [
            'fid'            => $fid,
            'wd_name'        => $accountInfo['account_name'],
            'wd_money'       => $wdMoney,
            'bank_name'      => $accountInfo['bank_name'],
            'bank_ins_code'  => $accountInfo['bank_ins_code'],
            'bank_accuont'   => $accountInfo['bank_account'],
            'accType'        => $accountInfo['acc_type'],
            'type'           => $accountType,
            'cut_fee_way'    => $feeCutWay,
            'fee_bank_once'  => $serviceFee,
            'service_charge' => $serviceCharge,
            'memo'           => $memo,
            'apply_time'     => date('Y-m-d H:i:s')
        ];

        if($isAuto) {
            $data['wd_operator']   = '后台系统|ID:1';
            if(defined('ENV') && ENV == 'PRODUCTION') {
                //生产环境需要实际打款
                $data['wd_status']   = 5;
                $data['push_status'] = 1;
            } else {
                $batchno             = 'cmbc_' . time();
                $data['batchno']     = $batchno;
                $data['memo']        = "民生银行代付成功，流水号【{$batchno}】" . ' - ' . $memo;
                $data['wd_time']     = date('Y-m-d H:i:s');
                $data['wd_status']   = 2;
                $data['push_status'] = 3;
            }
        }

        $this->startTrans();
        $res = $this->table($this->_withdrawTable)->add($data);

        if(!$res) {
            $this->rollback();
            return false;
        }

        //通过接口调用交易流水扣除
        $soapInside = Helpers::GetSoapInside();
        $frozenMoney = $feeCutWay == 1 ? $wdMoney + $serviceCharge : $wdMoney;
        $res = $soapInside->PFT_Member_Fund_Modify($fid, $fid, $frozenMoney, 1, 0, $fid, 6, null, '', $memo);
        if($res == 100) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

}