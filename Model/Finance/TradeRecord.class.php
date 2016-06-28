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
    private $recomposer;

    /**
     * @param $records
     * @param $extInfo
     * @param $prod_name
     * @param $payAcc
     *
     * @return array
     */
    private function _recomposeExcelData(
        $records,
        $extInfo,
        $prod_name,
        $payAcc,
        $fid,
        $partner_id,
        $account_types,
        $online_pay_info
    ) {
        $data = [];
        $recomposer = $this->_getRecomposer();
        foreach ($records as $record) {
            $ordernum = $record['orderid'];

            if ($ordernum && count($extInfo) && array_key_exists($ordernum, $extInfo)) {
                $record = array_merge($record, $extInfo[ $ordernum ]);

                $tid = $extInfo[ $ordernum ]['tid'];
                if (!empty($prod_name[ $tid ])) {
                    $record['p_name'] = $prod_name[ $tid ];
                }
            }

            if ($ordernum && count($payAcc) && array_key_exists($ordernum, $payAcc)) {
                $record = array_merge($record, $payAcc[ $ordernum ]);
            }
            if (isset($online_pay_info) && is_array($online_pay_info) && array_key_exists($ordernum,
                    $online_pay_info)
            ) {
                $record = array_merge($record, $online_pay_info[ $ordernum ]);
            };
            if (isset($account_types) && is_array($account_types) && $account_types[ $record['trade_no'] ]['fid'] != $record['fid'] && $account_types[ $record['trade_no'] ]['ptype'] == $record['ptype']) {
                $record['partner_acc_type'] = $account_types[ $record['trade_no'] ]['partner_acc_type'];
            }
            $data[] = $recomposer->setRecord($record, $fid, $partner_id)
                ->recomposeTradeType('|')
                ->recomposeMemberExcel()
                ->recomposePayType()
                ->recomposeChannel()
                ->recomposeTradeContent()
                ->recomposeMoney()
                ->recompseExcelMoney()
                ->excelWrap(['payer_acc', 'payee_acc', 'outcome', 'income', 'lmoney', 'trade_no', 'orderid'])
                ->getRecord();
        }

        return $data;
    }

    /**
     * 获取交易记录转换实例
     *
     * @return TradeRecordRecomposer
     */
    private function _getRecomposer()
    {
        if (is_null($this->recomposer)) {
            $this->recomposer = new TradeRecordRecomposer();
        }

        return $this->recomposer;
    }

    /**
     * 获取交易记录详情
     *
     * @param $trade_id
     * @param $fid
     * @param $partner_id
     *
     * @return array|bool
     * @throws \Library\Exception
     */
    public function getDetails($trade_id, $fid, $partner_id)
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
            'tr.account_type as member_acc_type',                      //交易账户类型
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

        $result = $this->_getRecomposer()
            ->setRecord($record, $fid, $partner_id)
            ->recomposeTradeType()
            ->recomposeTradeContent()
            ->recomposeMemberDetail()
            ->recomposePayType()
            ->recomposeChannel()
            ->recomposeMoney()
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
    public function getExList($map, $fid, $partner_id)
    {
        $order = 'rectime asc';
        $records = $this->getTradeRecord($map, $order);

        $extInfo = $prod_name = $payAcc = $account_types = $online_pay_info = [];

        if (!$records || !is_array($records)) {
            return false;
        } else {
            if (is_array($records) && count($records)) {
                list($account_types, $online_pay_info, $extInfo, $prod_name) = $this->getExpandInfo($records, true);
            }
        }


        //整合数据
        $data = $this->_recomposeExcelData($records, $extInfo, $prod_name, $payAcc, $fid, $partner_id, $account_types,
            $online_pay_info);

        return $data;
    }

    public function getTradeRecord($map, $order, $limit = null, $page = null)
    {
        $table = "{$this->_trade_record_table}";

        $field = [
            'id as trade_id',
            'fid',          //交易商户
            'aid',          //对方商户
            //'opid',         //操作人
            'rectime',      //交易时间
            'dtype',        //交易分类
            'orderid',      //交易号
            'dmoney',       //本次交易金额
            'lmoney',       //账户余额
            'ptype',        //支付方式
            'daction',      //0-收入 1-支出
            'account_type as member_acc_type',   //收款方账号
            'trade_no',     //支付流水号
            'memo',         //备注
        ];
        $records = $this->table($table)->field($field)->where($map)->order($order);

        if (isset($limit, $page)) {
            $records = $records->limit($limit)->page($page)->select();
        } else {
            $records = $records->select();
        }

        return $records;
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
            "subject as body",
        ];
        $field = implode(',', $field);
        $result = $this->table($table)->where($where)->getField($field, true);

        return $result;
    }

    /**
     *  获取交易记录列表
     *
     * @param array $map        查询条件
     * @param int   $page       当前页
     * @param int   $limit      单页记录数
     * @param int   $fid        管理员选择的商户id
     * @param int   $partner_id 管理员选择的对方商户id
     *
     * @return array
     * @throws \Library\Exception
     */
    public function getList($map, $page, $limit, $fid, $partner_id)
    {
        // 1 从交易记录表中获取基本交易信息
        $order = 'rectime desc';
        $records = $this->getTradeRecord($map, $order, $limit, $page);

        //2 获取其他交易信息
        $data = [];
        if (is_array($records) && count($records)) {
            list(, $online_pay_acc) = $this->getExpandInfo($records);
            $recomposer = $this->_getRecomposer();

            foreach ($records as $record) {
                $this->integrateTradeAccount($record, $online_pay_acc);
                //$this->integratePartnerAccount($record, $account_types);
                $record['partner_acc_type'] = '';
                $data[] = $recomposer->setRecord($record, $fid, $partner_id)
                    ->recomposeMemberInfo()
                    ->recomposeTradeType()
                    ->recomposeMoney()
                    ->getRecord();
            }
        }
        $total = $this->getTradeRecordCount($map);
        $return = [
            'total'      => $total,
            'page'       => $page + 1,
            'total_page' => ceil($total / $limit),
            'limit'      => $limit,
            'list'       => $data,
        ];

        return $return;

    }

    /**
     * @param array $map 计算记录条数
     *
     * @return int
     */
    public function getTradeRecordCount($map)
    {
        $table = $this->_trade_record_table;

        return $this->table($table)->where($map)->count();
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

            if (strlen($keywords) == 11) {
                $where['_complex']['mobile'] = ':mobile';
                $bind[':mobile'] = $keywords;
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
            'income'  => $income,
            'outcome' => $outcome,
        ];

        return $return;
    }

    /**
     * 根据外部交易流水号，查询交易双方的id和交易账号类型
     *
     * @param $trade_no
     *
     * @return mixed
     */
    public function getPartnerAccountType($trade_no)
    {
        $where = [
            'trade_no' => ['in', $trade_no],
        ];

        return $this->table($this->_trade_record_table)->where($where)->getField("trade_no,fid,aid,account_type as partner_account_type,ptype");
    }

    /**
     * @param $records
     *
     * @return array
     */
    private function getExpandInfo($records, $getProdInfo = false)
    {
        //默认值
        $account_types = $online_pay_acc = $extInfo = $prod_name = [];

        //$trade_no = array_filter(array_column($records, 'trade_no')); //外部交易流水号
        //if (is_array($trade_no)) {
        //    $account_types = $this->getPartnerAccountType($trade_no); //根据外部交易流水查询交易账户类型
        //}

        $orderIds = array_unique(array_filter(array_column($records, 'orderid'))); //交易号或订单号
        if (is_array($orderIds)) {
            $online_pay_acc = $this->getPayerAccount($orderIds);
        }
        if ($getProdInfo) {
            if (is_array($orderIds)) {
                $extInfo = $this->getExtendInfo($orderIds);
                if (count($extInfo)) {
                    $tid = array_unique(array_filter(array_column($extInfo, 'tid')));
                    $prod_name = $this->getProdNameByTid($tid);
                }

            }

            return array($account_types, $online_pay_acc, $extInfo, $prod_name);
        } else {
            return array($account_types, $online_pay_acc); //根据交易号查询交易账号
        }

    }

    ///**
    // * @param $record
    // * @param $account_types
    // */
    //private function integratePartnerAccount(&$record, $account_types=)
    //{
    //    //if (is_array($account_types)
    //    //    && isset($account_types[ $record['trade_no'] ]['fid'])
    //    //    && $account_types[ $record['trade_no'] ]['fid'] != $record['fid']
    //    //    && $account_types[ $record['trade_no'] ]['ptype'] == $record['ptype']
    //    //) {
    //    //    $record['partner_acc_type'] = $account_types[ $record['trade_no'] ]['partner_acc_type'];
    //    //} else {
    //    //    $record['partner_acc_type'] = '';
    //    //}
    //    return $record['partner_acc_type'] = '';
    //}

    /**
     * @param $record
     * @param $online_pay_acc
     */
    private function integrateTradeAccount(&$record, $online_pay_acc)
    {
        if (is_array($online_pay_acc) && array_key_exists($record['orderid'], $online_pay_acc)
        ) {
            $record = array_merge($record, $online_pay_acc[ $record['orderid'] ]);
        }
    }
}

