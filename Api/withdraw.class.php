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

        $list = array(
            array('order_id' => 'pft3301', 'acc_no' => '6214255441122236','acc_name' => '王小明','acc_type' => '0','ins_name' => '华瑞支行','ins_code' => '332255885421','txn_amt' => '20'),
            array('order_id' => 'pft3302', 'acc_no' => '6214255441122236','acc_name' => '王小明','acc_type' => '0','ins_name' => '华瑞支行','ins_code' => '332255885421','txn_amt' => '30'),
            array('order_id' => 'pft3303', 'acc_no' => '6214255441122236','acc_name' => '王小明','acc_type' => '0','ins_name' => '华瑞支行','ins_code' => '332255885421','txn_amt' => '40')
        );

        $this->apiReturn(200, $list);
    }

    /**
     * 接受民生订单的反馈信息
     * @author dwer
     * @date   2016-04-14
     *
     * @return
     */
    public function feedback() {

    }

}
