<?php
/**
 * User: Fang
 * Time: 9:55 2016/5/16
 */

namespace Model\Finance;


use Library\Model;
use Model\Member\Member;

class TradeRecord extends Model
{
    private $trade_record_table = 'pft_member_journal';
    private $trade_item_table   = 'pft_trade_item';
    private $product_table      = 'uu_products';
    private $order_table        = 'uu_ss_order';
    private $ticket_table       = 'uu_jq_ticket';

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

    //交易明细分类
    public static function getTradeTypes()
    {
        return array(
            0  => '购买产品',
            1  => '修改/取消订单',
            3  => '充值/扣款',
            4  => '供应商授信余额',
            5  => '产品利润',
            6  => '提现冻结',
            7  => '电子凭证费',
            8  => '短信息费',
            9  => '银行交易手续费',
            10 => '凭证费',
            11 => '供应商信用额度变化',
            12 => '取消提现',
            13 => '拒绝提现',
            14 => '退款手续费',
            15 => '押金',
            16 => '充值返现',
            17 => '撤销/撤改订单',
            18 => '转账',
            19 => '佣金发放',
            20 => '佣金提现',
            21 => '获得佣金',
            22 => '平台费',
            23 => '出售产品',
        );
    }

    //获取交易渠道
    public function getOrderChannel()
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
    public function getPayType()
    {
        return array(
            0 => '帐号资金',
            1 => '支付宝',
            2 => '授信支付',
            3 => '供应商信用额度设置',
            4 => '财付通',
            5 => '银联',
            6 => '环迅',
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
        $join   = [
            "LEFT JOIN {$this->trade_item_table} AS ti ON tr.dtype=ti.dtype",
            "LEFT JOIN {$this->order_table} AS o ON tr.orderid=o.ordernum",
            "LEFT JOIN {$this->ticket_table} AS t ON t.id=o.tid",
            "LEFT JOIN {$this->product_table} AS p ON p.id=t.pid",
        ];
        $field  = [
            'tr.rectime AS time', //交易时间
            'ti.item',            //交易大类
            'ti.content',         //交易明细类别
            'tr.fid',             //主体商户
            'tr.aid',             //对方商户
            'tr.orderid',         //交易号
            'p.p_name',           //交易内容
            'tr.order_channel',   //交易渠道
            'tr.dmoney',          //本次交易金额（分）
            'tr.ptype',           //支付方式
            'tr.trade_no',        //交易流水
            'tr.memo',            //备注
            'tr.daction',         //收支
        ];
        $where  = ['tr.id' => $trade_id];
        $record = $this->table($table)->field($field)->where($where)->join($join)->find();

        //        var_dump($this->getLastSql());
        return $this->resolveRecord($record);
    }

    /**
     * 获取交易记录列表
     * @param      $memberId
     * @param      $map
     * @param      $page
     * @param      $limit
     *
     * @return array
     */
    public function getList($memberId, $map, $page, $limit, $excel=0, $super=0)
    {
        $table = "{$this->trade_record_table}";
        $where = [];

        //开始时间默认取当天
        if (isset($map['bdate'])) {
            if ($map['bdate']) {
                $bdate = mb_substr($map['bdate'], 0, 10) . " 00:00:00";
            }
            unset($map['bdate']);
        }

        if ( ! isset($bdate)) {
            $bdate = date('Y-m-d H:i:s', strtotime("today midnight"));
        }

        $where['rectime'][] = array('egt', $bdate);

        //结束时间默认取当前时间
        if (isset($map['edate'])) {
            if ($map['edate']) {
                $edate = mb_substr($map['edate'], 0, 10) . " 23:59:59";
            }
            unset($map['edate']);
        }

        if ( ! isset($edate)) {
            $edate = date('Y-m-d H:i:s', strtotime("now"));
        }

        $where['rectime'][] = array('elt', $edate);

        //是否超级管理员查看会员记录
        if(!$super){
            $where['fid'] = $memberId;
        }

        $where = array_merge($where, $map);

        $field   = [
            'id as trade_id',
            'fid',
            'rectime AS time',
            'dtype',
            'orderid',
            'dmoney',
            'daction',
            'lmoney',
            'aid AS counter',
            'ptype',
            'order_channel',
        ];
        $order   = 'id desc';
        $records = $this->table($table)->field($field)->where($where)->page($page)->limit($limit)->order($order)->select();
        //        var_dump($this->getLastSql());
        //        exit;
        $data = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $data[] = $this->resolveRecord($record);
            }
        }
        $total                  = $this->table($table)->field($field)->where($where)->count();
        $return = [
            'bdate'   => substr($bdate, 0, 10),
            'edate'   => substr($edate, 0, 10),
            'total'   => $total,
            'list'    => $data,
        ];
        if(!$super && !$excel){
            $income_map             = $outcome_map = $where;
            $income_map['daction']  = 0;
            $outcome_map['daction'] = 1;
            $income                 = $this->table($table)->where($income_map)->getField('sum(dmoney)');
            $outcome                = $this->table($table)->where($outcome_map)->getField('sum(dmoney)');
            if (is_numeric($income) && is_numeric($outcome)) {
                $balance = strval(round(($income - $outcome) / 100, 2));
                $income  = strval(round($income / 100, 2));
                $outcome = strval(round($outcome / 100, 2));
            } else {
                return false;
            }
            $return['sum'] = [
                'balance' => $balance,
                'income'  => $income,
                'outcome' => $outcome,
            ];
        }

