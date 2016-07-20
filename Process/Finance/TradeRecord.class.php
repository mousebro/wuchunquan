<?php
/**
 * 交易记录数据统一处理层
 * 有些数据需要比较复杂的而且可重用的处理，可以统一放在这边处理
 * 
 * @author dwer
 * @date   2016-07-14
 *
 */
namespace Process\Finance;

class TradeRecord {

    /**
     * 解析支付类型
     *
     * @param $fid       被查询用户id
     * @param $partnerId 合作商家id
     * @param $map       查询条件数组
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    public static function parseAccountType($fid, $partnerId) {
        //接收参数
        $account_type = \safe_str(I('ptypes'));

        $pay_types       = array_combine(array_keys(C('pay_type')), array_column(C('pay_type'), 2));
        $online_pay_type = array_keys($pay_types, 0);

        //如果没有传fid参数    
        if (!is_numeric($account_type) || !$fid) {
            return [];
        }

        //查询条件
        $map = [];

        switch ($account_type) {
            case 2: //no break;
            case 99: //no break;
            case 97:
                $map['account_type'] = ['in', [2, 3]];
                break;
            case 98: //获取在线支付类
                $map['account_type'] = ['in', $online_pay_type];
                break;
            case 100:
                break;
            default:
                $map['account_type'] = $account_type;
        }

        //参数初始化
        $self  = 'fid';
        $other = 'aid';
        $logic = ['_logic' => 'or'];

        $fid_as_self  = [$self => $fid];
        $fid_as_other = [$other => $fid];

        //选择了对方商户
        if ($partnerId) {
            $fid_as_other += [$self => $partnerId];
            $fid_as_self  += [$other => $partnerId];
        }

        if ($account_type == 100) {
            $fid_as_other += [$other => $fid, 'account_type' => ['in', [2, 3],]];
            $map['_complex'][] = [$fid_as_other, $fid_as_self] + $logic;
        } elseif ($account_type == 99) {
            $fid_as_other += [$other => $fid, 'account_type' => ['in', [2, 3],]];
            $map += $fid_as_other;
        } elseif ($account_type == 97) {
            $map['_complex'][] = [$fid_as_other, $fid_as_self] + $logic;
        } else {
            $map += $fid_as_self;
        }

        return $map;
    }

    /**
     * 解析时间参数
     *
     * @param   string [btime]     开始时间        yy-mm-dd hh:ii:ss
     * @param   string [etime]     结束时间        yy-mm-dd hh:ii:ss
     *
     * @return  array|bool
     * @throws  \Library\Exception
     */
    public static function parseTime() {
        //开始时间
        $btime = self::_validateTime('btime', "today midnight", "00:00:00");
        //结束时间 - 默认为当前时间
        $etime = self::_validateTime('etime', "today 23:59:59", "23:59:59");
        $interval = [$btime, $etime];
        return $interval;
    }

    /**
     * 处理处理
     * @author dwer
     * @date   2016-07-16
     *
     * @param  $record 交易记录
     * @return
     */
    public static function handleData(&$record) {
        //判断fid是不是收款方
        $isPayee = $record['daction'] == 0;

        $selfAccType = self::getDefaultAccountType($record['member_acc_type'], $isPayee, $record['fid'], $record['ptype'], $record['dtype']);

        $partnerAcctype = self::getDefaultAccountType('', !$isPayee, $record['aid'], $record['ptype'], $record['dtype']);

        //不显示金额
        if (!in_array($selfAccType, [0, 2, 3]) && $record['lmoney'] == 0) {
            $record['lmoney'] = '';
        }

        //转换账号类型名称
        $record['taccount'] = self::getAccountName($selfAccType, true);
        $record['member']   = $record['self_name'];
        $record['counter']  = $record['partner_name'] . '<br />' . self::getAccountName($partnerAcctype, false, $record['is_acc_reverse']);

        $tmp = self::getTradeName($record['dtype']);
        $record['dtype'] = $tmp['dtype_name'];
        $record['item']  = $tmp['item_name'];

        //转化金额
        $tmp = self::transMoney($record['dmoney'], $record['lmoney'], $record['daction'], $record['ptype'], $record['is_acc_reverse']);

        $record['dmoney']  = $tmp['dmoney'];
        $record['lmoney']  = $tmp['lmoney'];

        //处理订单的状态
        if(isset($record['status'])) {
            $record['status'] = self::getStatusName($record['status']);
        } else {
            $record['status'] = '';
        }

        //删除没用数据
        unset($record['is_acc_reverse'], $record['partner_acc_type'], $record['self_name'], $record['self_account'], $record['partner_name'], $record['partner_account'], $record['payee_acc'], $record['payer_acc']);
    }

