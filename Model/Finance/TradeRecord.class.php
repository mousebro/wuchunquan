<?php
/**
 * User: Fang
 * Time: 9:55 2016/5/16
 */

namespace Model\Finance;


use Library\Model;
use Mockery\CountValidator\Exception;
use Model\Member\Member;

class TradeRecord extends Model
{
    private $trade_record_table = 'pft_member_journal';
    //    private $trade_item_table = 'pft_trade_item';
    private $product_table = 'uu_products';
    private $order_table = 'uu_ss_order';
    private $ticket_table = 'uu_jq_ticket';

    //交易大类
    public static function getTradeItems()
    {
        return array(
            1 => '平台运营',
            2 => '产品交易',
            3 => '账户操作',
            4 => '佣金利润',
        );
    }

    public static function getItemCat()
    {
        return array(
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
        );
    }

    //获取交易渠道
    public function getOrderChannels()
    {
        return array(
            0 => '默认',
            1 => '平台',
            2 => '微信',
            3 => '二级域名店铺',
            4 => '微商城',
            5 => '云票务',
            6 => '自助终端',
        );
    }

    //获取支付方式
    public static function getPayTypes()
    {
        return array(
            0 => [0,'平台账本'],
            1 => [1,'支付宝'],
            2 => [2,'授信支付'],
            3 => [2,'供应商信用额度设置'],
            4 => [3,'微信'],
            5 => [4,'银联'],
            6 => [5,'环迅'],
        );
    }

    public static function getAccTypes(){
       return array(
           '平台账户',
           '支付宝',
           '授信账户',
           '微信',
           '银联',
           '环迅',
       );
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
        $table  = "{$this->trade_record_table} AS tr";
        $field  = [
            'rectime',        //交易时间
            'dtype',          //交易类型
            'fid',             //主体商户
            'aid',             //对方商户
            'orderid',         //交易号
            'body',            //交易内容
            'order_channel',   //交易渠道
            'dmoney',          //本次交易金额（分）
            'ptype',           //支付方式
            'trade_no',        //交易流水
            'memo',            //备注
            'daction',         //收支
        ];
        $where  = ['id' => $trade_id];
        $record = $this->table($table)->field($field)->where($where)->find();
        //记录查询语句
        $this->logSql('get_details');
        return $this->resolveRecord($record, 0);
    }

