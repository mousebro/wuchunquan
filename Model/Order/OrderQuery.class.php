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

    const GET_TOTAL_ROWS            = true;

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
    public function getLandInfoById($id_list) {
        $where = ['id'=>['in', $id_list]];
        $data = $this->table('uu_land')->where($where)->getField('id,title,p_type', true);
        return $data;
    }
    public function getTicketsInfoById($id_list) {
        $where = ['id'=>['in', $id_list]];
        $data = $this->table('uu_jq_ticket')->where($where)->getField('id,pid,title', true);
        return $data;
    }
    public function getMemberInfoById($id_list) {
        $where = ['id'=>['in', $id_list]];
        $data = $this->table('pft_member')->where($where)->getField('id,dname', true);
        return $data;
    }
    public function sql_ids_M_A($amid,$maid){
        if(!$amid) return $this->err3;
        //array(array('gt',3),array('lt',10), 'or') ;
        if($maid) return " and ((os.sellerid=$amid and os.buyerid=$maid) or (os.sellerid=$maid and os.buyerid=$amid))";
        return " and (os.sellerid=$amid or os.buyerid=$amid) ";
    }

    /**
     * @param int $seller_id 供应商ID
     * @param int $buyer_id 分销商ID
     * @param int $lid
     * @param int $tid
     * @param array $timeParams
     * @param string $order_tel
     * @param string $order_name
     * @param string $remote_num
     * @param int $pay_status
     * @param int $order_status
     * @param int $order_mode
     * @param int $pay_mode
     * @return array
     */
    public function _where($seller_id, $buyer_id, $lid=0, $tid=0, $timeParams=[],
                           $order_tel='', $order_name='', $remote_num='',
                           $pay_status=-1, $order_status=-1, $order_mode=-1,
                           $pay_mode=-1)
    {
        $where = array();
        if ($seller_id>0 && $buyer_id>0) {
            $where['_string'] = "(os.sellerid=$buyer_id and os.buyerid=$seller_id) OR (os.sellerid=$seller_id and os.buyerid=$buyer_id)";
        }
        elseif($buyer_id>0) {
            $where['_string'] = "os.sellerid=$buyer_id OR os.buyerid=$buyer_id";
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

    public function TotalCount($where)
    {
        $total = $this->table(self::__ORDER_TABLE__ . ' s')
            ->join('LEFT JOIN ' . self::__ORDER_DETAIL_TABLE__ .' fd ON s.ordernum=fd.orderid')
            ->join('LEFT JOIN '. self::__ORDER_SPLIT__ . ' os ON s.ordernum=os.orderid' )
            ->where($where)
            ->getField('COUNT(*) AS cnt');
        return $total;
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
     * @param int $lid 景区ID
     * @param int $tid 门票ID
     * @param bool $total
     * @return array
     */
    public function OrderList($offset, $length, $seller_id, $buyer_id, $lid=0, $tid=0, $order_num='',
                              $timeParams=[], $order_tel='', $order_name='', $remote_num='',
                              $pay_status=-1, $order_status=-1, $order_mode=-1, $pay_mode=-1, $total=false
    )
    {
        if (is_numeric($order_num)) {
            return $this->OrderDetail($order_num);
        }
        $where = $this->_where($seller_id, $buyer_id, $lid, $tid, $timeParams,
            $order_tel, $order_name, $remote_num,
            $pay_status, $order_status, $order_mode,
            $pay_mode);

        //获取总条数
        if ($total) {
            $total = $this->TotalCount($where);
            return $total;
        }
        $fields = [
            's.member',
            's.ordernum', 's.remotenum', 's.lid', 's.tid',
            's.begintime', 's.ordertime', 's.endtime', 's.tnum', 's.tprice',
            's.ordername', 's.ordertel', 's.status', 's.salerid',
            's.dtime', 's.totalmoney', 's.paymode AS pmode', 's.ordermode',
            's.ctime', 's.code', 's.contacttel', 's.member','s.aid',
            'n.ifpack', 'n.pack_order', 'n.tordernum', 's.aid', 'a.aprice',
            'a.lprice', 'a.playtime','fd.pay_status','fd.concat_id', 'fd.aids', 'fd.aids_price','fd.aids_money',
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
        //echo $this->getLastSql(),"\n";
        $output = array();
        $lid_list = $tid_list = array();
        $member_list = array();
        foreach ($data as $item) {
            $lid_list[] = $item['lid'];
            $member_list[] = $item['member'];
            $member_list[] = $item['aid'];
            $tid_list[] = $item['tid'];
            if ($item['aids']) {
                foreach (explode(',', $item['aids']) as $_aid) $member_list[] = $_aid;
            }
            $output[$item['ordernum']]['main'] = $item;
            //处理联票
            if ($item['concat_id']!='' && $item['concat_id']!=$item['ordernum']) {
                $output[$item['concat_id']]['links'][] = $item;
            }
        }
        $lid_list       = array_unique($lid_list);
        $tid_list       = array_unique($tid_list);
        $member_list    = array_unique($member_list);

        $lands   = $this->getLandInfoById($lid_list);
        $tickets = $this->getTicketsInfoById($tid_list);
        $members = $this->getMemberInfoById($member_list);
        //echo $this->getLastSql();
        return ['orders'=>$output, 'lands'=>$lands,'tickets'=>$tickets, 'members'=>$members];
    }

    public function OrderSplitDiff($time_type,$startDate, $endDate,$lid=0, $tid=0 )
    {
        if (!$lid && !$tid) throw_exception('不能所有ID都为空');
        $where = [];
        switch ($time_type) {
            case 1:
                $col = 's.ordertime';
                break;
            case 2:
                $col = 's.dtime';
                break;
            default:
                $col = 's.dtime';
                break;
        }
        $where[$col] = ['between', [$startDate, $endDate]];
        if ($lid) $where['s.lid'] = $lid;
        if ($tid) $where['s.tid'] = $tid;
        $where['s.status'] = 1;
        $apply_did = $this->table('uu_land')->where(['id'=>$lid])->getField('apply_did');
        $dataOrder = $this->_OrderSplitDiff($where);
        //echo count(array_unique($dataOrder)),"\n";
        $where['os.sellerid'] = (int)$apply_did;
        $dataOrderSplit = $this->_OrderSplitDiff($where);
        //echo count(array_unique($dataOrderSplit)),"\n";
        $diff = array_diff ( $dataOrder, $dataOrderSplit);
        //var_dump($diff);
        if (count($diff)) {
            return $diff;
        }
    }
    private function _OrderSplitDiff( Array $where )
    {
        $ret = $this->table(self::__ORDER_TABLE__ .' s')->join('LEFT JOIN '. self::__ORDER_SPLIT__ . ' os on os.orderid=s.ordernum')
            ->where($where)
            ->getField('s.id,s.ordernum', true);
        echo $this->getLastSql();
        return $ret;
    }
}