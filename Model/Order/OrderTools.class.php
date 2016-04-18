<?php
/**
 * 订单工具(接口)模型
 */

namespace Model\Order;
use Library\Model;
use Model\Product\YXStorage;
use Model\Order\OrderTrack;

class OrderTools extends Model {

    const __ORDER_TABLE__  = 'uu_ss_order';

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
     * 获取订单的分销详情
     * @param $orderID
     *
     * @return mixed
     * @author fangli
     */
    public function getOrderDetail($orderID){

        return $this->table('uu_order_fx_details')->where(['orderid'=>$orderID])->find();
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
     * @param  [type] $orderid      订单号
     * @param  Soap   $soap_cli     soap接口实例
     * @param  int    $tid          门票id
     * @param  int    $tum          门票数
     * @param  int    $source       来源,详情见OrderTrack
     * @return [type]               [description]
     * @author  wengbin
     */
    public function cancelOutOfDateOrder($orderid, \SoapClient $soap_cli, $tid, $tnum, $source) {
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
        }

        $this->_cancelNotify($orderid);	//释放订单通知(todo://钩子系统)
        $this->_cancelRecord($orderid, $tid, $tnum, $source);
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

    /**
     * 订单追踪（取消）
     * @return [type] [description]
     */
    private function _cancelRecord($orderid, $tid, $tnum, $source) {
        $data = [
            'ordernum'       => $orderid,
            'action'         => 2,
            'tid'            => $tid,
            'tnum'           => $tnum,
            'left_num'       => 0,
            'source'         => 19,
            'terminal'       => 0,
            'branchTerminal' => 0,
            'id_card'        => 0,
            'SalerID'        => 0,
            'insertTime'     => date('Y-m-d H:i:s'),
            'oper_member'    => 0,
        ];
        $track = new OrderTrack();
        return $track->addTrack($orderid, 2, $tid, $tnum, 0, 19);
    }

    /**
     * 取消订单的时候，释放分销商库存
     * @author dwer
     * @date   2016-03-24
     *
     * @param  $orderNum 订单号
     * @return bool
     */
    private function _recoverStorage($orderNum) {
        $storageModel = new YXStorage();
        $res = $storageModel ->recoverStorage($orderNum);

        if($res) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取套票主票的子票信息
     * @param $orderNum
     *
     * @return mixed
     */
    public function getPackSubOrder($orderNum){
        $table = 'uu_order_addon';
        $where = ['pack_order' => $orderNum,];
        $field = ['orderid'];
        $result =  $this->table($table)->where($where)->field($field)->select();
//        $this->test();
        return $result;
    }

    /**
     * 获取联票所有子票订单号
     * @param $orderNum
     *
     * @return mixed
     */
    public function getLinkSubOrder($orderNum){
        $table = 'uu_order_fx_details';
        $where = array(
            'concat_id' => $orderNum,
        );
        $field = ['orderid'];
        $result = $this->table($table)->where($where)->field($field)->select();
        return $result;
    }

    /**
     * 打印测试语句
     */
    private function test(){
        $str = $this->getLastSql();
        var_dump($str);
    }
    /**
     * 获取某个会员所购买的订单信息
     * @param  int      $memberid 会员id
     * @param  array    $option   额外的查询条件
     * @return array
     */
    public function getSomeOneBoughtOrders($memberid, $options = array()) {

        return $this->table(self::__ORDER_TABLE__)->where(['member' => $memberid])->select($options);
    }

    /**
     * 更新套票状态
     * @author Guangpeng Cheng
     * @date 2016-04-16
     *
     * @param string $begin_time 开始时间
     * @param string $end_time 截止时间
     */
    public function syncPackageOrderStatus($begin_time, $end_time)
    {
        $dbConf = C('db');
        $this->db(11,$dbConf['terminal'], true);
        //获取子票
        $where = [
            'updateTime'=>
                [
                    array('egt',$begin_time),
                    array('elt',$end_time)
                ],
        ];
        $ordernums = $this->db(11)->table('order_print')
            ->where($where)->getField('orderNUM', true);
        echo $this->db(11)->getLastSql();
        if ($ordernums)
        {
            $ordernum_str = "'". implode("','", $ordernums) . "'";
            $where = "uu_ss_order.ordernum IN($ordernum_str) and uu_ss_order.status=0 and uu_order_addon.ifpack=2";
            $unchanged_orders = $this->db(0)->table('uu_ss_order')
                ->join('LEFT JOIN  uu_order_addon ON  uu_ss_order.ordernum=uu_order_addon.orderid')
                ->where($where)
                ->getField('uu_ss_order.ordernum', true);
            echo $this->db(0)->getLastSql();
                        
            if ($unchanged_orders) {
                $unchanged_orders_str = "'".implode("','", $unchanged_orders) ."'";
                $sql = <<<SQL
SELECT MAX(s.dtime) as dtime,pack_order FROM uu_ss_order s LEFT JOIN uu_order_addon a ON a.orderid=s.ordernum
WHERE a.pack_order IN($unchanged_orders_str)
GROUP BY pack_order
SQL;
                $ret = $this->db(0)->query($sql);
                $sql = "UPDATE uu_ss_order SET status=1,dtime= CASE ordernum WHERE";
                $main_ordernums = [];
                foreach ($ret as $item) {
                    $main_ordernums[] = "'".$item['pack_order']."'";
                    $sql .=  sprintf("WHEN '%s' THEN '%s' ", $item['pack_order'], $item['dtime']);
                }
                $main_ordernum_str = implode(',', $main_ordernums);
                $sql .= "END WHERE ordernum IN ($main_ordernum_str)";
                $this->db(0)->execute($sql);
            }
        }

    }
}
