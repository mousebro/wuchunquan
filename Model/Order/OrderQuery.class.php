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
    const __ORDER_DETAIL_TABLE__    = 'uu_order_fx_details';
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
                $ret[$time_param] = [ ['egt', $timeParams[$start]], ['elt', $timeParams[$end] ] ];
            }
            elseif (isset($timeParams[$start])) {
                $ret[$time_param] = ['egt', $timeParams[$start]];
            }
            elseif (isset($timeParams[$end])) {
                $ret[$time_param] = ['eq', $timeParams[$start]];
            }
        }
        return $ret;
    }
    public function OrderDetail($order_num)
    {
        $data  =[];

        return $data;
    }

    /**
     * 根据景区名称查询id
     *
     * @param string $name 景区名称
     * @return mixed
     */
    public function getLidByName($name)
    {
        return $this->table('uu_land')->where("title LIKE '%$name%'")->getField('id', true);
    }

    public function _where($seller_id, $buyer_id, $lid=0, $tid=0, $timeParams=[],
                           $order_tel='', $order_name='', $remote_num='',
                           $pay_status=-1, $order_status=-1, $order_mode=-1,
                           $pay_mode=-1)
    {
        $where = array();
        if ($seller_id>0 && $buyer_id>0) {
            $where[] = '';
        }
        if ($lid>0) $where['lid'] = $lid;
        if ($tid>0) $where['tid'] = $tid;
        if (count($timeParams)) {
            $_time = $this->handlerTimeParams($timeParams);
            $where = array_merge($where, $_time);
        }
        if (is_numeric($order_tel) && strlen($order_tel)==11) {
            $where['s.ordertel'] = $order_tel;
        }
        if (!empty($order_name)) {
            $where['s.ordername'] = $order_name;
        }
        if (!empty($remote_num) && ctype_alnum($remote_num)) {
            $where['s.remotenum'] = $remote_num;
        }
        if (is_numeric($pay_status) && $pay_status>-1) {
            $where['fd.pay_status'] =  $pay_status;
        }
        elseif (is_array($pay_status)) {
            $where['fd.pay_status'] = ['in', $pay_status];
        }
        if (is_numeric($order_status)  && $order_status>-1) {
            $where['s.status'] =  $order_status;
        }
        elseif (is_array($order_status) ) {
            $where['s.status'] = ['in', $order_status];
        }
        if (is_numeric($order_mode)  && $order_mode>-1) {
            $where['s.ordermode'] =  $order_mode;
        }
        elseif (is_array($order_mode)) {
            $where['s.ordermode'] = ['in', $order_mode];
        }
        if (is_numeric($pay_mode)  && $pay_mode>-1) {
            $where['s.paymode'] =  $pay_mode;
        }
        elseif (is_array($order_mode)) {
            $where['s.paymode'] = ['in', $pay_mode];
        }
        return $where;
    }

    /**
     * 订单查询
     *
     * @param int $offset
     * @param int $length
     * @param int $seller_id
     * @param int $buyer_id
     * @param string $order_num
     * @param array $timeParams
     * @param string $order_tel
     * @param string $order_name
     * @param string $remote_num
     * @param int $pay_status
     * @param int $order_status
     * @param int $order_mode
     * @param int $pay_mode
     * @param int $lid
     * @param int $tid
     * @return array
     */
    public function OrderList($offset, $length, $seller_id, $buyer_id, $lid=0, $tid=0, $order_num='',
                              $timeParams=[], $order_tel='', $order_name='', $remote_num='',
                              $pay_status=-1, $order_status=-1, $order_mode=-1, $pay_mode=-1
    )
    {
        if (is_numeric($order_num)) {
            return $this->OrderDetail($order_num);
        }
        $where = $this->_where($seller_id, $buyer_id, $lid, $tid, $timeParams,
            $order_tel, $order_name, $remote_num,
            $pay_status, $order_status, $order_mode,
            $pay_mode);

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
        $data = $this->table(self::__ORDER_TABLE__ . ' s')
            ->join('LEFT JOIN ' . self::__ORDER_DETAIL_TABLE__ .' fd ON s.ordernum=fd.orderid')
            ->join('LEFT JOIN ' . self::__ORDER_ADDON__ .' n ON s.ordernum=n.orderid')
            ->join('LEFT JOIN ' . self::__ORDER_APPLY_INFO__ . ' a ON s.ordernum=a.orderid')
            ->join('LEFT JOIN '. self::__ORDER_SPLIT__ . ' os ON s.ordernum=os.orderid' )
            ->field($fields)
            ->where($where)
            ->limit($offset, $length)
            ->select();
        //echo $this->getLastSql();
        return $data;
    }

}