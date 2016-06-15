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
}