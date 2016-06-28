<?php
/**
 * User: Fang
 * Date: 2016/6/24
 * Time: 17:18
 */

namespace Controller\Finance;


use Library\Exception;

trait TradeRecordParser
{

    /**
     * 解析支付类型
     *
     * @param        $memberId
     * @param string $fid       被查询用户id
     * @param string $partnerId 合作商家id
     * @param array  $map       查询条件
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    protected function _parseAccountType($memberId, $fid, $partnerId, &$map)
    {
        //接收参数
        $account_type = \safe_str(I('ptypes'));

        $pay_types = array_combine(array_keys(C('pay_type')), array_column(C('pay_type'), 2));
        $online_pay_type = array_keys($pay_types, 0);

        if (!is_numeric($account_type)) {
            return false;
        }

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
        //支付方式中包含在线支付
        if (!$fid) {
            if ($memberId != 1) {
                throw new Exception('无权限查看', 201);
            } else {
                return false;
            }
        }
        //参数初始化
        $self = 'fid';
        $other = 'aid';
        $logic = ['_logic' => 'or'];

        $fid_as_self = [$self => $fid];
        $fid_as_other = [$other => $fid];

        //选择了对方商户
        if ($partnerId) {
            $fid_as_other += [$self => $partnerId];
            $fid_as_self += [$other => $partnerId];
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

        return $account_type;
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
    protected function _parseTime()
    {
        //开始时间
        $btime = $this->_validateTime('btime', "today midnight", "00:00:00");
        //结束时间 - 默认为当前时间
        $etime = $this->_validateTime('etime', "today 23:59:59", "23:59:59");
        $interval = [$btime, $etime];

        return $interval;
    }

    /**
     * 解析交易大类
     *
     * @param         integer [items]     交易大类        见 C('trade_item'); 多值以'|'分隔
     * @param   array $map    查询条件
     *
     * @return array
     * @throws \Library\Exception
     */
    protected function _parseTradeCategory(&$map)
    {
        if (!isset($_REQUEST['items'])) {
            return false;
        }

        $items = \safe_str(I('items'));

        if ('' == $items) {
            return false;
        }

        $items = explode('|', $items);

        $subtype = [];
        $item_cat = array_column(C('item_category'), 0);
        foreach ($items as $item) {
            $subtype = array_merge($subtype, array_keys($item_cat, $item));
        }
        if ($subtype) {
            $map['dtype'] = ['in', $subtype];

            return $subtype;
        } else {
            return false;
        }

    }

    /**
     * 解析交易类型
     *
     * @param   string $subtype 交易类型
     * @param   array  $map     查询条件
     *
     * @return mixed
     * @throws \Library\Exception
     */
    protected function _parseTradeType($subtype, &$map)
    {
        if (!isset($_REQUEST['dtype'])) {
            return false;
        }

        $dtype = \safe_str(I('dtype'));
        if ($dtype == '') {
            return false;
        }
        $item_cat = C('item_category');
        if (is_numeric($dtype) && in_array($dtype, array_column($item_cat, 0))) {
            if (is_array($subtype) && !in_array($dtype, $subtype)) {
                throw new Exception('交易类型与交易类目不符', 205);
            }
            $map['dtype'] = $dtype;
        } else {
            throw new Exception('交易类型错误:', 206);
        }

        return false;
    }

    /**
     * @param   string $timeTag    时间字段
     * @param   string $defaultVal 绝对默认时间
     * @param   string $postfix    相对默认时间：未传入时分秒时的默认时间
     *
     * @return bool|mixed|string
     * @throws Exception
     */
    protected function _validateTime($timeTag, $defaultVal, $postfix)
    {
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