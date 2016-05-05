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

    public function OrderConsume()
    {

    }
}