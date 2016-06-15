<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/15-015
 * Time: 17:25
 */

namespace Model\Order;


use Library\Model;

class OrderSubmit extends Model
{
    const __TBL_ORDER__ = 'uu_ss_order';

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
            ->getField('id');
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
        return $this->makeOrderSn($id);
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
}