<?php
/**
 * Description: 交易记录查询模型
 * User: Fang
 * Time: 9:55 2016/5/16
 */

namespace Model\Finance;

use Library\Model;


class TradeRecord extends Model
{
    private $_order_table = 'uu_ss_order';
    private $_product_table = 'uu_products';
    private $_trade_record_table = 'pft_member_journal';
    private $_ticket_table = "uu_jq_ticket";
    private $_alipay_table = "pft_alipay_rec";
    private $parser;

    /**
     * 获取交易记录转换实例
     *
     * @return TradeRecordParser
     */
    private function _getParser()
    {
        if (is_null($this->parser)) {
            $this->parser = new TradeRecordParser();
        }
        return $this->parser;
    }

    /**
     * 获取交易记录详情
     *
     * @param $trade_id
     *
     * @return array|mixed
     */
    public function getDetails($trade_id)
    {
        $table = "{$this->_trade_record_table} AS tr";

        $join = [
            "LEFT JOIN {$this->_order_table} AS o ON o.ordernum = tr.orderid",
            "LEFT JOIN {$this->_ticket_table} AS t ON o.tid = t.id",
            "LEFT JOIN {$this->_product_table} AS p ON p.id = t.pid",
            "LEFT JOIN {$this->_alipay_table} AS a ON a.out_trade_no=o.ordernum",
        ];

        $field = [
            'tr.rectime',                           //交易时间
            'tr.dtype',                             //交易类型
            'tr.fid',                               //主体商户
            'tr.aid',                               //对方商户
            'tr.orderid',                           //交易号
            'tr.dmoney',                            //本次交易金额（分）
            'tr.ptype',                             //支付方式
            'tr.trade_no',                          //交易流水
            'tr.memo',                              //备注
            'tr.daction',                           //收支
            'tr.payee_type',                        //收款账户类型
            'o.ordermode as order_channel',         //交易渠道
            'p.p_name',                             //产品名称
            'a.buyer_email as payer_acc',           //支付方账号
            'a.seller_email as payee_acc',          //收款方账号
            'a.subject as body',                    //交易内容
        ];

        $where = ['tr.id' => $trade_id];

        $record = $this->table($table)->field($field)->where($where)->join($join)->find();

        if (!is_array($record) || !$record) {
            return false;
        }

        $result = $this->_getParser()
            ->setRecord($record)
            ->parseTradeType()
            ->parseTradeContent()
            ->parseMemberBasic()
            ->parseMoney()
            ->parsePayType()
            ->parseChannel()
            ->getRecord();
        return $result;

    }

    /**
     * 获取excel数据
     *
     * @param   array $map 查询条件
     *
     * @return  array
     */
    public function getExList($map)
    {
        $table = "{$this->_trade_record_table}";

        $field = [
            'fid',          //交易商户
            'aid',          //对方商户
            'opid',         //操作人
            'rectime',      //交易时间
            'dtype',        //交易分类
            'orderid',      //交易号
            'dmoney',       //本次交易金额
            'lmoney',       //账户余额
            'ptype',        //支付方式
            'daction',      //0-收入 1-支出
            'payee_type',   //收款方账号
            'trade_no',     //支付流水号
            'memo',         //备注
        ];
        $order = 'id asc';

        $records = $this->table($table)->field($field)->where($map)->order($order)->select();

        if (!$records || !is_array($records)) {
            return false;
        } else {
            $orderid = array_unique(array_filter(array_column($records, 'orderid'))); //提取交易号
            if (count($orderid)) {
                $extInfo = $this->getExtendInfo($orderid);
                $payAcc = $this->getPayerAccount($orderid);
                if (is_array($extInfo)) {
                    $tid = array_unique(array_column($extInfo, 'tid'));
                    $prod_name = $this->getProdNameByTid($tid);
                }
            }
        }
        //整合数据
        $data = [];

        $parser = $this->_getParser();

        foreach ($records as $record) {
            $ordernum = $record['orderid'];

            if (!$ordernum) {
                continue;
            }

            if (isset($extInfo) && array_key_exists($ordernum, $extInfo)) {
                $record = array_merge($record, $extInfo[$ordernum]);

                $tid = $extInfo[$ordernum]['tid'];
                if (!empty($prod_name[$tid])) {
                    $record['p_name'] = $prod_name[$tid];
                }
            }

            if (isset($payAcc) && $ordernum && array_key_exists($ordernum, $payAcc)) {
                $record = array_merge($record, $payAcc[$ordernum]);
            }

            $data[] = $parser->setRecord($record)
                ->parseTradeType('|')
                ->parseMember(false, true)
                ->parseMoney(true)
                ->parsePayType()
                ->parseChannel()
                ->parsePayee()
                ->parseTradeContent()
                ->getRecord();
        }

        return $data;
    }

    /**
     * 获取订单信息：辅助交易内容的获取
     *
     * @param   string $orderId 交易号/订单号
     *
     * @return  mixed
     */
    public function getExtendInfo($orderId)
    {
        if (!is_array($orderId)) {
            $orderId = [$orderId];
        }

        $table = "{$this->_order_table}";

        $field = [
            "ordernum",
            "ordermode as order_channel",
            "tnum",
            "tid",
        ];
        $field = implode(',', $field);
        $where = ['ordernum' => ['in', $orderId]];
        $orderInfo = $this->table($table)->where($where)->getField($field, true);

        return $orderInfo;
    }

