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

    private $this->withdrawTable = 'pft_wd_cash';

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

        $list = $this->table($this->withdrawTable)->where($where)->field($field)->order($order)->page($page)->select();

        return $list === false ? array() : $list;
    }

    
    public function feedbackStatus() {

    }

}