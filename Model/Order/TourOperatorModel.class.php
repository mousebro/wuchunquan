<?php
/**
 * User: Fang
 * Time: 22:32 2016/3/5
 */

namespace Model\Order;

use Library\Model;

class TourOperatorModel extends Model
{
    private $_memberTable = 'pft_member';

    /**
     * 判断用户是否可以给员工配置计调下单 - 暂时只对云顶账号开放
     *
     * @param int $memberId
     *
     * @return bool
     */
    public function hasTourOPGrantAuth($memberId)
    {
        $table     = $this->_memberTable;
        $where     = array(
            "id" => $memberId,
        );
        $field     = "group_id";
        $resultSet = $this->table($table)
                          ->where($where)
                          ->field($field)
                          ->find();
        $auth      = ($resultSet['group_id'] == 4) ? true : false;

        return $auth;
    }

    /**
     * 查询用户是否可以进行计调下单
     *
     * @param int $supplierId 供应商id
     * @param int $memberId   员工id
     * @param int $dtype      账号类型
     *
     * @return bool
     */
    public function hasTourOPAuth($supplierId, $memberId, $dtype)
    {
        $supplierHasGrantAuth = $this->hasTourOPGrantAuth($supplierId);
        $asStaff              = ($dtype == 6);
        $isGranted            = $this->isGrantedOPAuth($memberId);
        $auth                 = ($supplierHasGrantAuth && $asStaff
                                 && $isGranted);

        return $auth;
    }

    /**
     * 查询用户是否被授权
     *
     * @param int $memberId
     *
     * @return bool
     */
    private function isGrantedOPAuth($memberId)
    {
        $table     = $this->_memberTable;
        $where     = array(
            "id" => $memberId,
        );
        $field     = "member_auth";
        $resultSet = $this->table($table)
                          ->where($where)
                          ->field($field)
                          ->find();
        $auth      = in_array('dispatch',
            explode(',', $resultSet['member_auth']));

        return $auth;
    }

    /**
     * 测试用：打印调用的sql语句
     *
     * @return string
     */
    private function test()
    {
        $str = $this->getLastSql();
        print_r($str . PHP_EOL);
    }
}