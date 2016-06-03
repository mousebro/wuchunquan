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
    private $order_table = 'uu_ss_order';

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
        $join   = "LEFT JOIN {$this->order_table} AS o ON o.ordernum = tr.orderid";
        $field  = [
            'tr.rectime',        //交易时间
            'tr.dtype',          //交易类型
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
        ];
        $where  = ['tr.id' => $trade_id];
        $record = $this->table($table)->field($field)->where($where)->join($join)->find();
        //记录查询语句
        $this->logSql('get_details');
        if ($record) {
            return $this->resolveRecord($record, 0);
        } else {
            return false;
        }

    }

    public function logSql($operation)
    {
        if (ENV == 'DEVELOP') {
            \pft_log('trade_record/query', $operation . "#" . $this->getLastSql());
        }

    }

    /**
     * 转化为可读数据
     *
     * @param array $record
     * @param int   $if_unset
     *
     * @return array
     */
    protected function resolveRecord(array $record, $if_unset = 1, $parse_lmoney = 1)
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
            //            $channel_list = self::getOrderChannels();
            $channel_list = C('order_channel');
            if (array_key_exists($record['order_channel'], $channel_list)) {
                $record['order_channel'] = $channel_list[$record['order_channel']];
            }
        }
        //交易渠道
        if (isset($record['ptype'])) {
            $ptype = $record['ptype'];
            //            $ptype_list = self::getPayTypes();
            $ptype_list = C('pay_type');
            if (array_key_exists($ptype, $ptype_list)) {
                $record['ptype'] = $ptype_list[$ptype][1];
                $p_acc           = $ptype_list[$ptype][0];
                $acc_type_list   = C('account_type');
                if (array_key_exists($p_acc, $acc_type_list)) {
                    $record['taccount'] = $acc_type_list[$p_acc];
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
            $dtype_list = array_column(C('item_category'), 1);
            $cat_list = array_column(C('item_category'), 0);
            if (array_key_exists($record['dtype'], $dtype_list)) {
                $record['item'] = $cat_list[$record['dtype']];
                $record['dtype'] = $dtype_list[$record['dtype']];
                //交易记录对应分类
                $item_list = C('trade_item');
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
        $table = "{$this->trade_record_table}";

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

        $order   = 'id desc';
        $records = $this->table($table)->field($field)->where($map)->page($page)->limit($limit)->order($order)->select();
        //记录查询语句
        $this->logSql('get_list');
        $data = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $data[] = $this->resolveRecord($record, 1, 0);
            }
        }

        $total = $this->table($table)->where($map)->count();

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
     * 获取会员列表
     *
     * @param $map
     *
     * @return array
     */
    public function getExList($map)
    {
        $table = "{$this->trade_record_table}";
        $where = [];
        $where = array_merge($where, $map);

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
     *  获取统计记录
     *
     * @param $map
     *
     * @return array
     */
    public function getSummary($map)
    {
        $table = "{$this->trade_record_table}";
        $where = [];

        $where                  = array_merge($where, $map);
        $income_map             = $outcome_map = $where;
        $income_map['daction']  = 0;
        $outcome_map['daction'] = 1;
        $income                 = $this->table($table)->where($income_map)->getField('sum(dmoney)');
        $this->logSql('get_income');
        $outcome = $this->table($table)->where($outcome_map)->getField('sum(dmoney)');
        $this->logSql('get_outcome');
        $income  = $income ? $income : 0;
        $outcome = $outcome ? $outcome : 0;
        $balance = strval(round(($income - $outcome) / 100, 2));
        $income  = strval(round($income / 100, 2));
        $outcome = strval(round($outcome / 100, 2));
        $return  = [
            'balance' => $balance,
            'income'  => $income,
            'outcome' => $outcome,
        ];

        return $return;
    }
}