    /**
     * 获取在线支付收款方/付款方账号
     *
     * @param string|array $orderId 订单号
     *
     * @return mixed
     */
    public function getPayerAccount($orderId)
    {
        if (empty($orderId)) {
            return [];
        }
        if (!is_array($orderId)) {
            $orderId = [$orderId];
        }
        $table = $this->_alipay_table;
        $where = ['out_trade_no' => ['in', $orderId]];
        $field = [
            "out_trade_no as orderid",
            "buyer_email as payer_acc",
            "seller_email as payee_acc",
            "subject as body"
        ];
        $field = implode(',', $field);
        $result = $this->table($table)->where($where)->getField($field, true);
        return $result;
    }

    /**
     * 获取交易记录列表
     *
     * @param   array $map   查询条件
     * @param   int   $page  当前页
     * @param   int   $limit 单页记录数
     *
     * @return  array
     */
    public function getList($map, $page, $limit)
    {
        $table = "{$this->_trade_record_table}";

        $field = [
            'orderid',
            'id as trade_id',
            'fid',
            'aid',
            'rectime',
            'dtype',
            'dmoney',
            'daction',
            'lmoney',
            'ptype',
            'memo',
            'payee_type',
        ];

        $field = join(',', $field);

        $order = 'id desc';

        $records = $this->table($table)
            ->where($map)
            ->page($page)
            ->limit($limit)
            ->order($order)
            ->field($field)
            ->select();

        if (is_array($records) && count($records)) {
            $orderIds = array_filter(array_column($records, 'orderid'));
            $online_pay_info = $this->getPayerAccount($orderIds);
        } else {
            return false;
        }

        $data = [];
        $parser = $this->_getParser();
        if (is_array($records)) {
            foreach ($records as $record) {
                $orderid = $record['orderid'];
                if (is_array($online_pay_info) && array_key_exists($orderid, $online_pay_info)) {
                    $record = array_merge($record, $online_pay_info[$orderid]);
                }
                $data[] = $parser->setRecord($record)
                    ->parseMember()
                    ->parseMoney()
                    ->parseTradeType()
                    ->parsePayType()
                    ->getRecord();
            }
        }

        $total = $this->table($table)->where($map)->count();

        $return = [
            'total' => $total,
            'page' => $page + 1,
            'total_page' => ceil($total / $limit),
            'limit' => $limit,
            'list' => $data,
        ];

        return $return;

    }

    /**
     * 获取会员列表
     *
     * @param   string $keywords 查询关键字
     * @param   int    $limit    返回记录条数
     *
     * @return mixed
     */
    public function getMember($keywords, $limit = 10)
    {
        //输入少于4个字符不查询
        if (preg_match("/^[a-zA-Z\s]+$/", $keywords) && strlen($keywords) < 4) {
            return false;
        }

        $where['_complex'] = [
            'dname' => ['like', ':dname'],
        ];

        $bind[':dname'] = '%' . $keywords . '%';

        if (is_numeric($keywords)) {
            $where['_complex']['id'] = ':id';
            $bind[':id'] = $keywords;

            if (strlen($keywords) >= 4) {
                $where['_complex']['account'] = ':account';
                $bind[':account'] = $keywords;
            }

        }
        if (count($bind) > 1) {
            $where['_complex']['_logic'] = 'or';
        }
        $where['dtype'] = ['in', '0,1,7'];
        $field = ['id as fid', 'account', 'dname'];
        $return = $this->table('pft_member')
            ->bind($bind)
            ->where($where)
            ->field($field)
            ->limit($limit)
            ->select();

        return $return;
    }

    /**
     * 根据票类id获取产品名称
     *
     * @param int $tid 门票id
     *
     * @return mixed
     */
    public function getProdNameByTid($tid)
    {
        if (!is_array($tid)) {
            $tid = [$tid];
        }
        $table = "{$this->_product_table} AS p";
        $join = "LEFT JOIN {$this->_ticket_table} AS t ON p.id=t.pid";
        $where = ['t.id' => ['in', $tid]];
        $result = $this->table($table)->where($where)->join($join)->getField('t.id AS tid,p.p_name', true);
        return $result;
    }

    /**
     *  获取统计记录
     *
     * @param   array $map 查询条件
     *
     * @return  array
     */
    public function getSummary($map)
    {
        $table = "{$this->_trade_record_table}";
        $where = [];

        $where = array_merge($where, $map);
        $income_map = $outcome_map = $where;

        $income_map['daction'] = 0;
        $outcome_map['daction'] = 1;

        $income = $this->table($table)->where($income_map)->getField('sum(dmoney)');
        $outcome = $this->table($table)->where($outcome_map)->getField('sum(dmoney)');

        $income = $income ? $income : 0;
        $outcome = $outcome ? $outcome : 0;
        $balance = strval(round(($income - $outcome) / 100, 2));
        $income = strval(round($income / 100, 2));
        $outcome = strval(round($outcome / 100, 2));
        $return = [
            'balance' => $balance,
            'income' => $income,
            'outcome' => $outcome,
        ];

        return $return;
    }
}