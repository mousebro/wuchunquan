<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/10-010
 * Time: 13:46
 *
 * 订单追踪
 */

namespace Model\Order;


use Library\Model;

//CREATE TABLE `pft_order_track` (
//  `id` int(10) NOT NULL AUTO_INCREMENT,
//  `ordernum` varchar(20) NOT NULL,/*订单号*/
//`action` tinyint(1) unsigned not null default 0,/*事件类型 0 验证 1 取消 2 出票 3支付 4验证 5撤销 6撤改*/
//  `tid` int(10) unsigned not null,/*本次操作票类*/
//  `tnum` int(10) unsigned not null,/*本次操作门票张数*/
//  `left_num` int(10) unsigned not null,/*剩余门票张数*/
//  `source` tinyint(1) unsigned not null default 0,/* 操作来源 0 终端机 1 软终端 3 自助机 4 外部通知更新 5云票务 6云闸机*/
//  `terminal` int(11) unsigned not null,/* 终端号 */
//  `branchTerminal` int(11) unsigned not null,/* 终端号分支 */
//  `id_card` varchar(20) not null, /* 验证身份证/手机号/凭证码 */
//  `insertTime` datetime NOT NULL,/*记录时间*/
//  `oper_member`  int(10) unsigned not null,/*操作员id*/


class OrderTrack extends Model
{
    public function addTrack()
    {

    }
}