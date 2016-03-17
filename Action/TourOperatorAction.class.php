<?php

/**
 * User: Fang
 * Time: 10:29 2016/3/9
 */
namespace Action;

use Model\Order\TourOperatorModel;

class TourOperatorAction extends BaseAction
{
    /**
     * 判断用户是否可以给员工配置计调下单 -任一供应商
     * @param $memberId
     *
     * @return bool
     */
    public function hasGrantAuth($memberId)
    {
        $memberId = intval($memberId);
        if ( ! $memberId) {
            return false;
        }
        $operatorModel = new TourOperatorModel();
        $auth          = $operatorModel->hasTourOPGrantAuth($memberId);

        return $auth;
    }

    /**
     * 判断用户是否具有计调下单权限 - 必须是员工账号
     * @param $supplierId
     * @param $memberId
     * @param $idType
     *
     * @return bool
     */
    public function hasOperateAuth($supplierId, $memberId, $idType)
    {
        $supplierId = intval($supplierId);
        $memberId   = intval($memberId);
        $idType     = intval($idType);
        if ( ! $supplierId || ! $memberId) {
            return false;
        }
        $operatorModel = new TourOperatorModel();
        $auth          = $operatorModel->hasTourOPAuth($supplierId, $memberId, $idType);

        return $auth;
    }
}