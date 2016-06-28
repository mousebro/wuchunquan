<?php
/**
 * User: Fang
 * Date: 2016/6/12
 * Time: 14:56
 * Description: 将交易记录转化为可读数据
 */

namespace Model\Finance;

use Library\Exception;
use Model\Member\Member;

class TradeRecordParser
{
    private $record;
    private $memberModel;
    private $is_acc_reverse;
    private $is_online_pay;
    private $is_fid_payee;
    private $is_self_payee;

    /**
     * 传入交易记录
     *
     * @param array $record
     *
     * @return $this
     * @throws Exception
     */
    public function setRecord(array $record, $fid = 0, $partner_id = 0)
    {
        if (!isset($this->record)) {
            $this->record = $record;
        } else {
            throw new Exception('数据未读取', 301);
        }
        if ($fid) {
            $this->fid = $fid;
        }
        if ($partner_id) {
            $this->partner_id = $partner_id;
        }
        if (isset($this->fid) && $this->fid) {
            $this->is_acc_reverse = $this->record['fid'] != $this->fid;
        } else {
            $this->is_acc_reverse = isset($this->record['fid']) && ($this->record['fid'] != $_SESSION['sid'] && $_SESSION['sid'] != 1);
        }

        $this->is_fid_payee = $this->record['daction'] == 0;
        $this->is_self_payee = ($this->record['daction'] == 0 && !$this->is_acc_reverse) || ($this->record['action'] && $this->is_acc_reverse);
        //var_dump($this->record['fid']);

        return $this;
    }

    /**
     * 获取解析结果
     *
     * @return array
     */
    public function getRecord()
    {
        $record = $this->record;
        unset($this->record);

        return $record;
    }

    /**
     * 查询会员名称
     *
     * @param string $separator
     *
     * @return $this
     */
    public function parseMember($separator = '<br>')
    {
        $options['opid'] = 'oper';
        $account = [];
        $this->getMemberAccInfo($account);

        $pay_types = C('pay_type');
        $payer_acc = $pay_types[ $this->record['ptype'] ][1];
        switch ($this->record['ptype']) {
            //平台账户
            case 0:
                $this->record['counter'] = $this->record['counter'] ?: '';
                if ($this->is_fid_payee) {
                    $this->record['payer_acc'] = $account['aid'];
                    $this->record['payee_acc'] = $account['fid'];
                } else {
                    $this->record['payer_acc'] = $account['fid'];
                    $this->record['payee_acc'] = $account['aid'];
                }
                $partner_info = (!$this->is_acc_reverse) ? $account['aid'] : $account['fid'];
                break;
            // 授信账户
            case 2:
                // no break;
            case 3:
                $partner_info = (!$this->is_acc_reverse) ? '(供应商)授信账户' : '(分销商)授信账户';
                if ($this->record['daction'] == 0) {
                    $this->record['payer_acc'] = $account['aid'];
                    $this->record['payee_acc'] = $account['fid'];;
                } else {
                    $this->record['payer_acc'] = $account['fid'];;
                    $this->record['payee_acc'] = $account['aid'];
                }
                break;

            ////在线支付
            //case 1:
            //    // no break;
            //case 4:
            //    // no break;
            //case 5:
            //    // no break;
            //case 6:
            //    // no break;
            //case 11:
            //    // no break;
            default:
                //显示支付宝账号
                if ($this->is_acc_reverse) {
                    $self = 'aid';
                    $other = 'fid';
                } else {
                    $self = 'fid';
                    $other = 'aid';
                }
                if ($this->record['payer_acc'] && $this->record['ptype'] == 1) {
                    $payer_acc .= ':' . $this->record['payer_acc'];
                    $this->record['payer_acc'] = $payer_acc;
                }
                if ($this->is_self_payee) {
                    $partner_info = $payer_acc;
                    if (in_array($this->record['payee_type'], [0, 1])) {
                        $this->record['payee_acc'] = $account[ $self ];
                    }
                } else {
                    $partner_info = $account[ $other ];
                    $this->record['payer_acc'] = $payer_acc;
                    $this->record['payee_acc'] = $account[ $other ];
                }
                break;
        }

        if ($separator && isset($partner_info)) {
            $this->record['counter'] = ltrim($this->record['counter'] . $separator . $partner_info, $separator);
        }

        return $this;
    }

    /**
     * 转换金额
     */
    public function parseMoney()
    {
        $renew_time = C('update_time')[ ENV ];
        $options = ['dmoney', 'lmoney'];
        foreach ($options as $money) {
            $this->record[ $money ] = strval(sprintf($this->record[ $money ] / 100, 2));
        }

        if ($this->is_acc_reverse && $this->is_online_pay && $this->record['rectime'] < $renew_time) {
            $this->record['daction'] = decbin(!($this->record['daction']));
            $this->record['lmoney'] = '';
        }
        //var_dump($this->record['daction']);
        $this->record['dmoney'] = $this->record['daction'] == 0 ? ("+" . $this->record['dmoney']) : ("-" . $this->record['dmoney']);

        return $this;
    }

