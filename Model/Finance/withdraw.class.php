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

class Withdraw extends Model{

    private $this->_withdrawTable = 'pft_wd_cash';

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
        $field = 'id, wd_money, bank_name, bank_ins_code, bank_accuont, wd_name, accType';

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
            'wd_status'   => 0,
            'memo'        => $errMsg
        ];

        $res = $this->table($this->_withdrawTable)->where(['id' => $orderId])->save($data);
        return $res === false ? false : true;
    }

}