    /**
     *  详细数据处理
     * @author dwer
     * @date   2016-07-17
     *
     * @param  &$record
     * @return
     */
    public static function handleDetailData(&$record) {
        //判断fid是不是收款方
        $isPayee = $record['daction'] == 0;

        $selfAccType = self::getDefaultAccountType($record['member_acc_type'], $isPayee, $record['fid'], $record['ptype'], $record['dtype']);

        $partnerAcctype = self::getDefaultAccountType('', !$isPayee, $record['aid'], $record['ptype'], $record['dtype']);

        //不显示金额
        if (!in_array($selfAccType, [0, 2, 3]) && $record['lmoney'] == 0) {
            $record['lmoney'] = '';
        }

        $selfTradeAcc    = $isPayee ? $record['payee_acc'] : $record['payer_acc'];
        $partnerTradeAcc = $isPayee ? $record['payer_acc'] : $record['payee_acc'];

        $selfAccount    = self::getAccount($selfAccType, $record['self_account'], $selfTradeAcc);
        $partnerAccount = self::getAccount($partnerAcctype, $record['partner_account'], $partnerTradeAcc);

        //转换交易内容
        $tmp = self::getTradeName($record['dtype']);
        $record['dtype'] = $tmp['dtype_name'];
        $record['item']  = $tmp['item_name'];

        if (empty($record['body']) && isset($record['p_name'])) {
            $record['body'] = $record['p_name'];
            unset($record['p_name']);
        }

        //转换账号
        $selfAccountName    = self::getAccountName($selfAccType, true, $record['is_acc_reverse'], true);
        $partnerAccountName = self::getAccountName($partnerAcctype, false, $record['is_acc_reverse']);

        $record['member']  = self:: getMemberDetail($selfAccountName, $selfAccount, $record['self_name']);
        $record['counter'] = self:: getMemberDetail($partnerAccountName, $partnerAccount, $record['partner_name']);

        //转换支付类型和购买渠道
        $record['ptype'] = self::getPayName($record['ptype']);
        $record['order_channel'] = self::getChannelName($record['order_channel']);

        //转化金额
        $tmp = self::transMoney($record['dmoney'], $record['lmoney'], $record['daction'], $record['ptype'], $record['is_acc_reverse']);
        $record['dmoney']  = $tmp['dmoney'];
        $record['lmoney']  = $tmp['lmoney'];

        //删除没用数据
        unset($record['is_acc_reverse'], $record['self_name'], $record['self_account'], $record['partner_name'], $record['partner_account']);
    }