    /**
     * 转换交易渠道
     */
    public function parseChannel()
    {
        $channel_list = C('order_channel');
        if (array_key_exists($this->record['order_channel'], $channel_list)) {
            $this->record['order_channel'] = $channel_list[ $this->record['order_channel'] ];
        } else {
            $this->record['order_channel'] = '平台';
        }

        return $this;
    }

    /**
     * 转换交易内容
     */
    public function parseTradeContent()
    {
        if (empty($this->record['body']) && isset($this->record['p_name'])) {
            $this->record['body'] = $this->record['p_name'];
            unset($this->record['p_name']);
        }

        return $this;
    }


    /**
     * 转换支付方式
     *
     * @param   int    [ptype]             支付类型
     * @param   string [trade_no]          交易流水号
     * @param   int    [daction]           收入/支出标识
     *
     * @param   string [payer_cc]          支付方账号
     * @param   string [payee_cc]          收款方账号
     *
     * @param   string [payer_cc_type]     支付方账号类型
     * @param   string [payee_cc_type]     收款方账号类型
     *
     * @param   string [taccount]          交易账户类型
     *
     *
     * @return $this
     */
    public function parsePayType()
    {
        if (!isset($this->record['ptype'])) {
            return $this;
        }

        $ptype = $this->record['ptype'];

        $ptype_list = C('pay_type');
        $acc_type_list = C('account_type');

        //支付方式
        $this->record['ptype'] = $ptype_list[ $ptype ][1];

        //支付账户类型代码
        $p_acc = $ptype_list[ $ptype ][0];

        $this->record['payer_acc_type'] = $acc_type_list[ $p_acc ];

        $this->record['payer_acc'] = (in_array($ptype, [0, 1, 2, 3])) ? $this->record['payer_acc'] : '';

        $this->record['taccount'] = $acc_type_list[ $p_acc ];//默认交易账户取对应记录值


        //非在线支付类型的交易 与 支付宝收款方非票付通账户的 收款方账户类型与支付方式一致
        if (in_array($ptype, [0, 2, 3]) || ($ptype == 1 && !in_array($this->record['payee_type'], [0, 1]))) {
            $this->record['payee_acc_type'] = $acc_type_list[ $p_acc ];
            $this->record['trade_no'] = '';
            $this->is_online_pay = false;
        } else {
            $this->is_online_pay = true;
            //收入
            $this->record['payee_acc_type'] = '平台账户';
            $this->record['payer_acc_type'] = $acc_type_list[ $p_acc ];
        }

        if ((!$this->is_acc_reverse && $this->record['daction'] == 0) || ($this->is_acc_reverse && $this->record['daction'] == 1)) {
            $this->record['taccount'] = $this->record['payee_acc_type'];
        } else {
            $this->record['taccount'] = $this->record['payer_acc_type'];
        }

        return $this;
    }

    /**
     * 转换交易类型
     *
     * @param string $separator
     *
     * @return $this
     */
    public function parseTradeType($separator = '-')
    {
        if (isset($this->record['dtype'])) {
            $dtype_list = array_column(C('item_category'), 1);
            $cat_list = array_column(C('item_category'), 0);
            if (array_key_exists($this->record['dtype'], $dtype_list)) {
                $this->record['item'] = $cat_list[ $this->record['dtype'] ];
                $this->record['dtype'] = $dtype_list[ $this->record['dtype'] ];
                //交易记录对应分类
                $item_list = C('trade_item');
                if (array_key_exists($this->record['item'], $item_list)) {
                    $this->record['item'] = $item_list[ $this->record['item'] ] . $separator . $this->record['dtype'];
                }
            }
        }

        return $this;
    }

    /**
     * 转换支付方账号
     */
    public function parsePayee()
    {
        if (isset($this->record['payee_type'])) {
            $this->record['payee_type'] = C('payee_type')[ $this->record['payee_type'] ];
        }

        return $this;
    }

    /**
     * 转换收款方账号
     */
    public function parsePayer()
    {
        if (array_key_exists('payer_acc', $this->record)) {
            if (!($this->record['payer_acc'])) {
                $this->record['payer_acc'] = '';
            }
            if ($this->record['ptype'] == 0) {
                $this->record['payer_acc'] = $this->getMemberModel()->getMemberCacheById($this->record['fid'],
                    'account');
            }
        }

        return $this;
    }

    /**
     * 获取会员模型
     *
     * @return mixed
     */
    public function getMemberModel()
    {
        if (!isset($this->memberModel)) {
            $this->memberModel = new Member();
        }

        return $this->memberModel;
    }

