<?php
/**
 * 业务配置数据
 *
 * @author dwer
 * @date   2016-05-18
 */

return array(
    //短信模板配置
    'sms' => array(
        'account_upgrade' => '您正在使用账号升级功能。验证码:{vcode}。【票付通】',
        'alipay_m'        => '您正在绑定或者修改支付宝账号，您的验证码为{vcode}。',
        'change_pwd'      => '您正在修改密码，验证码{vcode}',
        'forget'          => '您正在使用找回密码功能，您的验证码为{vcode}',
        'hotel_order'     => '预订通知：客人{dname}预订了{pname}{num}间，{begintime}入住，{endtime}离店，订单号：{ordernum}。联系电话：{tel}。客人备注信息：{note}。',
        'order_search'    => '您正在使用手机号查询订单功能。验证码:{vcode}。【票付通】',
        'phone'           => '您您正在使用修改手机号功能，您的验证码为{vcode}【票付通】',
        'register'        => '验证码{vcode}，欢迎使用票付通平台。',
        'relation'        => '{dname}您好！【{aname}】添加您为平台分销商。帐号为您的手机号,密码{pwd},赶快登录www.12301.cc或关注"票付通"、"pft_12301"微信公众号，绑定账号分销{aname}的产品吧~帮助：t.cn/RZG1HLA',
        'wechat_bind'     => '您正在使用微信绑定功能，验证码：{vcode}',
    ),
    'black_list' => array(
        'mobile' => array('18661797480','13026506113'),
        'ip'     => array()
    )

);


