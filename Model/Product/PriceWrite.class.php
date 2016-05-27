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

    public static function ErrorMsg($code)
    {
        $msgs = [
            0   => '价格数据保存出错',
            101 => '价格数据格式出错',
            142 => '时间段价格存在交集',
        ];
        return isset($msgs[$code]) ? $msgs[$code] : '未知错误';
    }

    /**
     * [综合]供应商产品动态价格插入或修改
     *
     * @param int $pid 商品总表ID
     * @param string $startdate 开始日期
     * @param string $enddate 结束日期
     * @param int $g_price 供应价
     * @param int $l_price 零售价
     * @param int $ptype 类型[0平时1特殊]
     * @param int $mode 编辑模式[0插入1修改]
     * @param int $rid 记录ID[或外键ID]
     * @param string $memo 价格说明
     * @param string $ondays 适用日期[例如：0,1,2,3,4,5,6]
     * @param string $storage 库存-1不限量0售罄
     * @param int $c_id c_id不为零pid/tid
     * @return int|string
     */
    public function In_Dynamic_Price_Merge($pid, $startdate='', $enddate='',
                                           $g_price=-1, $l_price=-1, $ptype=0,
                                           $mode=0, $rid=0, $memo='', $ondays='',
                                           $storage='',$c_id=0)
    {
        if ($mode==0) {
            if (!$startdate || !$enddate) return 101;
            if ($ondays==='') return 101;
        }
        $price_data = [
            'pid'   => $pid,
            'ptype' => $ptype,
            'memo'  => $memo,
        ];
        if ( $ptype==0 && $g_price>=0 ) {
            $price_data['n_price'] = $g_price;
            $price_data['s_price'] = 0;
        }
        elseif ( $ptype==1 && $g_price>=0 ) {
            $price_data['n_price'] = 0;
            $price_data['s_price'] = $g_price;
        }
        if ($l_price >= 0)  $price_data['l_price'] = $l_price;
        if ($startdate)     $price_data['start_date'] = $startdate;
        if ($enddate)       $price_data['end_date'] = $enddate;
        if ($ondays!=='')   $price_data['weekdays'] = $ondays;
        if ($storage!=='')  $price_data['storage'] = $storage;

        if ($c_id==11) {
            $ticketObj = new Ticket();
            $pid = $ticketObj->QueryTicketInfo(['id'=>$pid],'pid');
            $pid = $pid[0]['pid'];
            $price_data['pid'] = $pid;
        }
        //查找是否有时间交集的记录
        $where  = "greatest(start_date,'$startdate')<=least(end_date,'$enddate')"
                ." and pid=$pid and ptype=$ptype"
                . " and string_bj(weekdays,'$ondays')=1";
        if ($mode==1 && $rid) $where.=" and id<>'$rid'";
        $_id = $this->table($this->price_table)->where($where)->limit(1)->getField('id');

        if ($_id>0) return 142;//['code'=>0, 'msg'=>'时间段价格存在交集'];
        //update
        if ($mode==1) {
            $ret = $this->table($this->price_table)->where(['id'=>$rid]) ->save($price_data);
        }
        else {
            $ret = $this->table($this->price_table)->data($price_data)->add();
        }
        if ($ret===true || $ret>0) return 100;
        //write_log
        $msg = [
            'log_type'  => 'create_price_error',
            'msg'       => '修改或新增产品价格失败,原因:' . $this->getDbError(),
            'data'      => $price_data,
            'args'      => func_get_args(),
        ];
        write_to_logstash('platform_app_log', $msg);
        return 0;
    }

    public function RemovePrice($id, $pid)
    {
        return $this->table($this->price_table)->where(['id'=>$id, 'pid'=>$pid])->delete();
    }
}