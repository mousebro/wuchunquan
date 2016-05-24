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
}