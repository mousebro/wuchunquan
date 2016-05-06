<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/5-005
 * Time: 9:32
 */

namespace Model\Product;


use Library\Model;

class PriceWrite extends Model
{
    private $price_table = 'uu_product_price';


    /**
     * [综合]供应商产品动态价格插入或修改
     *
     * @param int $pid 商品总表ID
     * @param string $startdate 开始日期
     * @param string $enddate 结束日期
     * @param int $g_price 供应价
     * @param int $l_price 零售价
     * @param int $ptype 类型[0平时1特殊]
     * @param int $mode 编辑模式[0插入1修改2修改根据外键ID]
     * @param int $rid 记录ID[或外键ID]
     * @param string $memo 价格说明
     * @param string $ondays 适用日期[例如：0,1,2,3,4,5,6]
     * @param string $storage 库存-1不限量0售罄
     * @param int $c_id c_id不为零pid/tid
     * @return int|string
     */
    public function In_Dynamic_Price_Merge($pid, $startdate='', $enddate='', $g_price=-1, $l_price=-1, $ptype=0, $mode=0, $rid=0, $memo='', $ondays='', $storage='',$c_id=0)
    {
        if ($mode==0 && (!$startdate || !$enddate)) return $this->err11;
        if ($ondays==='') return $this->err11;
        $price_data = [];
        if ( $ptype==0 && $g_price>=0 ) {
            $price_data['n_price'] = $g_price;
            $price_data['s_price'] = 0;
        }
        elseif ( $ptype==1 && $g_price>=0 ) {
            $price_data['n_price'] = 0;
            $price_data['s_price'] = $g_price;
        }
        if ($l_price >= 0) $price_data['l_price'] = $l_price;
        if ($startdate) $price_data['start_date'] = $startdate;
        if ($enddate) $price_data['end_date'] = $enddate;
        if ($ondays!=='') $price_data['weekdays'] = $ondays;
        if ($storage!=='') $price_data['storage'] = $storage;

        if ($c_id==11) {
            $ticketObj = new Ticket();
            $pid = $ticketObj->QueryTicketInfo(['id'=>$pid],'pid');
            $pid = $pid[0]['pid'];
        }

        if ($startdate || $enddate) {//查找是否有时间交集的记录
            $strY="";
            $strY.="and string_bj(weekdays,'$ondays')=1";
            if ($mode==1 && $rid) $strY.=" and id<>'$rid'";
            $str="select id from uu_product_price where greatest(start_date,'$startdate')<=least(end_date,'$enddate') and pid=$pid and ptype=$ptype $strY limit 1";
            $GLOBALS['le']->query($str);
            if ($GLOBALS['le']->fetch_assoc()) return $this->err42;
        }
        if ($mode==1 || $mode==2){
            // 修改0平日周末1特殊日
            $strid=($mode==2)?'rid':'id';
            $str="UPDATE uu_product_price set {$strU} memo='$memo' where $strid='$rid'";
            $GLOBALS['le']->query($str);
            return 100;
        }
        $str="insert uu_product_price set pid=$pid,ptype=$ptype,{$strU} memo='$memo'";
        $GLOBALS['le']->query($str);
        return 100;

    }
}