    /**
     * Excel导出数据处理
     * @author dwer
     * @date   2016-07-17
     *
     * @param  &$record
     * @return
     */
    public static function handleExcelData(&$record) {
        //判断fid是不是收款方
        $isPayee = $record['daction'] == 0;

        $selfAccType = self::getDefaultAccountType($record['member_acc_type'], $isPayee, $record['fid'], $record['ptype'], $record['dtype']);

        $partnerAcctype = self::getDefaultAccountType($record['partner_acc_type'], !$isPayee, $record['aid'], $record['ptype'], $record['dtype']);

        //不显示金额
        if (!in_array($selfAccType, [0, 2, 3]) && $record['lmoney'] == 0) {
            $record['lmoney'] = '';
        }

        $selfTradeAcc    = $isPayee ? $record['payee_acc'] : $record['payer_acc'];
        $partnerTradeAcc = $isPayee ? $record['payer_acc'] : $record['payee_acc'];

        $selfAccount    = self::getAccount($selfAccType, $record['self_account'], $selfTradeAcc);
        $partnerAccount = self::getAccount($partnerAcctype, $record['partner_account'], $partnerTradeAcc);

        //类型名称
        $selfAccName    = self::getAccountName($selfAccType, true, $record['is_acc_reverse'], true);
        $partnerAccName = self::getAccountName($partnerAcctype, false, $record['is_acc_reverse'], false);

        //转换交易内容
        $tmp = self::getTradeName($record['dtype'], '|');
        $record['dtype'] = $tmp['dtype_name'];
        $record['item']  = $tmp['item_name'];

        //会员信息
        $record['member']  = $record['self_name'];
        $record['counter'] = $record['partner_name'];

        if ($isPayee) {
            $record['payer_acc'] = $record['partner_account'];
            $record['payee_acc']      = $record['self_account'];
            $record['payer_acc_type'] = $partnerAccName;
            $record['payee_acc_type'] = $selfAccName;
        } else {
            $record['payer_acc'] = $record['self_account'];
            $record['payee_acc']      = $record['partner_account'];
            $record['payer_acc_type'] = $selfAccName;
            $record['payee_acc_type'] = $partnerAccName;
        }

        //如果pft_alipay_rec表中没有对应记录，则取p_name
        if (empty($record['body']) && isset($record['p_name'])) {
            $record['body'] = $record['p_name'];
            unset($record['p_name']);
        }

        //转化金额
        $tmp = self::transMoney($record['dmoney'], $record['lmoney'], $record['daction'], $record['ptype'], $record['is_acc_reverse']);
        $record['dmoney']  = $tmp['dmoney'];
        $record['lmoney']  = $tmp['lmoney'];

        //导出报表中区分收入与支出
        $dmoney = ltrim($record['dmoney'], '+');
        $record['income'] = $record['outcome'] = '';
        if ($dmoney > 0) {
            $record['income'] = $dmoney;
        } else {
            $record['outcome'] = $dmoney;
        }

        //转换支付类型和购买渠道
        $record['ptype'] = self::getPayName($record['ptype']);
        $record['order_channel'] = self::getChannelName($record['order_channel']);

        //将excel字段设置为字符串类型
        $field = ['payer_acc', 'payee_acc', 'outcome', 'income', 'lmoney', 'trade_no', 'orderid'];
        foreach ($field as $key) {
            if (!empty($record[ $key ])) {
                $record[ $key ] = '<td style="vnd.ms-excel.numberformat:@">' . $record[ $key ] . '</td>';
            }
        }

        //删除没用数据
        unset($record['is_acc_reverse'], $record['self_name'], $record['self_account'], $record['partner_name'], $record['partner_account']);
    }


    /**
     * 获取需要的账号信息
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $accType
     * @param  $account
     * @param  $tradeAcc
     * @return
     */
    private static function getAccount($accType, $account, $tradeAcc) {
        if(in_array($accType, [0, 2, 3])) {
            return $account;
        } elseif($accType == 1) {
            return $tradeAcc;
        } else {
            return '';
        }
    }

    /**
     * 重组交易详情中的会员信息
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $accType
     * @param  $account
     * @param  $tradeAcc
     * @return
     */
    private static function getMemberDetail($accType, $account, $dname) {
        if(!$dname) {
            return '';
        }

        if (isset($accType)) {
            $extend_info = $accType;
            if ($account && in_array($accType, [0, 1])) {
                $extend_info = $extend_info . ':' . $account;
            }
            $extend_info = '(' . $extend_info . ')';
        } else {
            $extend_info = '';
        }

        $result = $dname . $extend_info;
        return $result;
    }

    /**
     * 重组支付类型
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $accType
     * @param  $account
     * @param  $tradeAcc
     * @return
     */
    private static function getPayName($ptype) {
        $ptype      = intval($ptype);
        $ptype_list = C('pay_type');

        if($ptype < 0 || !isset($ptype_list[ $ptype ])) {
            return '';
        }

        return $ptype_list[ $ptype ][1];
    }

    /**
     * 转换交易渠道
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $accType
     * @param  $account
     * @param  $tradeAcc
     * @return
     */
    private static function getChannelName($order_channel) {
        $channel_list = C('order_channel');
        if (array_key_exists($order_channel, $channel_list)) {
            return $channel_list[ $order_channel ];
        } else {
            return '平台';
        }
    }

    /**
     * 获取账号类型名称
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $accType 类型 
     * @param  $isSelf 是不是fid
     * @param  $accReverse 是不是授信的反向记录
     * @param  $isDetail 是否获取详细的说明，在列表只要返回“信用账户”
     * @return
     */
    private static function getAccountName($accType, $isSelf = true, $accReverse = false, $isDetail = false) {
        $accountTypes = C('account_type');

        //授信类型处理
        if (in_array($accType, [2, 3])) {
            if($isSelf) {
                if($isDetail) {
                    if($accReverse) {
                        return '信用账户(供应商)';
                    } else {
                       return '信用账户(分销商)';
                    }
                } else {
                    return '信用账户';
                }
            } else {
                if($accReverse) {
                    return '信用账户(分销商)';
                } else {
                    return '信用账户(供应商)';
                }
            }
        }

        return $accountTypes[ $accType ];
    }

