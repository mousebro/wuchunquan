<?php
/**
 * 订单工具模型
 */

namespace Model\Order;
use Library\Model;

class OrderTools extends Model {

	
	/**
	 * 获取订单信息
	 * @param  int  	$orderid  订单id
	 * @return array    [description]
	 */
	public function getOrderInfo($orderid) {
		return $this->table('uu_ss_order')->where(array('ordernum' => $orderid))->find();
	}

	/**
	 * 获取订单的额外信息
	 * @param  int $orderid 订单id
	 * @return array 
	 */
	public function getOrderAddonInfo($orderid) {
		return $this->table('uu_order_addon')->where(array('orderid' => $orderid))->find();
	}


	/**
	 * 获取超过支付时限而未支付的订单
	 * @param  int 	   $limit  条数
	 * @param  string  $order  排序
	 * @return array   $result 结果集
	 */
	public function getOutOfDateOrders($limit = 10, $order = 'uu_ss_order.id asc') {
		$result = $this->table('uu_ss_order')->join("
				left join uu_order_fx_details detail on uu_ss_order.ordernum=detail.orderid 
				left join uu_land land on uu_ss_order.lid=land.id")
			->where(array(
				'uu_ss_order.status' => 0,
				'detail.pay_status' => 2, 
				'land.terminal_type' => array('neq', 0)))
			->field('uu_ss_order.*,detail.*')
			->order($order)
			->limit($limit)
			->select();
		return $result;
	}
}
