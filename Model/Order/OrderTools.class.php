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
				'land.terminal_type' => array('neq', 0)))
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
	public function cancelOutOfDateOrder($orderid, \SoapClient $soap_cli, $connection) {
		$res = $soap_cli->Order_Change_Pro($item['ordernum'], 0, -1, 1, 1);
		if ($res == 100) {//取消成功
			$remote_con = new Model('', '', $connection['remote_1']);
			$seat = $remote_con->table('pft_roundseat_dyn')
						->where(array('ordernum' => $orderid, 'status' => 2))	
						->find();
			// var_dump($seat);
			if ($seat) {
				//如果是场馆订单，则需要执行释放座位的动作
				$this->releaseSeat($seat['id']);
				//todo:log it
			}

			$this->cancelNotify();	//释放订单通知(todo://钩子系统)
		}
		return $res;
	}

	/**
	 * 释放锁定的座位
	 * @param  int 		$seat_id  pft_roundseat_dyn表id
	 * @param  object   $model对象   [description]
	 * @return mixed
	 * @author  wengbin
	 */
	private function releaseSeat($seat_id, $remote_con) {
		$update = array(
			'id' => $seat_id,
			'status' => 4,
			'ordernum' => 0,
		);
		return $remote_con->update($update);
	}

	/**
	 * 取消订单通知
	 * @param  int $ordernum 订单号
	 * @return [type]           [description]
	 * @author  wengbin
	 */
	private function cancelNotify($ordernum) {

		//todo
		
	}
}
