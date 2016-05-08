<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/5/8
 * Time: 19:49
 * 订单查询模型
 */

namespace Model\Order;


use Library\Model;

class OrderQuery extends Model
{
    private $orderFields = [

    ];
    const __ORDER_TABLE__           = 'uu_ss_order';
    const __ORDER_DETAIL_TABLE__    = 'uu_order_fx_detail';
    const __ORDER_SPLIT__           = 'order_aids_split';
    const __ORDER_ADDON__           = 'uu_order_addon';
    const __ORDER_APPLY_INFO__      = 'uu_order_apply_info';

    public function __construct()
    {
        parent::__construct('slave');
    }
    private function handlerTimeParams($timeParams)
    {
        $limit_time = ['ordertime','dtime','begintime','endtime'];
        $ret = [];
        foreach ($limit_time as $time_param)
        {
            $start = $time_param.'Start';
            $end   = $time_param.'End';
            if ( isset($timeParams[$start]) && isset( $timeParams[$end]) ) {
                $ret[$time_param] = [ ['gte', $timeParams[$start]], ['lte', $timeParams[$end] ] ];
            }
            elseif (isset($timeParams[$start])) {
                $ret[$time_param] = ['gte', $timeParams[$start]];
            }
            elseif (isset($timeParams[$end])) {
                $ret[$time_param] = ['eq', $timeParams[$start]];
            }
        }
        return $ret;
    }
    public function OrderList($offset, $length, $seller_id, $buyer_id, $order_num='', $timeParams=[],
                              $order_tel='', $order_name='', $remote_num='',
                              $pay_status=-1, $order_status=-1, $order_mode=-1,
                              $pay_mode=-1, $product_name=''
    )
    {
        $where = array();
        if (count($timeParams)) {

        }
        $fields = [
            's.ordernum', 's.remotenum', 's.lid', 's.tid',
            's.begintime', 's.ordertime', 's.endtime', 's.tnum', 's.tprice',
            's.ordername', 's.ordertel', 's.status', 's.salerid',
            's.dtime', 's.totalmoney', 's.paymode AS pmode', 's.ordermode',
            's.ctime', 's.code', 's.contacttel', 's.member','s.aid',
            'n.ifpack', 'n.pack_order', 'n.tordernum', 's.aid', 'a.aprice',
            'a.lprice', 'fd.concat_id', 'fd.aids', 'fd.aids_price','fd.aids_money',
            'os.buyerid AS buyid', 'os.sellerid AS sellid',
        ];
        $this->table(self::__ORDER_TABLE__ . ' s')
            ->join('LEFT JOIN ' . self::__ORDER_DETAIL_TABLE__ .' fd')
            ->join('LEFT JOIN ' . self::__ORDER_APPLY_INFO__ . ' a')
            ->join('LEFT JOIN '. self::__ORDER_SPLIT__ . ' os' )
            ->field($fields)
            ->where($where)
            ->limit($offset, $length)
            ->select();
    }

}