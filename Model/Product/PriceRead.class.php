<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/5-005
 * Time: 9:32
 */

namespace Model\Product;


use Library\Model;

class PriceRead extends Model
{
    private $price_table = 'uu_product_price';
    public function __construct()
    {
        parent::__construct('slave', '');
    }

    /**
     * 动态价格获取 Copy from ServerInside.class.php
     * @author GuangpengChen
     * @param int $pid  产品ID
     * @param string $date 日期
     * @param int $mode 模式[0XML 1单个价格 2单个最低价]
     * @param string $sdate 开始时间
     * @param string $edate 结束时间
     * @param int $ptype 类型[0供应价 1零售价],说明：时间段价格$date,$mode必须为默认空值并会跨时间集
     * @param int $get_storage 是否需要获取库存1需要
     * @return int|null|string
     */
    public function get_Dynamic_Price_Merge($pid, $date='', $mode=0, $sdate='', $edate='', $ptype=0, $get_storage=0)
    {
        //取单个价格
        if ($mode==1 && $date){
            return $this->getSpecialPeice($pid, $ptype, $date, $get_storage);
        }
        elseif($mode==2){
            return $this->getLowestPrice($pid, $ptype);
        }
       return $this->getPriceList($pid, $sdate, $edate);
    }

    /**
     * 获取日历模式配置的价格
     *
     * @param int $pid
     * @param $ptype
     * @param $date
     * @return int|string
     */
    private function getSpecialPeice($pid, $ptype, $date, $get_storage)
    {
        $onday=date('w',strtotime($date));
        $fields = ['storage'];
        $q_s_price=($ptype==0)?'s_price':'l_price as s_price';
        $fields['q_s_price'] = $q_s_price;
        $where = [
            'start_date'=>['elt',$date],
            'end_date'  =>['egt', $date],
            'pid'       => $pid,
            'ptype'     => 1,
            "string_bj(weekdays,$onday)"=>1,
        ];
        $data = $this->table($this->price_table)->field($fields)->where($where)->order("id desc")->find();
        $s_price=$data['s_price'];
        $storage=$data['storage'];
        if (is_numeric($s_price)) {
            if ($get_storage==1) return "$s_price,$storage";
            else return $s_price;
        }
        $q_s_price=($ptype==0)?'n_price':'l_price as n_price';
        $fields['q_s_price'] = $q_s_price;
        $where['ptype'] = 0;
        $data = $this->table($this->price_table)->field($fields)->where($where)->order("id desc")->find();
        $n_price=$data['n_price'];
        $storage=$data['storage'];
        if (is_numeric($n_price)) {
            if ($get_storage==1) return "$n_price,$storage";
            else return $n_price;
        }
        return -1;
    }
    /**
     * 获取时间段价格
     *
     * @param int $pid 产品ID
     * @param string $sdate
     * @param string $edate
     * @return mixed
     */
    private function getPriceList($pid,$sdate='', $edate='')
    {
        $where = "pid=$pid";
        if ($sdate!=='' && $edate!==''){
            $where .= " AND greatest(start_date,'$sdate')<=least(end_date,'$edate')";
        }
        $data = $this->table($this->price_table)
            ->field('id,pid,ptype,n_price,s_price,l_price,start_date,end_date,memo,weekdays,storage')
            ->where($where)->order('id desc')
            ->select();
        //echo $this->getLastSql();
        return $data;
    }

    /**
     * 获取最低价
     *
     * @param $pid
     * @param $ptype
     * @return int|null
     */
    private function getLowestPrice($pid, $ptype)
    {
        $now = date('Y-m-d H:i:s');
        //单个有效最低价
        $fields = "ptype,min(s_price) as ms,min(n_price) as ns, min(l_price) as ls";
        $where  = [
            "pid" => $pid,
            "date_add(end_date,interval 1 day)"=>['gt',$now],
        ];
        $data = $this->table($this->price_table)
            ->field($fields)
            ->where($where, false)
            ->group('ptype')
            ->select();
        foreach ($data as $item) {
            $price[$item['ptype']] = $item;
        }
        //echo $this->getLastSql();
        //print_r($price);
        if ($ptype==0) {
            $mp = $price[0]['ns'];
            $np = $price[1]['ms'];
        }
        else {
            $mp = $price[0]['ls'];
            $np = $price[1]['ls'];
        }

        if ($mp===NULL && $np!==NULL) $lp=$np;
        elseif ($np===NULL && $mp!==NULL) $lp=$mp;
        elseif ($mp!==NULL && $np!==NULL) $lp=($mp>$np) ? $np: $mp;
        else $lp=-1;
        return $lp;
    }
}