<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/10-010
 * Time: 10:27
 */

namespace Library\Dict;


class OrderDict
{
    public static function DictOrderStatus()
    {
        return array(
            0=> '未使用',
            1=> '已验证',
            2=> '已过期',
            3=> '已取消',
            4=> '凭证码被替代',
            5=> '已撤改',
            6=> '已撤销',
            7=> '部分使用',
        );
    }
    public static function DictOrderPayStatus()
    {
        return  array(
            0=> '现场支付',
            1=> '已支付',
            2=> '未支付',
        );
    }
    public static function DictOrderMode()
    {
        return array(
            0  => '',
            1  => '散客预订',
            2  => '用户手机支付',
            9  => '现场购票',
            10 => '云票务',
            11 => '微信商城',
            16 => '计调下单'
        );
    }
    public static function DictOrderPayMode()
    {
        //6=>'会员卡支付',7=>'银联支付',8=>'环迅支付',9=>现金支付，10=>会员卡
        return array(
            0=> '账户余额',
            1=> '支付宝',
            2=> '授信支付',
            3=> '自供自销',
            4=> '现场支付',
            5=> '微信支付',
            6=> '会员卡支付',
            7=> '银联支付',
            8=> '环迅支付',
            9=> '现金支付',
            10=>'会员卡2',
            11=>'拉卡拉支付',
        );
    }

    public static function DictProductType()
    {
        return array(
            'A' => '景区',
            'B' => '线路',
            'C' => '酒店',
            'F' => '套票',
            'G' => '餐饮',
            'H' => '演出',
        );
    }
}