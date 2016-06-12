<?php
/**
 * User: Fang
 * Time: 9:55 2016/5/16
 */

namespace Model\Finance;

use Controller\Finance\TradeRecordParser;
use Library\Exception;
use Library\Model;
use Model\Member\Member;

class TradeRecord extends Model
{
    private $_order_table = 'uu_ss_order';
    private $_product_table = 'uu_products';
    private $_trade_record_table = 'pft_member_journal';
    private $_ticket_table = "uu_jq_ticket";
    private $parser;

    /**
     * 获取交易记录转换实例
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
            "LEFT JOIN {$this->_product_table} AS p ON p.id = t.pid"
        ];

        $field = [
            'tr.rectime',         //交易时间
            'tr.dtype',           //交易类型
            'tr.fid',             //主体商户
            'tr.aid',             //对方商户
            'tr.orderid',         //交易号
            'tr.dmoney',          //本次交易金额（分）
            'tr.ptype',           //支付方式
            'tr.trade_no',        //交易流水
            'tr.memo',            //备注
            'tr.daction',         //收支
            'tr.payee_type',      //收款账户类型
            'o.ordermode as order_channel',        //交易渠道
            'o.tnum',
            'p.p_name',

        ];
        $where = ['tr.id' => $trade_id];
        $record = $this->table($table)->field($field)->where($where)->join($join)->find();
        //记录查询语句
        $this->logSql();
        if (is_array($record)) {
            return $this->_getParser()
                ->setRecord($record)
                ->parseTradeType()
                ->parseTradeContent()
                ->parseMember()
                ->parseMoney()
                ->parsePayType()
                ->parseChannel()
                ->parsePayee()
                ->getRecord();
        } else {
            return false;
        }

    }

    /**
     * 获取excel数据
     *
     * @param   array $map 查询条件
     *
     * @return array
     */
    public function getExList($map)
    {
        $table = "{$this->_trade_record_table}";

        $field = [
            'fid',//交易商户
            'aid',//对方商户
            'opid',//操作人
            'rectime',//交易时间
            'dtype',//交易分类
            'orderid',//交易号
            'dmoney',
            'lmoney',
            'ptype',
            'daction',
            'payee_type',//收款方账号
            'trade_no',//支付流水号
            'memo',
        ];
        $order = 'id asc';

        $records = $this->table($table)->field($field)->where($map)->order($order)->select();
        $this->logSql();

        if (!$records || !is_array($records)) {
            return false;
        } else {
            $orderid = array_filter(array_column($records, 'orderid'));
            $extInfo = $this->getExtendInfo($orderid);
            if (is_array($extInfo)) {
                $tid = array_unique(array_column($extInfo, 'tid'));
                $prod_name = $this->getProdNameByTid($tid);
            }
        }

        $data = [];

        $parser = $this->_getParser();

        foreach ($records as $record) {

            $ordernum = $record['orderid'];

            if ($ordernum && array_key_exists($ordernum, $extInfo)) {
                $record['order_channel'] = $extInfo[$ordernum]['ordermode'];
                $tid = $extInfo[$ordernum]['tid'];
                if (isset($prod_name) && array_key_exists($tid, $prod_name)) {
                    $record['body'] = $prod_name[$tid] . ' ' . $extInfo[$ordernum]['tnum'] . '张';
                }
            }

            $record['order_channel'] = isset($record['order_channel']) ? $record['order_channel'] : '平台';

            $record['body'] = isset($record['body']) ? $record['body'] : '';
            $data[] = $parser->setRecord($record)
                ->parseTradeType()
                ->parseMember()
                ->parseMoney()
                ->parsePayType()
                ->parseChannel()
                ->parsePayee()
                ->getRecord();
            //if($record['orderid']) {
            //    print_r($data);
            //    exit;
            //}
        }

        return $data;
    }

    /**
     * 获取产品名称
     *
     * @param $orderId
     *
     * @return mixed
     */
    public function getExtendInfo($orderId)
    {
        if (!is_array($orderId)) {
            $orderId = [$orderId];
        }

        $table = "{$this->_order_table}";

        $field = [
            "ordernum",
            "ordermode",
            "tnum",
            "tid",
        ];
        $field = implode(',', $field);
        $where = ['ordernum' => ['in', $orderId]];
        $orderInfo = $this->table($table)->where($where)->getField($field, true);
        $this->logSql();

        return $orderInfo;
    }

    /**
     * 获取交易记录列表
     *
     * @param $map
     * @param $page
     * @param $limit
     *
     * @return array
     */
    public function getList($map, $page, $limit)
    {
        $table = "{$this->_trade_record_table}";

        $field = [
            'id as trade_id',
            'fid',
            'rectime',
            'dtype',
            'orderid',
            'dmoney',
            'daction',
            'lmoney',
            'ptype',
            'memo',
        ];

        $order = 'id desc';
        $records = $this->table($table)->field($field)->where($map)->page($page)->limit($limit)->order($order)->select();
        //记录查询语句
        $this->logSql();
        $data = [];
        $parser = $this->_getParser();
        if (is_array($records)) {
            foreach ($records as $record) {
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
     * @param   string $srch 查询关键字
     * @param   int $limit 返回记录条数
     *
     * @return mixed
     */
    public function getMember($srch, $limit = 10)
    {
        //如果输入的是少于4个字符的英文字符串，不查询
        if (preg_match("/^[a-zA-Z\s]+$/", $srch) && strlen($srch) < 4) {
            return false;
        }

        $where['_complex'] = [
            'dname' => ['like', ':dname'],
        ];
        $bind[':dname'] = '%' . $srch . '%';

        if (strlen($srch) >= 4) {
            $where['_complex']['account'] = ['like', ':account'];
            $bind[':account'] = $srch . '%';
        }

        if (is_numeric($srch)) {
            $where['_complex']['id'] = ['like', ':id'];
            $bind[':id'] = $srch . '%';
        }
        if (count($bind) > 1) {
            $where['_complex']['_logic'] = 'or';
        }
        $field = ['id as fid', 'account', 'dname'];
        $return = $this->table('pft_member')
            ->bind($bind)
            ->where($where)
            ->field($field)
            ->limit($limit)
            ->select();
        $this->logSql();

        return $return;
    }

    /**
     * 根据票类id获取产品名称
     *
     * @param $tid
     *
     * @return mixed
     */
    public function getProdNameByTid($tid)
    {
        if (!is_array($tid)) {
            $tid = [$tid];
        }
        $table = "{$this->_product_table} AS p";
        $join = "{$this->_ticket_table} AS t ON p.id=t.pid";
        $where = ['t.id' => ['in', $tid]];
        $result = $this->table($table)->where($where)->join($join)->getField('t.id AS tid,p.p_name', true);
        $this->logSql();

        return $result;
    }

    /**
     *  获取统计记录
     *
     * @param $map
     *
     * @return array
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
        $this->logSql();
        $outcome = $this->table($table)->where($outcome_map)->getField('sum(dmoney)');
        $this->logSql();
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

    /**
     * 在非生产环境记录所执行的sql语句
     */
    private function logSql()
    {
        if (ENV == 'DEVELOP') {
            $prefix = __CLASS__ ? strtolower(__CLASS__) . '/' : '';
            $action = debug_backtrace()['function'] ?: '';
            \pft_log($prefix . 'query', $action . "#" . $this->getLastSql());
        }
    }

}