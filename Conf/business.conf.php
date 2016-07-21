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
        'phone'           => '您正在使用修改手机号功能，您的验证码为{vcode}【票付通】',
        'register'        => '验证码{vcode}，欢迎使用票付通平台。',
        'relation'        => '{dname}您好！【{aname}】添加您为平台分销商。帐号为您的手机号,密码{pwd},赶快登录www.12301.cc或关注"票付通"、"pft_12301"微信公众号，绑定账号分销{aname}的产品吧~帮助：t.cn/RZG1HLA',
        'wechat_bind'     => '您正在使用微信绑定功能，验证码：{vcode}',
    ),

    //黑名单
    'black_list' => array(
        'mobile' => array('18661797480','13026506113'),
        'ip'     => array()
    ),
    'order_pay_mode'=> [//订单支付类型
        0=>"账户余额",
        1=>"支付宝",
        2=>"授信支付",
        3=>"产品自销",
        4=>"现场支付",
        5=>'微信支付',
        6=>'会员卡支付',
        7=>'银联支付',
        8=>'环迅支付',
        9=>'现金支付',
        10=>'会员卡',
        11=>'拉卡拉（商户）',
        12=>'拉卡拉（平台）',
    ],
    'pay_type'  => [//交易记录支付类型
        0=>'帐号资金',
        1=>'支付宝',
        2=>'供应商处可用资金',
        3=>'供应商信用额度设置',
        4=>'财付通',
        5=>'银联',
        6=>'环迅',
        9=>'现金',
        10=>'会员卡',
        11=>'拉卡拉（商户）',
        12=>'拉卡拉（平台）',
    ],
    'order_mode'  => [//下单方式
        0=>'正常分销商下单',
        1=>'普通用户支付',
        2=>'用户手机支付',
        9=>'会员卡购票',
        10=>'云票务',
        11=>'微信商城',
        12=>'自助机',
        13=>'二级店铺',
        14=>'闸机购票',
        15=>'智能终端',
    ],
    //自动提现默认配置
    'withdraw_default' => array(
        'day' => array(
            'service_fee'       => 5, //默认千分之五
            'reserve_money'     => 200, //默认冻结多少钱
            'reserve_scale'     => 20,  //默认冻结的比例
            'limit_money'       => 200,  //最低需要达到多少才能体现 - 单位元
            'auth_money'        => 50000,  //金额达到多少需要财务审核 - 单位元
            'low_service_money' => 1,  //金额达到多少需要财务审核 - 单位元
        ),
        'week' => array(
            'service_fee'       => 5, //默认千分之五
            'reserve_money'     => 200, //默认冻结多少钱
            'reserve_scale'     => 20,  //默认冻结的比例
            'limit_money'       => 200,  //最低需要达到多少才能体现 - 单位元
            'auth_money'        => 50000,  //金额达到多少需要财务审核 - 单位元
            'low_service_money' => 1,  //金额达到多少需要财务审核 - 单位元
        ),
        'month' => array(
            'service_fee'       => 5, //默认千分之五
            'reserve_money'     => 200, //默认冻结多少钱
            'reserve_scale'     => 20,  //默认冻结的比例
            'limit_money'       => 200,  //最低需要达到多少才能体现 - 单位元
            'auth_money'        => 50000,  //金额达到多少需要财务审核 - 单位元
            'low_service_money' => 1,  //金额达到多少需要财务审核 - 单位元
        )
    )
);


