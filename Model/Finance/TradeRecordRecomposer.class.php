<?php
/**
 * User: Fang
 * Date: 2016/6/27
 * Time: 12:18
 * Description: 将交易记录转化为可读数据
 */

namespace Model\Finance;

use Library\Exception;

class TradeRecordRecomposer
{
    use TradeRecordMemberRecomposer;
    private $record;
    private $ptype;
    private $member;
    private $partner;
    private $self;
    private $other;

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
     * 获取解析结果
     *
     * @return array
     */
    public function getRecord()
    {
        $this->record['fid'] = $this->self['id'];
        $this->record['aid'] = $this->other['id'];
        $record = $this->record;
        unset($this->record);

        return $record;
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

    static function combineStr($array, $glue)
    {
        $array = array_filter($array);
        $str = implode($glue, $array);

        return $str;
    }

    /**
     * 转换交易渠道
     */
    public function recomposeChannel()
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
     * 转换金额
     */
    public function recomposeMoney()
    {
        $options = ['dmoney', 'lmoney'];
        foreach ($options as $money) {
            $this->record[ $money ] = strval(sprintf($this->record[ $money ] / 100, 2));
        }
        if ($this->is_acc_reverse && !in_array($this->ptype, [2, 3])) {
            $this->record['dmoney'] = $this->record['daction'] == 0 ? ("-" . $this->record['dmoney']) : ("+" . $this->record['dmoney']);
            $this->record['lmoney'] = '';
        } else {
            $this->record['dmoney'] = $this->record['daction'] == 0 ? ("+" . $this->record['dmoney']) : ("-" . $this->record['dmoney']);
        }

        return $this;
    }

    public function recompseExcelMoney()
    {

        $dmoney = ltrim($this->record['dmoney'], '+');
        $this->record['income'] = $this->record['outcome'] = '';
        if ($dmoney > 0) {
            $this->record['income'] = $dmoney;
        } else {
            $this->record['outcome'] = $dmoney;
        }

        return $this;
    }

    /**
     * 转换交易内容
     * 如果pft_alipay_rec表中没有对应记录，则取p_name
     */
    public function recomposeTradeContent()
    {
        if (empty($this->record['body']) && isset($this->record['p_name'])) {
            $this->record['body'] = $this->record['p_name'];
            unset($this->record['p_name']);
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
    public function recomposeTradeType($separator = '-')
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
        $this->initMemberInfo();

        if ($this->is_acc_reverse) {
            $this->self = $this->partner;
            $this->other = $this->member;
        } else {
            $this->other = $this->partner;
            $this->self = $this->member;
        }
        $this->ptype = $this->record['ptype'];
        return $this;
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


    public function recomposeMemberDetail()
    {
        $this->record['member'] = $this->_recomposeMemberDetail($this->self);
        $this->record['counter'] = $this->_recomposeMemberDetail($this->other);
        return $this;
    }

    public function recomposeMemberInfo($separator = '<br/>')
    {
        $this->record['taccount'] = $this->self['acc_type'];
        $this->record['member'] = $this->self['dname'];
        $this->record['counter'] = self::combineStr([$this->other['dname'], $this->other['acc_type']], $separator);

        return $this;
    }

    public function recomposeMemberExcel()
    {
        $this->record['member'] = $this->self['dname'];
        $this->record['counter'] = $this->other['dname'];
        if ($this->self['is_payee']) {
            $payee = $this->self;
            $payer = $this->other;
        } else {
            $payee = $this->other;
            $payer = $this->self;
        }
        $this->record['payer_acc'] = $payer['account'];
        $this->record['payee_acc'] = $payee['account'];
        $this->record['payer_acc_type'] = $payer['acc_type'];
        $this->record['payee_acc_type'] = $payee['acc_type'];

        return $this;
    }

    public function recomposePayType()
    {
        if (!isset($this->record['ptype'])) {
            return $this;
        }
        $ptype = $this->record['ptype'];
        $ptype_list = C('pay_type');
        $this->record['ptype'] = $ptype_list[ $ptype ][1];

        return $this;
    }
}