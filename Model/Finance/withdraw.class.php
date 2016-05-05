<?php
/**
 * 提现相关模型
 *
 * @author dwer
 * @date 2016-01-20
 * 
 */
namespace Model\Member;
use Library\Model;

class Reseller extends Model{

    private $withdrawTable = 'pft_wd_cash';

    /**
     *  获取需要自动提现的列表
     * @author dwer
     * @date   2016-04-14
     *
     * @param  $limit 条目数
     * @return
     */
    public function getAutoTransferList($limit) {
        $where = array(
            'push_status' => 1
        );

        $order = 'apply_time asc';
        $page  = "1,{$limit}";
        $field = '*';

        $list = $this->table($this->withdrawTable)->where($where)->field($field)->order($order)->page($page)->select();

        return $list;
    }

    
    public function feedbackStatus() {

    }

}