        return $return;

    }

    /**
     * 转化为可读数据
     *
     * @param array $record
     *
     * @return array
     */
    protected function resolveRecord(array $record)
    {
        if (isset($record['dmoney'])) {
            $record['dmoney'] = strval(sprintf($record['dmoney'] / 100, 2));
        }
        if (isset($record['lmoney'])) {
            $record['lmoney'] = sprintf($record['lmoney'] / 100, 2);
        }
        //查询会员名称
        if (isset($record['fid']) && isset($record['aid'])) {
            $memberModel = new Member();
            if ($record['fid']) {
                $record['member'] = $memberModel->getMemberCacheById($record['fid'], 'dname');
            }
            if ($record['aid']) {
                $record['counter'] = $memberModel->getMemberCacheById($record['aid'], 'dname');
            }
        }
        //交易记录对应分类
        if (isset($record['content']) && isset($record['item'])) {
            $item_list = self::getTradeItems();
            if (array_key_exists($record['item'], $item_list)) {
                $record['item'] = $item_list[$record['item']] . '-' . $record['content'];
            }
            unset($record['content']);
        }

        if (isset($record['order_channel'])) {
            $channel_list = self::getOrderChannel();
            if (array_key_exists($record['order_channel'], $channel_list)) {
                $record['order_channel'] = $channel_list[$record['order_channel']];
            }
        }

        if (isset($record['ptype'])) {
            $ptype_list = self::getPayType();
            if (array_key_exists($record['ptype'], $ptype_list)) {
                $record['ptype'] = $ptype_list[$record['ptype']];
            }
        }

        if (isset($record['daction'])) {
            $record['dmoney'] = $record['daction'] == 0 ? $record['dmoney'] : ("-" . $record['dmoney']);
            unset($record['daction']);
        }

        return $record;
    }


    public static function getBalanceCached()
    {
        $cache = Cache::getInstance('redis');

    }

    /**
     * 从缓存里面获取会员的数据
     *
     * @author Guangpeng Chen
     *
     * @param int    $id    会员ID
     * @param string $field 需要的字段
     *
     * @return bool|mixed
     */
    public function getMemberCacheById($id, $field)
    {
        /** @var $cache \Library\Cache\CacheRedis; */
        $cache = Cache::getInstance('redis');
        $name  = "member:$id";
        $data  = $cache->hget($name, '', $field);
        if ( ! $data) {
            $data = $this->table(self::__MEMBER_TABLE__)->where("id=$id")->getField($field);
            $cache->hset($name, '', [$field => $data]);
        }

        return $data;
    }
}