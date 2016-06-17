<?php
/**
 * User: Fang
 * Time: 16:49 2016/6/3
 */

return array(
    //交易渠道
    'order_channel' => [
        0  => '平台-分销商下单',
        1  => 'PC支付',
        2  => '手机支付',
        9  => '会员卡购票',
        10 => '云票务',
        11 => '微信商城',
        12 => '自助机',
        13 => '二级店铺',
        14 => '闸机购票',
        15 => '智能终端',
        16 => '计调下单',
    ],
    //支付类型 第一列: 对应account_type的键值; 第二列: 支付方式描述; 第三列：所属支付大类
    'pay_type'      => [
        0  => [0, '平台账本', 1],
        1  => [1, '支付宝', 0],
        2  => [2, '授信支付', 2],
        3  => [2, '供应商信用额度设置', 2],
        4  => [3, '微信', 0],
        5  => [4, '银联', 0],
        6  => [5, '环迅', 0],
        9  => [7, '现金', 3],
        11 => [6, '商户拉卡拉', 0],
        12 => [6, '平台拉卡拉', 0],
    ],
    //支付大类
    'pay_cat'       => [
        0 => '在线支付',
        1 => '余额支付',
        2 => '授信支付',
        3 => '现金支付',
    ],
    //账户类型
    'account_type'  => [
        0 => '平台账户',
        1 => '支付宝',
        2 => '授信账户',
        3 => '微信',
        4 => '银联',
        5 => '环迅',
        6 => '拉卡拉',
        7 => '现金账户',
    ],
    //交易类型分类
    'item_category' => [
        0  => [2, '购买产品',],
        1  => [2, '修改/取消订单',],
        2  => [1, '未定义操作',],
        3  => [3, '充值/扣款',],
        4  => [3, '供应商授信余额',],
        5  => [2, '产品利润',],
        6  => [3, '提现冻结',],
        7  => [1, '电子凭证费',],
        8  => [1, '短信息费',],
        9  => [1, '银行交易手续费',],
        10 => [1, '凭证费',],
        11 => [3, '供应商信用额度变化',],
        12 => [3, '取消提现',],
        13 => [3, '拒绝提现',],
        14 => [2, '退款手续费',],
        15 => [2, '押金',],
        16 => [2, '充值返现',],
        17 => [2, '撤销/撤改订单',],
        18 => [3, '转账',],
        19 => [4, '佣金发放',],
        20 => [4, '佣金提现',],
        21 => [4, '获得佣金',],
        22 => [1, '平台费',],
        23 => [2, '出售产品',],
    ],
    //交易大类
    'trade_item'    => [
        1 => '平台运营',
        2 => '产品交易',
        3 => '账户操作',
        4 => '佣金利润',
    ],

    //excel表头
    'excel_head'    => [
        'order_channel'  => '交易渠道',
        'member'         => '交易商户',
        'counter'        => '对方商户',
        //'oper'           => '操作人',
        'rectime'        => '交易时间',
        'item'           => '交易分类|明细分类',
        'orderid'        => '交易号',
        'body'           => '交易内容',
        'dmoney'         => '操作金额(单位:元)',
        'lmoney'         => '账户余额(单位:元)',
        'ptype'          => '支付方式',
        //'taccount'      => '交易账户',
        'payer_acc'      => '支付方账号',
        'payer_acc_type' => '支付方账号类型',
        'payee_acc'      => '收款方账号',
        'payee_acc_type' => '收款方账号类型',
        'trade_no'       => '支付流水号',
        'memo'           => '备注',
    ],
    'payee_type'    => [
        '',
        '票付通',
        '鼓浪屿',
        '鼓浪屿休闲屋',
        '云顶',
        '法门寺',
    ],
    //pft_alipay_rec.sourceT 与 pft_member_journal.ptype的对应
    'sourceT_ptype' => [
        0 => '',  //支付宝
        1 => '',  //微信
        2 => '',  //银联
        3 => '',  //环迅
        4 => '',  //现金
        6 => '',  //商户拉卡拉
    ],
);