    /**
     * 将excel表中的数值型转成字符串型
     *
     * @param $string
     */
    static function wrapStr(&$string)
    {
        $string = '<td style="vnd.ms-excel.numberformat:@">' . $string . '</td>';

    }

    /**
     * 查看交易详情会员
     *
     * @param array $account
     *
     * @return $this
     */
    public function parseMemberBasic(&$account = [])
    {
        $account = $this->getMemberAccInfo($account);

        switch ($this->record['ptype']) {
            //平台账户
            case 0:
                // no break;
                $this->record['counter'] = $this->record['counter'] ?: '';
                $this->record['counter'] .= !empty($account['counter']) ? self::join_bracket([
                    '平台账户:',
                    $account['counter'],
                ]) : '';
                $this->record['member'] .= !empty($account['member']) ? self::join_bracket([
                    '平台账户:',
                    $account['member'],
                ]) : '';
                break;
            case 2:
                // no break;
            case 3:
                $this->record['counter'] .= $this->is_acc_reverse ? '(分销商授信账户)' : '(供应商授信账户)';
                $this->record['member'] .= $this->is_acc_reverse ? '(供应商授信账户)' : '(分销商授信账户)';
                break;
            ////在线支付
            //case 1:
            //    // no break;
            //case 4:
            //    // no break;
            //case 5:
            //    // no break;
            //case 6:
            //    // no break;
            //case 11:
            //    // no break;
            default:
                $pay_types = C('pay_type');

                //交易账户类型
                $payer_acc = $pay_types[ $this->record['ptype'] ][1];
                //显示支付宝账号
                if ($this->record['payer_acc'] && $this->record['ptype'] == 1) {
                    $payer_acc .= ':' . $this->record['payer_acc'];
                }
                if ($this->is_acc_reverse) {
                    $self = 'counter';
                    $other = 'member';
                } else {
                    $self = 'member';
                    $other = 'counter';
                }
                if ($this->record['daction'] == 0) { //收入
                    $payee = $self;
                    $payer = $other;
                } else {
                    $payee = $other;
                    $payer = $self;
                }
                $this->_recomposeMemberAccount($payee, $payer, $account[ $payee ], $payer_acc);
                break;
        }

        return $this;
    }

    /**
     * 将excel字段设置为字符串类型
     *
     * @param   array $field 字段名
     *
     * @return  $this
     */
    public function excelWrap(array $field)
    {
        foreach ($field as $key) {
            if (!empty($this->record[ $key ])) {
                self::wrapStr($this->record[ $key ]);
            }
        }

        return $this;
    }

    /**
     * 给字符串加上小括号
     *
     * @param   array $array 多个字符串以数组形式传入
     *
     * @return  string
     */
    static function join_bracket($array)
    {
        $str = '(' . implode('', $array) . ')';

        return $str;
    }

    /**
     * 在交易详情的交易账户和对方账号中加上支付账户信息
     *
     * @param   string $payee     当前收款方
     * @param   string $payer     当前支付方
     * @param   string $payee_acc 收款方账号
     * @param   string $payer_acc 支付方账号
     */
    private function _recomposeMemberAccount($payee, $payer, $payee_acc, $payer_acc)
    {
        if (!empty($this->record[ $payee ])) {
            $this->record[ $payee ] .= self::join_bracket(['平台账户:', $payee_acc]);
        } else {
            $this->record[ $payee ] = '平台账户';
        }

        if (!empty($this->record[ $payer ])) {
            if (!empty($this->record['payer_cc'])) {
                $this->record[ $payer ] .= self::join_bracket([$payer_acc, $this->record['payer_cc']]);
            } else {
                $this->record[ $payer ] .= self::join_bracket([$payer_acc]);
            }
        } else {
            $this->record[ $payer ] = $payer_acc;
        }
    }

    /**
     * @param $account
     *
     * @return mixed
     */
    protected function getMemberAccInfo(&$account)
    {
        if ($this->is_acc_reverse) {
            $options['aid'] = 'member';
            $options['fid'] = 'counter';
        } else {
            $options['aid'] = 'counter';
            $options['fid'] = 'member';
        }

        foreach ($options as $key => $value) {
            if (!$this->record[ $key ]) {
                $this->record[ $value ] = '';
                $account[ $key ] = '';
            } elseif ($this->record[ $key ] == 1) {
                //$is_pft_trade = ($this->record[$key] == 1);
                $this->record[ $value ] = '票付通信息科技';
                $account[ $key ] = $account[ $value ] = '';
            } else {
                $this->record[ $value ] = $this->getMemberModel()->getMemberCacheById($this->record[ $key ],
                    'dname') ?: '';
                $account[ $key ] = $account[ $value ] = $this->getMemberModel()->getMemberCacheById($this->record[ $key ],
                    'account') ?: '';
            }
        }

        return $account;
    }
}