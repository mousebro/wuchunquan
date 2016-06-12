<?php
/**
 * User: Fang
 * Date: 2016/6/12
 * Time: 14:56
 * Description: 将交易记录转化为可读数据
 */

namespace Controller\Finance;

use Library\Exception;
use Model\Member\Member;

class TradeRecordParser
{
    private $record;
    private $memberModel;

    /**
     * 传入交易记录
     * @param array $record
     * @return $this
     * @throws Exception
     */
    public function setRecord(array $record)
    {
        if (!isset($this->record)) {
            $this->record = $record;
        } else {
            throw new Exception('数据未读取', 301);
        }
        return $this;
    }

    /**
     * 获取解析结果
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
     */
    public function parseMember()
    {

        if (null == $this->memberModel) {
            $this->memberModel = new Member();
        }

        $options = [
            'fid' => 'member',
            'aid' => 'counter',
            'opid' => 'oper'
        ];
        if (isset($this->record['aid']) && $_SESSION['sid'] == $this->record['aid']) {
            $options['aid'] = 'member';
            $options['fid'] = 'counter';
        }

        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->record)) {
                $this->record[$value] = $this->memberModel->getMemberCacheById($this->record[$key], 'dname');
            }
            $this->record[$value] = !empty($this->record[$value]) ? $this->record[$value] : '';

        }

        return $this;
    }

    /**
     * 转换金额
     */
    public function parseMoney()
    {
        $options = ['dmoney', 'lmoney'];
        foreach ($options as $money) {
            $this->record[$money] = strval(sprintf($this->record[$money] / 100, 2));
        }
        //收入支出
        if (isset($this->record['daction'])) {
            $this->record['dmoney'] = $this->record['daction'] == 0 ? $this->record['dmoney'] : ("-" . $this->record['dmoney']);
            unset($this->record['daction']);
        }
        return $this;
    }

    /**
     * 转换交易渠道
     */
    public function parseChannel()
    {
        $channel_list = C('order_channel');
        if (array_key_exists($this->record['order_channel'], $channel_list)) {
            $this->record['order_channel'] = $channel_list[$this->record['order_channel']];
        }
        return $this;
    }

    /**
     * 转换交易内容
     */
    public function parseTradeContent()
    {
        if (isset($this->record['p_name'], $this->record['tnum'])) {
            $this->record['body'] = $this->record['p_name'] . ' ' . $this->record['tnum'] . '张';
            unset($this->record['p_name'], $this->record['tnum']);
        }
        return $this;
    }

    /**
     * 转换支付方式
     */
    public function parsePayType()
    {
        if (isset($this->record['ptype'])) {
            $ptype = $this->record['ptype'];

            $ptype_list = C('pay_type');
            if (array_key_exists($ptype, $ptype_list)) {
                $this->record['ptype'] = $ptype_list[$ptype][1];
                $p_acc = $ptype_list[$ptype][0];
                $acc_type_list = C('account_type');
                if (array_key_exists($p_acc, $acc_type_list)) {
                    $this->record['taccount'] = $acc_type_list[$p_acc];
                }
            }
        }
        return $this;
    }

    /**
     * 转换交易类型
     */
    public function parseTradeType()
    {
        if (isset($this->record['dtype'])) {
            $dtype_list = array_column(C('item_category'), 1);
            $cat_list = array_column(C('item_category'), 0);
            if (array_key_exists($this->record['dtype'], $dtype_list)) {
                $this->record['item'] = $cat_list[$this->record['dtype']];
                $this->record['dtype'] = $dtype_list[$this->record['dtype']];
                //交易记录对应分类
                $item_list = C('trade_item');
                if (array_key_exists($this->record['item'], $item_list)) {
                    $this->record['dtype'] = $item_list[$this->record['item']] . '-' . $this->record['dtype'];
                    $this->record['item'] = $this->record['dtype'];
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
            $this->record['payee_type'] = C('payee_type')[$this->record['payee_type']];
        }
        return $this;
    }
}