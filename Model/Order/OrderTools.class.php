<?php
/**
 * 订单工具(接口)模型
 */

namespace Model\Order;
use Library\Model;

class OrderTools extends Model {

    /**
     * 获取订单信息
     * @param  int  	$orderid  订单id
     * @return array    [description]
     * @author  wengbin
     */
    public function getOrderInfo($orderid) {
        return $this->table('uu_ss_order')->where(array('ordernum' => $orderid))->find();
    }

    /**
     * 获取订单的额外信息
     * @param  int $orderid 订单id
     * @return array
     * @author  wengbin
     */
    public function getOrderAddonInfo($orderid) {
        return $this->table('uu_order_addon')->where(array('orderid' => $orderid))->find();
    }

    /**
     * 获取超过支付时限而未支付的订单
     * @param  int 	   $limit  条数
     * @param  string  $order  排序
     * @return array   $result 结果集
     * @author  wengbin
     */
    public function getOutOfDateOrders($limit = 10, $order = 'uu_ss_order.id asc') {
        $result = $this->table('uu_ss_order')->join("
				left join uu_order_fx_details detail on uu_ss_order.ordernum=detail.orderid
				left join uu_land land on uu_ss_order.lid=land.id")
            ->where(array(
                'uu_ss_order.status' => 0,
                'detail.pay_status' => 2,
                'land.id' => array('neq', 5322),
                'land.terminal_type' => array('neq', 0),))
            ->field('uu_ss_order.*,detail.*')
            ->order($order)
            ->limit($limit)
            ->select();
        return $result;
    }


    /**
     * 取消超时未支付的订单
     * @param  [type] $orderid      [description]
     * @param  Soap   $soap_cli     [description]
     * @return [type]               [description]
     * @author  wengbin
     */
    public function cancelOutOfDateOrder($orderid, \SoapClient $soap_cli) {
        $res = $soap_cli->Order_Change_Pro($orderid, 0, -1, 1, 1);
        if ($res != 100) return $res;

        $remote_con = new Model('remote_1');
        $seat = $remote_con->table('pft_roundseat_dyn')
            ->where(array('ordernum' => $orderid, 'status' => 2))
            ->field('id')
            ->select();
        if ($seat) {
            $seat_ids = '';
            foreach ($seat as $item) {
                $seat_ids .= $item['id'] . ',';
            }
            $seat_ids = rtrim($seat_ids, ',');
            //如果是场馆订单，则需要执行释放座位的动作
            $this->_releaseSeat($seat_ids, $remote_con);
            //todo:log it
        }

        $this->_cancelNotify($orderid);	//释放订单通知(todo://钩子系统)
        return $res;
    }

    /**
     * 释放锁定的座位
     * @param  string 		$seat_id  pft_roundseat_dyn表id集合
     * @param  object   $model对象   [description]
     * @return mixed
     * @author  wengbin
     */
    private function _releaseSeat($seat_ids, $remote_con) {
        $where['id'] = array('in', $seat_ids);
        $update = array(
            'status' => 4,
            'ordernum' => 0,
        );
        return $remote_con->table('pft_roundseat_dyn')->where($where)->save($update);
    }

    /**
     * 取消订单通知
     * @param  int $ordernum 订单号
     * @return [type]           [description]
     * @author  wengbin
     */
    private function _cancelNotify($orderid) {

        //todo,,

    }
}
