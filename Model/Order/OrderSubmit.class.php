<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/15-015
 * Time: 17:25
 */

namespace Model\Order;


use Library\Cache\Cache;
use Library\Model;

class OrderSubmit extends Model
{
    const __TBL_ORDER__ = 'uu_ss_order';
    /**
     * @var \Library\Cache\CacheRedis;
     */
    private $cache;
    /**
     * 检测远端订单号是否唯一
     *
     * @param $remoteNum
     * @param $memberId
     * @return mixed
     */
    public function is_uk_remote($remoteNum, $memberId)
    {
        $map = ['remotenum'=>':remotenum', 'member'=>':member','status'=>['neq',3]];
        return $this->table(self::__TBL_ORDER__)
            ->where($map)
            ->bind([':remotenum'=>$remoteNum, ':member'=>$memberId])
            ->limit(1)
            ->getField('id,ordernum,code,salerid');
    }

    /**
     * 限购票判断
     *
     * @param int $tid 票ID
     * @param int $limitNum 限购票数
     * @param int $buyNum 本次欲购买票数
     * @param string $dateType 限购时间类型,0:不限，1:每日，2:每周，3:每月
     * @param string $mobile 手机号
     * @param string $idCard 身份证
     * @param string $chk_column 校验的值,1手机号，2身份证
     * @return bool | int
     */
    public function buyLimitCheck($tid, $limitNum, $buyNum, $dateType, $mobile, $idCard, $chk_column)
    {
        $now = time();
        $today = date('Y-m-d');
        $cache_key = "$tid:$dateType:" . ($chk_column==1 ? $mobile : $idCard);
        switch ($dateType) {
            case 1:
                $expire = strtotime($today . ' 23:59:59') - $now;
                break;
            case 2:
                $w = date('w');
                $diff = $w==0 ? 0 : 7-$w;
                $expire = strtotime("+$diff days");
                break;
            case 3:
                $diff = date('t') - date('j');
                $expire = strtotime("+$diff days");
                break;
            default:
                $expire = 0;
                break;
        }

        /**
         * @var $cache \Library\Cache\CacheRedis
         */
        $cache = Cache::getInstance('redis');
        $this->cache = $cache;
        $cacheNum = $cache->get($cache_key);
        if (!$cacheNum) {
            $cache->set($cache_key, 0, '', $expire);
        }
        $totalNum = $cacheNum + $buyNum;
        if ($cacheNum>0 && $cacheNum >= $limitNum || $totalNum > $limitNum) return false;
        return [$expire, $cache_key];
    }

    /**
     * 购票限制-操作缓存数据
     *
     * @param int $buyNum 0 增加1减少
     * @param string $key 键值
     * @param int $buyNum 数量
     * @param int $expire 过期时间
     * @return bool
     */
    public function UpdateBuyLimit($action, $key, $buyNum, $expire)
    {
        if (is_null($this->cache)) {
            $this->cache = Cache::getInstance('redis');
        }
        if ($action==0) {
            $res = $this->cache->incrBy($key, $buyNum);
        }
        else {
            $this->cache->decrBy($key, $buyNum);
        }
        return true;
    }
    /**
     * 检测凭证号是否有效
     *
     * @param int $lid landid
     * @param int $code 凭证号
     * @return mixed
     */
    public function is_ok_code($lid, $code, $pCode)
    {
        if ($pCode==1) {
            $id  = $this->table(self::__TBL_ORDER__)
                ->join(' s left join uu_order_addon a on s.ordernum=a.orderid')
                ->where(['s.code'=>$code, 'a.ifpack'=>1, ])
                ->limit(1)
                ->getField('s.id');
        }
        else {
            $map = ['lid'=>':lid', 'code'=>':code','status'=>0];
            $id  = $this->table(self::__TBL_ORDER__)
                ->where($map)
                ->bind([':lid'=>$lid, ':code'=>$code])
                ->limit(1)
                ->getField('id');
        }

        return $id > 0 ? false : true;
    }

    public function get_sale_list($fid, $aid)
    {
        $pids = $this->table('pft_product_sale_list')
            ->where(['fid'=>$fid, 'aid'=>$aid,'status'=>0])
            ->limit(1)
            ->getField('pids');
       return $pids;
    }
    public function getOrderCode($ordernum)
    {
        return $this->table(self::__TBL_ORDER__)->where(['ordernum'=>$ordernum])->getField('code');
    }

    public function TaoBaoOrder($taobaoOrderId, $ordernum)
    {
        $id = $this->table('pft_taobao_o2o_log')->where(['order_id'=>$taobaoOrderId])->getField('id');
        if($id > 0){
            return $this->table('pft_taobao_o2o_log')
                ->where("id=$id")
                ->save(['uu_order_id'=>$ordernum]);
        }
        return false;
    }

    /**
     * 订单编号生成规则，n(n>=1)个订单表对应一个支付表，
     * 生成订单编号(年取1位 + $pay_id取13位 + 第N个子订单取2位)
     * 1000个会员同一微秒提订单，重复机率为1/100
     * @param int $pay_id 支付表自增ID
     * @return string
     * copy from shopnc
     */
    public function makeOrderSn($pay_id) {
        //记录生成子订单的个数，如果生成多个子订单，该值会累加
        static $num;
        if (empty($num)) {
            $num = 1;
        } else {
            $num ++;
        }
        return (date('y',time()) % 9+1) . sprintf('%013d', $pay_id) . sprintf('%02d', $num);
    }
    public  function getOrderSn() {
        $id = $this->table('pft_order_key')->add(['id'=>'null']);
        return $id;//$this->makeOrderSn($id);
    }

    public function addOrder($params)
    {
        return $this->table(self::__TBL_ORDER__)->data($params)->add();
    }

    public function addOrderApplyInfo($params)
    {
        return $this->table('uu_order_apply_info')->data($params)->add();
    }

    public function addOrderAddon($params)
    {
        return $this->table('uu_order_addon')->data($params)->add();
    }
    public function addOrderDetail($params)
    {
        return $this->table('uu_order_fx_details')->data($params)->add();
    }
    public function addSaleRecord($member, $aid,$ordern,$pid,$tid, $lid, $pMoney, $nMoney, $eMoney )
    {
        $params = [
            'fid'       => $member,
            'aid'       => $aid,
            'ordernum'  => $ordern,
            'pid'       => $pid,
            'tid'       => $tid,
            'lid'       => $lid,
            'pMoney'    => $pMoney,
            'nMoney'    => $nMoney,
            'emoney'    => $eMoney,
            'rectime'   => date('Y-m-d H:i:s'),
        ];
        $lastid = $this->table('pft_onsale_record')->data($params)->add();
        if (!$lastid) pft_log('order/error', 'OrderSubmit.addSaleRecord Error:'.$this->_sql());
        return $lastid;
    }


}