    /**
     * 获取交易记录列表
     *
     * @param       $map
     * @param array $time
     * @param       $page
     * @param       $limit
     * @param int   $super
     *
     * @return array
     */
    public function getList($map, array $time, $page, $limit)
    {
        $table            = "{$this->trade_record_table}";
        $where            = [];
        $where['rectime'] = ['between', $time];

        $where = array_merge($where, $map);

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

        $where = array_merge($where, $map);

        $order   = 'id desc';
        $records = $this->table($table)->field($field)->where($where)->page($page)->limit($limit)->order($order)->select();
        //记录查询语句
        $this->logSql('get_list');
        $data = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $data[] = $this->resolveRecord($record,1,0);
            }
        }

        $total = $this->table($table)->where($where)->count();

        $return = [
            'btime' => $time[0],
            'etime' => $time[1],
            'total' => $total,
            'page'  => $page + 1,
            'total_page' => ceil($total / $limit),
            'limit' => $limit,
            'list'  => $data,
        ];

        return $return;

    }

    /**
     * excel导出数据
     *
     * @param       $map
     * @param array $time
     *
     * @return array
     */
    public function getExList($map, array $time)
    {
        $table            = "{$this->trade_record_table}";
        $where            = [];
        $where['rectime'] = ['between', $time];
        $where            = array_merge($where, $map);

        $field   = [
            'fid',
            'rectime',
            'orderid',
            'dtype',
            'ptype',
            'daction',
            'dmoney',
            'lmoney',
            'aid',
            'opid',
            //            'body',
            'memo',
        ];
        $order   = 'id asc';
        $records = $this->table($table)->field($field)->where($where)->order($order)->select();
        $this->logSql('get_excel');
        $data = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $data[] = $this->resolveRecord($record);
            }
        }

        return $data;
    }


    /**
     * 转化为可读数据
     *
     * @param array $record
     * @param int   $if_unset
     *
     * @return array
     */
    protected function resolveRecord(array $record, $if_unset = 1,$parse_lmoney = 1)
    {
        if (isset($record['dmoney'])) {
            $record['dmoney'] = strval(sprintf($record['dmoney'] / 100, 2));
        }
        if (isset($record['lmoney'])) {
            $record['lmoney'] = sprintf($record['lmoney'] / 100, 2);
        }
        //查询会员名称
        $memberModel = new Member();
        if (isset($record['fid'])) {
            if ($record['fid']) {
                $record['member'] = $memberModel->getMemberCacheById($record['fid'], 'dname');
            }
            $record['member'] = ! empty($record['member']) ? $record['member'] : '';
            if ($if_unset) {
                unset($record['fid']);
            }
        }
        if (isset($record['aid'])) {
            if ($record['aid']) {
                $record['counter'] = $memberModel->getMemberCacheById($record['aid'], 'dname');
            }
            $record['counter'] = ! empty($record['counter']) ? $record['counter'] : '';
            if ($if_unset) {
                unset($record['aid']);
            }
        }
        if (isset($record['opid'])) {
            if ($record['opid']) {
                $record['oper'] = $memberModel->getMemberCacheById($record['opid'], 'dname');
            }
            $record['oper'] = ! empty($record['oper']) ? $record['oper'] : '';
            if ($if_unset) {
                unset($record['opid']);
            }
        }

        //交易渠道
        if (isset($record['order_channel'])) {
            $channel_list = self::getOrderChannels();
            if (array_key_exists($record['order_channel'], $channel_list)) {
                $record['order_channel'] = $channel_list[$record['order_channel']];
            }
        }
        //交易渠道
        if (isset($record['ptype'])) {
            $ptype      = $record['ptype'];
            $ptype_list = self::getPayTypes();
            if (array_key_exists($ptype, $ptype_list)) {
                $record['ptype'] = $ptype_list[$ptype][1];
                $p_acc =$ptype_list[$ptype][0];
                $acc_type_list = self::getAccTypes();
                if(array_key_exists($p_acc, self::getAccTypes())){
                    $record['taccount'] =  $acc_type_list[$p_acc];
                }
            }
        }
        //区分账户余额与授信余额
        if (isset($record['lmoney']) && $parse_lmoney) {
            if (isset($ptype)) {
                if (in_array($ptype, [0, 1, 4, 5, 6])) {
                    $record['cre_money'] = '';
                    $record['acc_money'] = $record['lmoney'];
                } elseif (in_array($ptype, [2, 3])) {
                    $record['cre_money'] = $record['lmoney'];
                    $record['acc_money'] = '';
                }
            } else {
                throw new Exception('支付数据缺失', 301);
            }
            unset($record['lmoney']);
        }

        //交易类型
        if (isset($record['dtype'])) {
            $dtype_list = array_column(self::getItemCat(), 1);
            if (array_key_exists($record['dtype'], $dtype_list)) {
                $record['dtype'] = $dtype_list[$record['dtype']];
                //交易记录对应分类
                $item_list = self::getTradeItems();
                if (array_key_exists($record['item'], $item_list)) {
                    $record['item'] = $item_list[$record['item']] . '-' . $record['dtype'];
                }
            }

        }
        //收入支出
        if (isset($record['daction'])) {
            $record['dmoney'] = $record['daction'] == 0 ? $record['dmoney'] : ("-" . $record['dmoney']);
            unset($record['daction']);
        }

        return $record;
    }

    /**
     * 获取会员列表
     *
     * @param     $srch
     * @param int $limit
     *
     * @return mixed
     */
    public function getMember($srch, $limit = 10)
    {
        $where['dname'] = ['like', "%$srch%"];
        $field          = ['id as fid', 'account', 'dname'];
        $return         = $this->table('pft_member')->where($where)->field($field)->limit($limit)->select();
        $this->logSql('srch_mem');
        return $return;
    }

    /**
     * 获取统计记录
     *
     * @param $map
     * @param $time
     *
     * @return array
     */
    public function getSummary($map, $time){
        $table            = "{$this->trade_record_table}";
        $where            = [];
        $where['rectime'] = ['between', $time];

        $where = array_merge($where, $map);
        $income_map             = $outcome_map = $where;
        $income_map['daction']  = 0;
        $outcome_map['daction'] = 1;
        $income                 = $this->table($table)->where($income_map)->getField('sum(dmoney)');
        $this->logSql('get_income');
        $outcome                = $this->table($table)->where($outcome_map)->getField('sum(dmoney)');
        $this->logSql('get_outcome');
        $income                 = $income ? $income : 0;
        $outcome                = $outcome ? $outcome : 0;
        $balance                = strval(round(($income - $outcome) / 100, 2));
        $income                 = strval(round($income / 100, 2));
        $outcome                = strval(round($outcome / 100, 2));
        $return                 = [
            'balance' => $balance,
            'income'  => $income,
            'outcome' => $outcome,
        ];
        return $return;
    }

    public function logSql($operation){
        if(ENV == 'DEVELOP'){
            \pft_log('trade_record/query', $operation . "#" . $this->getLastSql());
        }

    }
}