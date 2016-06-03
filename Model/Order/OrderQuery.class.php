<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/5/8
 * Time: 19:49
 * 订单查询模型
 */

namespace Model\Order;


use Library\Dict\OrderDict;
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
    const __ORDER_TRACK__           = 'pft_order_track';

    const GET_TOTAL_ROWS            = true;

    public function __construct()
    {
        if (ENV=='PRODUCTION') parent::__construct('slave');
        else parent::__construct('localhost');
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

    /**
     * 获取分批验证的票数
     *
     * @param array $ordernum_list 订单号
     * @return array
     */
    public function getCheckedTnum(Array $ordernum_list) {
        $where = ['ordernum'=>['in', $ordernum_list], 'action'=>5];
        $data  = $this->table('pft_order_track')->where($where)
            ->order('id desc')
            ->getField('tid,ordernum, tnum,left_num', true);
        if (!$data) return [];
        $ret = [];
        foreach ($data as $item) {
            $_k = $item['ordernum'];
            unset($item['ordernum']);
            $ret[$_k] = $item;
        }
        return $ret;
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
    public function _where($seller_id, $buyer_id, $order_num='', $lid=0, $tid=0, $timeParams=[],
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
        if (is_numeric($order_num)) {
            $where['ordernum'] = $order_num;
            return $where;
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

        $where = $this->_where($seller_id, $buyer_id, $order_num, $lid, $tid, $timeParams,
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
            ->order('ordertime desc')
            ->limit($offset, $length)
            ->select();
        //echo $this->getLastSql(),"\n";
        $output = array();
        $lid_list = $tid_list = $part_list = array();
        $member_list = array();
        foreach ($data as $key=>$item) {
            $lid_list[] = $item['lid'];
            $member_list[] = $item['member'];
            $member_list[] = $item['aid'];
            $tid_list[] = $item['tid'];
            //部分验证的订单
            if ($item['status']==7) {
                $part_list[] = "'{$item['ordernum']}'";
            }
            if ($item['aids']) {
                foreach (explode(',', $item['aids']) as $_aid) $member_list[] = $_aid;
            }
            $output[$item['ordernum']]['main'] = $item;
            //处理联票
            if ($item['concat_id']!='' && $item['concat_id']!=$item['ordernum']) {
                unset($output[$item['ordernum']]);//删除联票数据
                $output[$item['concat_id']]['links'][] = $item;
            }

        }
        $lid_list       = array_unique($lid_list);
        $tid_list       = array_unique($tid_list);
        $member_list    = array_unique($member_list);
        //print_r($output);exit;
        //echo $this->getLastSql();

        $lands   = $this->getLandInfoById($lid_list);
        $tickets = $this->getTicketsInfoById($tid_list);
        $members = $this->getMemberInfoById($member_list);
        $checked = $this->getCheckedTnum($part_list);
        return [
            'orders'    => $output,
            'lands'     => $lands,
            'tickets'   => $tickets,
            'members'   => $members,
            'checked'   => $checked,
        ];
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

    /**
     * 云票务订单汇总
     *
     * @param int $unix_tm_start 查询开始时间-时间戳
     * @param int $unix_tm_end 查询结束时间-时间戳
     * @param int $op_id 操作员ID
     * @param int $lid 景点ID
     * @return array
     */
    public function CTS_SaleSummary($unix_tm_start, $unix_tm_end, $op_id, $lid=0)
    {
        $where = [
            'op_id'=>$op_id,
            'created_time'=>['between', [$unix_tm_start, $unix_tm_end]]
        ];
        $ordernum_list = $this->table('pft_ordercustomer')
            ->where($where)
            ->getField('ordernum', true);
        if (!$ordernum_list) {
            return false;
        }
        $where = ['ordernum'=>['in', $ordernum_list]];
        if (is_numeric($lid) && $lid>0) $where['lid'] = $lid;
        $orders = $this->table(self::__ORDER_TABLE__)
        ->where($where)
        ->field('ordernum,paymode,status,tnum,totalmoney,tid,tprice')
        ->select();
        $data = array();
        //修改的票数
        $orders_modify = $this->table(self::__ORDER_TRACK__)
            ->where(['ordernum'=>['in', $ordernum_list], 'action'=>['in', [1, 7] ] ])
            ->field('SUM(tnum) AS tnum,tid,ordernum')
            ->group('tid')
            ->select();
        $modify = [];
        foreach ($orders_modify as $item) {
            $modify[$item['ordernum'].'_'.$item['tid']] = $item['tnum'];
        }
        $fee_order = [];
        foreach ($orders as $order) {
            //门票ID列表
            $ticket_ids[] = $order['tid'];
            //收款
            $data[$order['paymode']][$order['tid']][1]['tnum']  += $order['tnum'];
            $data[$order['paymode']][$order['tid']][1]['money'] += $order['totalmoney'];
            //退款
            if (isset($modify[$order['ordernum'].'_'.$order['tid']])) {
                //echo $order['ordernum'],'--',$order['tid'],"\n";
                $fee_order[$order['ordernum']]= [$order['paymode'], $order['tid']];
                $data[$order['paymode']][$order['tid']][0]['tnum']  += $modify[$order['tid']];
                $data[$order['paymode']][$order['tid']][0]['money'] += $modify[$order['tid']] * $order['tprice'];
            }
            //退款取消/撤销
            if ($order['status']==3 || $order['status']==6 ) {
                //echo $order['status'],'---',$order['ordernum'],'--',$order['tid'],"\n";
                $fee_order[$order['ordernum']]= [$order['paymode'], $order['tid']];
                $data[$order['paymode']][$order['tid']][0]['tnum']  += $order['tnum'];
                $data[$order['paymode']][$order['tid']][0]['money'] += $order['totalmoney'];
            }
        }
        $ticket_names = $this->table('uu_jq_ticket t')
            ->join('uu_land l ON l.id=t.landid')
            ->where(['t.id'=>['in', array_unique($ticket_ids)]])
            ->getField('t.id, t.title as ttitle, l.title as ltitle', true);
        //print_r($ticket_names);exit;
        //return $data;
        //手续费
        if (count($fee_order)>0) {
            $cancel_fee = $this->table('pft_member_journal')
                ->where(['orderid'=>['in', $ordernum_list], 'dtype'=>14])
                ->field('orderid,dmoney')
                ->select();
            foreach ($cancel_fee as $fee) {
                $mode = $fee_order[$fee['orderid']][0];
                $tid  = $fee_order[$fee['orderid']][1];
                if (!$mode) {
                    print_r($fee);exit;
                }
                $data[$mode][$tid][2] += $fee['dmoney'];
            }
        }
        //{"付款方式":1,"money":2459,"tnum":23,"fee":14},{"付款方式":1,"money":2459,"tnum":23,"fee":14}
        $pay_mode_list = OrderDict::DictOrderPayMode();
        $output = array();
        //return $data;
        foreach ($data as $pay_mode => $tickets) {
            $output[$pay_mode] = [
                'mode'   => $pay_mode,
                'name'   => $pay_mode_list[$pay_mode],
                'tk'     => ['tnum'=>0, 'money'=>0],
                'sk'     => ['tnum'=>0, 'money'=>0],
                'sxf'    => 0,
            ];
            foreach ($tickets as $tid=>$item) {
                $output[$pay_mode]['tk']['tnum']    += $item[0]['tnum'];
                $output[$pay_mode]['tk']['money']   += $item[0]['money'];

                $output[$pay_mode]['sk']['tnum']    += $item[1]['tnum'];
                $output[$pay_mode]['sk']['money']   += $item[1]['money'];

                $output[$pay_mode]['tickets'][] = [
                    'id'    => $tid,
                    'scenic'=> $ticket_names[$tid]['ltitle'],
                    'ticket'=> $ticket_names[$tid]['ttitle'],
                    'tk'    => isset($item[0]) ? $item[0] : ['tnum'=>0, 'money'=>0],
                    'sk'    => $item[1],
                    'sxf'   => isset($item[2]) ? $item[2] : 0,
                ];
            }

        }
        return array_values($output);
    }
}