    /**
     * 获取交易类型名称
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $dtype 类型
     * @param  $separator 连接符
     * @return
     */
    private static function getTradeName($dtype, $separator = '-') {
        $dtype_list = array_column(C('item_category'), 1);
        $cat_list   = array_column(C('item_category'), 0);  

        if (array_key_exists($dtype, $dtype_list)) {
            $itemName  = $cat_list[ $dtype ];
            $dtypeName = $dtype_list[ $dtype];

            //交易记录对应分类
            $item_list = C('trade_item');
            if (array_key_exists($itemName, $item_list)) {
                $itemName = $item_list[ $itemName ] . $separator . $dtypeName;
            }
        }

        return ['dtype_name' => $dtypeName, 'item_name' => $itemName];
    }

    /**
     * 获取订单状态名称
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $dtype 类型
     * @param  $separator 连接符
     * @return
     */
    private static function getStatusName($status = '') {
        if($status === '') {
            return '';
        }

        $statusArr = C('order_status');
        if(isset($statusArr[ $status ])) {
            return $statusArr[ $status ];
        } else {
            return '';
        }
    }

    /**
     * 可视化金额
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $dmoney 本次交易的金额
     * @param  $lmoney 剩余金额
     * @return
     */
    private static function transMoney($dmoney, $lmoney, $daction, $ptype, $isAccReverse = false) {
        $dmoney = self::visualMoney($dmoney);
        $lmoney = self::visualMoney($lmoney);

        if ($isAccReverse && !in_array($ptype, [2, 3])) {
            $lmoney = '';
            $dmoney = $daction == 0 ? ("-" . $dmoney) : ("+" . $dmoney);
        } else {
            $dmoney = $daction == 0 ? ("+" . $dmoney) : ("-" . $dmoney);
        }

        return ['dmoney' => $dmoney, 'lmoney' => $lmoney];
    }

    /**
     * 将分转换为元
     * @author dwer
     * @date   2016-07-17
     *
     * @param  $money 金额 - 分为单位
     * @return
     */
    private static function visualMoney($money) {
        if($money === '') {
            return '';
        } else {
            return strval(sprintf($money / 100, 2));
        }
    }

    /**
     * 获取交易账户类型
     * 如果是支付方，那acc_type和ptype是一致的
     * 如果是收款方，就按下面的规则获取
     *
     * @param   int $acc_type 账户类型
     * @param   int $is_payee 是否收款方
     * @param   int $memberid 会员id
     * @param   int $ptype 支付类型
     * @param   int $dtype 交易类型
     *
     * @return int|string
     */
    private static function getDefaultAccountType($acc_type, $is_payee, $memberid, $ptype, $dtype) {
        if ($acc_type === '0' || ($acc_type && $acc_type != -1)) {
            return $acc_type;
        }

        switch ($ptype) {
            //授信账户和现金账户双方账户类型相同
            case 2: //no break;
            case 3: //no break;
            case 9: //no break;
                return $ptype;
            case 0:
                //非取消订单
                if (!in_array($dtype, [1, 17])) {
                    return 0;
                } else {
                    if($memberid == 112 || !$memberid) {
                        return '';
                    } else {
                        return 0;
                    }
                }
            default:
                if ($is_payee) {
                    return 0;
                } else {
                    return $ptype;
                }
        }
    }

    /**
     * @param   string $timeTag    时间字段
     * @param   string $defaultVal 绝对默认时间
     * @param   string $postfix    相对默认时间：未传入时分秒时的默认时间
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    protected static function _validateTime($timeTag, $defaultVal, $postfix) {
        $time = \safe_str(I($timeTag));

        if ($time) {
            if (!strtotime($time)) {
                throw new Exception('时间格式错误', 201);
            } else {
                if (strlen($time) < 11) {
                    $time .= ' ' . $postfix;
                }
            }
        }

        $time = $time ?: $defaultVal;

        $time = date('Y-m-d H:i:s', strtotime($time));

        return $time;
    }


}