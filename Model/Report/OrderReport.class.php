<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/15-015
 * Time: 17:13
 */

namespace Model\Report;


use Library\Model;

class OrderReport extends Model
{
    private $dbConf;
    public function __construct()
    {
        $this->dbConf = C('db');
        $this->db(1, $this->dbConf['summary'], true);
        $this->db(0, $this->dbConf['slave'], true);
        parent::__construct('slave');
    }

    public function getMobileCode()
    {
        $data = $this->db(1)->table('pft_mobile_city')->field('id,area_code')->select();
        $id_code = [];
        foreach( $data as $row ) {
            $id_code[$row['id']] = $row['area_code'];
        }
        return $id_code;
    }

    public function OrderCountEveryDayCreate($date)
    {
        $id_code = $this->getMobileCode();
        $begin_time = $date . ' 00:00:00';
        $end_time   = $date . ' 23:59:59';
        $dataResult = $this->db(0)->table('uu_ss_order o')->join('uu_order_fx_details f ON o.ordernum=f.orderid')
            ->field(' o.ordertel,o.tnum,o.member,o.aid,f.aids' )
            ->where(['o.ordertime'=>['between',[$begin_time, $end_time]]])
            ->select();
        echo $this->db(0)->getLastSql(),PHP_EOL;
        echo 'error 0:',$this->db(0)->getDbError(),PHP_EOL;
        echo 'error 1:',$this->db(1)->getDbError(),PHP_EOL;
        //echo $sql;
        //print_r($result);
        //exit;
        //$result = $this->query($sql);
        $result = $sids = [];
        foreach($dataResult as $item) {
            if ($item['aids']!=0) {
                $sids = explode(',', $item['aids']);
            }
            elseif($item['aid']!=$item['member']){
                $sids[] = $item['aid'];
            }
            $sids[] =  $item['member'];
            /*统计电话号码归属地 */
            $tele = substr($item['ordertel'], 0, 7);
            $code  = '';
            if (isset($id_code[$tele])) {
                $code = $id_code[$tele];
            } else {
                $code = $tele;
            }
            //客源地数组
            foreach ($sids as $v) {
                $result[$v][$code]['order'] += 1;
                $result[$v][$code]['tnum']  += $item['tnum'];
            }
        }
        $save_data = [];
        $ins = "INSERT into pft_order_count (sid,ddate,code,torder,tnum) values ";
        foreach($result as $sid => $value){
            foreach($value as $code => $val){
                $ins .= " ($sid,'$date','$code',{$val['order']},{$val['tnum']}),";
                //$save_data[] = [
                //    'sid'   =>$sid,
                //    'ddate' => $date,
                //    'code'  => $code,
                //    'torder'=> $val['order'],
                //    'tnum'  => $val['tnum'],
                //
                //];
            }
        }
        $ins = rtrim($ins, ',');
        $this->db(1)->execute($ins);
        //$this->db(1, $this->dbConf['summary'], true);
        //$this->db(1)->table('pft_order_count')->addAll($save_data);
    }

    public function OrderCreated()
    {
        $sql = <<<SQL
SELECT SUM(tnum),SUM(totalmoney) FROM uu_ss_order WHERE ordertime BETWEEN '' AND ''
SQL;

    }

    public function OrderCancel()
    {

    }

    public function OrderSummaryByLid($date)
    {
        /*CREATE TABLE `pft_applyer_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  `sday` int(11) NOT NULL COMMENT '统计日期 Ymd格式',
  `tnum` int(11) NOT NULL COMMENT '票数',
  `total_money` int(11) NOT NULL COMMENT '订单总金额',
  `onum` int(11) NOT NULL COMMENT '订单总数',
  `apply_did` int(11) NOT NULL COMMENT '供应商ID',
  `ltitle` varchar(50) COLLATE utf8_bin NOT NULL COMMENT '景区名称',
  `ttitle` varchar(50) COLLATE utf8_bin NOT NULL COMMENT '门票名称',
  `dname` varchar(50) COLLATE utf8_bin NOT NULL COMMENT '供应商名称',
  PRIMARY KEY (`id`),
  KEY `idx_day_aid` (`sday`,`apply_did`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;*/
        $startTime = $date . '000000';
        $endTime   = $date . '235959';

        $sql = <<<SQL
SELECT tid,COUNT(*) AS cnt,SUM(tnum) AS tnum, SUM(totalmoney) AS totalmoney
FROM uu_ss_order
WHERE ordertime BETWEEN '$startTime' AND '$endTime'
GROUP BY tid
SQL;
        //echo $sql;exit;
        $orders = $this->db(0)->query($sql);
        if (!$orders) return false;
        $output = [];
        foreach ($orders as $order) {
            $output[$order['tid']] = $order;
        }
        $infos = $this->db(0)->table('uu_jq_ticket t')
            ->join('left join uu_land l ON l.id=t.landid')
            ->join('LEFT JOIN pft_member m ON m.id=l.apply_did')
            ->where(['t.id'=>['in', array_keys($output)]])
            ->field('l.id as lid,t.id as tid, m.id as mid,l.title as ltitle,t.title as ttitle, m.dname')
            ->select();
        //echo $this->getLastSql();
        $save = array();
        //print_r($infos);
        //$sday = date('Ymd', strtotime($endTime));
        foreach ($infos as $info)
        {
            //echo $info['tid'];
            $save[] = [
                'sday'      => $date,
                'lid'       => $info['lid'],
                'tid'       => $info['tid'],
                'apply_did' => $info['mid'],
                'ltitle'     => $info['ltitle'],
                'ttitle'     => $info['ttitle'],
                'dname'     => $info['dname'],
                'onum'      =>$output[$info['tid']]['cnt'],
                'tnum'      => $output[$info['tid']]['tnum'],
                'total_money'=>$output[$info['tid']]['totalmoney'],
            ];
        }
        //var_export($save);exit;
        $res = $this->db(1)->table('pft_applyer_order')->addAll($save);
        if ($res===false) {
            echo $this->getDbError();
        }
    }

