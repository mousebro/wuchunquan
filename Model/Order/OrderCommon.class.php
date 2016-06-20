<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/18-018
 * Time: 11:04
 */

namespace Model\Order;


use Library\Model;

class OrderCommon extends Model
{
    /**
     * 订单销售记录
     * @author Guangpeng Chen
     *
     * @param array $orders 订单号
     * @param int $sale_price 实际销售价-总价
     * @param int $sale_op 销售账号ID
     * @param int $op_id 操作员ID
     * @param int $ad_flag 广告费冲抵标识0非冲抵1冲抵
     * @param int $sale_type 销售方式:0采购1销售
     * @return int|bool
     */
    public function OrderSaleLog(Array $orders, $sale_op, $op_id, $ad_flag=0, $sale_type=0)
    {
        $tm = time();
        $data = [];
        foreach ($orders as $items) {
            foreach($items as $ordernum=>$sale_price) {
                $data[] = [
                    'ordernum' => $ordernum,
                    'sale_price' => $sale_price,
                    'sale_op' => $sale_op,
                    'op_id' => $op_id,
                    'ad_flag' => $ad_flag,
                    'sale_type' => $sale_type,
                    'created_time' => $tm,
                ];
            }
        }
        $r = $this->table('pft_ordercustomer')->addAll($data);
        return $r;
    }
}