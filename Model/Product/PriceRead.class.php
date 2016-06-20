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
     * 联盟检测
     *
     * @param int $fid 发起人ID
     * @param int $mid 分销商ID
     * @return bool
     */
    protected function PFT_D_Union_ZK_SE($fid, $mid){
        $id = $this->table('pft_union_member_info_SE')
            ->where(['fid'=>$fid,'memberID'=>$mid,'dstatus'=>0])
            ->limit(1)
            ->getField('id');
        return $id > 0 ? true : false;
    }

    /**
     * @param string $ac 帐号
     * @param int $pid 产品ID
     * @param string $date 日期
     * @param int $mode 模式[1单个价格 2单个最低价 3时间段XML格式价格]
     * @param int $ptype 类型[0分销价 1零售价]
     * @param int $get_storage 获取当日库存上限返回-1无限
     * @param int $m 供应商ID
     * @param string $sdate 开始时间
     * @param string $edate 结束时间
     * @return int|mixed  101 无此帐号 103 pid参数错误   1065 数据错误   105 无此价格
     *
     */
    public function Dynamic_Price_And_Storage($ac, $pid, $date='', $mode=0, $ptype=0, $get_storage=0, $m, $sdate='', $edate='')
    {
        if (is_numeric($pid)===false) return 105;
        if ($ptype==0){
            $fxs_id = $this->table('pft_member')->where(['account'=>$ac, 'status'=>0])->limit(1)->getField('id');
            if (!$fxs_id) return 101;
            $apply_did = $this->table('uu_jq_ticket')->where(['pid'=>$pid])->limit(1)->getField('apply_did');
            if ($apply_did!=$fxs_id){
                //判断是否可以购买此供应商下的产品
                $pids = $this->table('pft_product_sale_list')->where(['fid'=>$fxs_id, 'aid'=>$apply_did, 'status'=>0])->limit(1)->getField('pids');
                $flag=0;
                if ($pids!='A'){
                    $a_pids=explode(',',$pids);
                    if (!in_array($pid,$a_pids)) $flag=1;
                }
                $aids = $this->table('pft_p_apply_evolute')->where(['pid'=>$pid, 'fid'=>$fxs_id,'sid'=>$m, 'status'=>0])->limit(1)->getField('aids');
                if (!$aids) $flag2=1;
                else $flag2=0;
                if ($flag==1 && $flag2==1) return 1065;

                $arr_aids=explode(',',$aids);

                $ci=count($arr_aids);
                $ex_price=0;
                for ($i=0;$i<$ci;$i++){
                    if ($i==0) continue;
                    $j  = $i-1;
                    if ($i==1) {
                        //取得此分销商的差价
                        $priceRet = $this->get_price_set($pid, $arr_aids[$i], 0);
                        $aid_dprice=$priceRet['dprice'];
                        if (!$aid_dprice) $aid_dprice=0;
                        //分销联盟会员等价发起人1
                        if ($this->PFT_D_Union_ZK_SE($apply_did,$arr_aids[$i])) $aid_dprice=0;
                    }else{
                        //计算供应商应扣多少钱
                        $priceRet = $this->get_price_set($pid, $arr_aids[$i], $arr_aids[$j]);
                        $aid_dprice = $priceRet['dprice'];
                        if (!$aid_dprice) $aid_dprice=0;
                        //分销联盟会员等价发起人2
                        if ($this->PFT_D_Union_ZK_SE($arr_aids[$j],$arr_aids[$i])) $aid_dprice=0;
                    }
                    //增量价格
                    $ex_price += $aid_dprice;
                }
                $j++;
                $last_apply_did =$arr_aids[$j]?$arr_aids[$j]:0;
                $priceRet       = $this->get_price_set($pid, $fxs_id, $last_apply_did, 1, 'id,dprice');
                if (!$priceRet) $tt_dprice=0;
                else $tt_dprice=$priceRet['dprice'];

                if ($last_apply_did == 0 && !$priceRet['id']) return 1065;

                //分销联盟会员折扣3
                $LM_apply_did=$arr_aids[$j]?$arr_aids[$j]:$apply_did;
                if ($this->PFT_D_Union_ZK_SE($LM_apply_did,$fxs_id)) $tt_dprice=0;

                $ex_price+=$tt_dprice;

            }else{
                $ex_price=0;
            }
        }
        $dprice      = isset($ex_price) ? $ex_price : 0; // 差价
        return $this->get_Dynamic_Price_Merge($pid, $date, $mode, $sdate, $edate, $ptype, $get_storage, $dprice);
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
    public function get_Dynamic_Price_Merge($pid, $date='', $mode=0, $sdate='', $edate='', $ptype=0, $get_storage=0, $add_dprice=0)
    {
        //取单个价格
        if ($mode==1 && $date){
            return $this->getSpecialPeice($pid, $ptype, $date, $get_storage) + $add_dprice;
        }
        elseif($mode==2){
            return $this->getLowestPrice($pid, $ptype) + $add_dprice;
        }
        return $this->getPriceList($pid, $sdate, $edate, $add_dprice);
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
        $fields[1] = $q_s_price;
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
        $fields[1] = $q_s_price;
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
    private function getPriceList($pid,$sdate='', $edate='', $add_dprice=0)
    {
        $where = "pid=$pid";
        if ($sdate!=='' && $edate!==''){
            $where .= " AND greatest(start_date,'$sdate')<=least(end_date,'$edate')";
        }
        $data = $this->table($this->price_table)
            ->field("id,pid,ptype,(n_price+$add_dprice) as n_price,(s_price+$add_dprice) as s_price,l_price,start_date,end_date,memo,weekdays,storage")
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

    public function get_price_set($tid, $pid, $aid, $limit=1, $field='dprice')
    {
        $map = [
            'tid'=>$tid,
            'pid'=>$pid,
            'aid'=>$aid,
        ];
        $query = $this->table('uu_priceset')->where($map)->limit($limit)->field($field);
        if ($limit==1) return $query->find();
        return $query->select();
    }
}