    public function OrderConsume()
    {

    }


    /**
     * 统计报表数据聚合
     * @author dwer
     * @date   2016-05-30
     *
     * @param $timeType 1：下单时间，2：预计游玩， 3：完成时间
     * @param $beginTime 开始时间 2016-10-23 00:00:00
     * @param $endTime 结束时间 2016-10-24 23:59:59
     * @param $orderBy 统计方式 lid：按景区统计，tid：按门票统计， mid：按分销商统计， aid：按供应商统计
     * @param $statusArr 订单状态 
     * @param $aid 供应商ID
     * @param $lid 景区ID
     * @param $ticketId 门票ID
     * @param $fid 分销商ID
     * @param $includeMy 是否包含自己购买的产品
     * @retur
     */
    public function summary($timeType, $beginTime, $endTime, $orderBy, $statusArr, $aid = '', $lid = '', $ticketId = '', $fid = '', $includeMy = '') {
        if(!in_array($timeType, [1, 2, 3]) || !in_array($orderBy, ['lid', 'tid', 'mid', 'aid']) || !$beginTime || !$endTime) {
            return [];
        }

        $field = 'count(s.id) as torder,sum(s.tnum) as ttnum,sum(s.tnum * s.tprice) as money, sum(totalmoney) as realmoney, paymode as pmode';
        $table = 'uu_ss_order as s';
        $where = [];
        $join  = '';
        $group = '';

        switch($timeType){
            case '1':
            default:
                $where['ordertime'] = [['egt',$beginTime], ['elt',$endTime]];
                break;
            case '2':
                $where['begintime'] = [['egt',$beginTime], ['elt',$endTime]];
                break;
            case '3':
                $where['dtime'] = [['egt',$beginTime], ['elt',$endTime]];
                break;
        }

        if(!$statusArr || !is_array($statusArr)) {
            //默认查询已经完成订单
            $statusArr = [1, 2, 3, 4, 5, 6, 7];
        }

        $statusStr       = implode(',', $statusArr);
        $statusStr       = trim($statusStr, ',');
        $where['s.status'] = ['in', $statusStr];

        switch($orderBy){
            case 'lid':
            default:
                $join  = " left join uu_land l on s.lid=l.id ";
                $field .= ",l.title as ltitle,lid ";
                $group = "s.lid";
                break;
                case 'tid':
                $join  = " left join uu_land l on s.lid=l.id left join uu_jq_ticket t on s.tid=t.id";
                $field .= ",l.title  as ltitle,t.title  as ttitle,tid ";
                $group = "s.tid";
                break;
            case 'mid':
                $join  = " left join order_aids_split os on s.ordernum=os.orderid left join pft_member d on os.buyerid=d.id";
                $field .= ",os.buyerid,d.dname";
                $group = "os.buyerid";
                break;
            case 'aid':
                $join  = "  left join order_aids_split os on s.ordernum=os.orderid left join pft_member d on os.sellerid=d.id";
                $field .= ",os.sellerid,d.dname";
                $group = "os.sellerid";
                break;
        }

        if($lid) {
            $where['_string'] = $where['_string'] ? $where['_string'] . " and s.lid = {$lid}" : "s.lid = {$lid}";
        }

        if($ticketId) {
            $where['_string'] = $where['_string'] ? $where['_string'] . " and s.tid = {$ticketId}" : "s.tid = {$ticketId}";
        }


        if($fid) {
            if($orderBy!='mid' && $orderBy!='aid'){
                $join .= ' left join order_aids_split os on s.ordernum=os.orderid ';
            }

            $where['_string'] = $where['_string'] ? $where['_string'] . " and os.buyerid = {$fid}" : "os.buyerid = {$fid}";
        }

        if($aid) {
            if($orderBy!='mid' && $orderBy!='aid' && !$fid){
                $join .= ' left join order_aids_split os on s.ordernum=os.orderid ';
            }

            $where['_string'] = $where['_string'] ? $where['_string'] . " and os.sellerid = {$aid}" : "os.sellerid = {$aid}";

            if(!$includeMy) {
                $where['_string'] = $where['_string'] ? $where['_string'] . " and os.sellerid<>os.buyerid" : "os.sellerid<>os.buyerid";
            }
        }


        $res = $this->table($table)
            ->field($field)
            ->join($join)
            ->group($group)
            ->where($where)
            ->select();

            echo $this->getLastSql();

        //返回的数据
        $resData   = array();
        $totalData = array();

        if($res) {
            //数据处理
            switch($orderBy){
                case 'lid':
                default:
                    foreach($res as $row) {
                        $row['money']       = number_format($row['money'],2,'.','');
                        $row['realmoney']   = number_format($row['realmoney'],2,'.','');

                        $resData[$row['lid']]['title']     = $row['ltitle'];
                        $resData[$row['lid']]['order']    += $row['torder'];
                        $resData[$row['lid']]['tnum']     += $row['ttnum'];
                        $resData[$row['lid']]['money']     += $row['money'];
                        $resData[$row['lid']]['realmoney'] += $row['realmoney'];

                        $totalData['order']    += $row['torder'];
                        $totalData['tnum']     += $row['ttnum'];
                        $totalData['money']     += $row['money'];
                        $totalData['realmoney'] += $row['realmoney'];

                        $resData[$row['lid']]['pmode'.$row['pmode']] +=  $row['money'];
                        $totalData['pmode'.$row['pmode']] +=  $row['money'];

                        $resData[$row['lid']]['realpmode'.$row['pmode']] +=  $row['realmoney'];
                        $totalData['realpmode'.$row['pmode']] +=  $row['realmoney'];
                    }
                    break;
                case 'tid':
                    foreach($res as $row) {
                        $row['money']       = number_format($row['money'],2,'.','');  
                        $row['realmoney']   = number_format($row['realmoney'],2,'.','');

                        $resData[$row['tid']]['order']    += $row['torder'];
                        $resData[$row['tid']]['title']     = $row['ltitle'].$row['ttitle'];
                        $resData[$row['tid']]['tnum']     += $row['ttnum'];
                        $resData[$row['tid']]['money']     += $row['money'];
                        $resData[$row['tid']]['realmoney'] += $row['realmoney'];

                        $totalData['order']    += $row['torder'];
                        $totalData['tnum']     += $row['ttnum'];
                        $totalData['money']     += $row['money'];
                        $totalData['realmoney'] += $row['realmoney'];

                        $resData[$row['tid']]['pmode'.$row['pmode']] +=  $row['money'];
                        $totalData['pmode'.$row['pmode']] +=  $row['money'];

                        $resData[$row['tid']]['realpmode'.$row['pmode']] +=  $row['realmoney'];
                        $totalData['realpmode'.$row['pmode']] +=  $row['realmoney'];
                    }
                    break;
                case 'mid':
                    foreach($res as $row) {
                        $row['money']     = number_format($row['money'],2,'.','');
                        $row['realmoney'] = number_format($row['realmoney'],2,'.','');

                        $resData[$row['buyerid']]['order']    += $row['torder'];
                        $resData[$row['buyerid']]['title']     = $row['dname'];
                        $resData[$row['buyerid']]['tnum']     += $row['ttnum'];
                        $resData[$row['buyerid']]['money']     += $row['money'];
                        $resData[$row['buyerid']]['realmoney'] += $row['realmoney'];

                        $totalData['order']    += $row['torder'];
                        $totalData['tnum']     += $row['ttnum'];
                        $totalData['money']     += $row['money'];
                        $totalData['realmoney'] += $row['realmoney'];

                        $resData[$row['buyerid']]['pmode'.$row['pmode']] +=  $row['money'];
                        $totalData['pmode'.$row['pmode']] +=  $row['money'];
                        
                        $resData[$row['buyerid']]['realpmode'.$row['pmode']] +=  $row['realmoney'];
                        $totalData['realpmode'.$row['pmode']] +=  $row['realmoney'];
                    }
                    break;
                case 'aid':
                    foreach($res as $row) {
                        $row['money']     = number_format($row['money'],2,'.','');
                        $row['realmoney'] = number_format($row['realmoney'],2,'.','');
                        
                        $resData[$row['sellerid']]['order']    += $row['torder'];
                        $resData[$row['sellerid']]['title']     = $row['dname'];
                        $resData[$row['sellerid']]['tnum']     += $row['ttnum'];
                        $resData[$row['sellerid']]['money']     += $row['money'];
                        $resData[$row['sellerid']]['realmoney'] += $row['realmoney'];

                        $totalData['order']    += $row['torder'];
                        $totalData['tnum']     += $row['ttnum'];
                        $totalData['money']     += $row['money'];
                        $totalData['realmoney'] += $row['realmoney'];

                        $resData[$row['sellerid']]['pmode'.$row['pmode']] +=  $row['money'];
                        $totalData['pmode'.$row['pmode']] +=  $row['money'];

                        $resData[$row['sellerid']]['realpmode'.$row['pmode']] +=  $row['money'];
                        $totalData['realpmode'.$row['pmode']] +=  $row['money'];
                    }
                    break;
            }
        }

        //返回处理过的数据
        return ['res_data' => $resData, 'total_data' => $totalData];
    }
}