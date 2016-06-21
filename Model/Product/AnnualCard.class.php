<?php

namespace Model\Product;

use Library\Cache\Cache;
use Library\Model;
use Library\Exception;
use Model\Order\OrderTools;
use Model\Product\Ticket;

class AnnualCard extends Model
{

    const ANNUAL_CARD_TABLE = 'pft_annual_card';            //卡片信息表
    const CARD_CONFIG_TABLE = 'pft_annual_card_conf';       //年卡激活配置表
    const CARD_PRIVILEGE_TABLE = 'pft_annual_card_privilege';  //年卡景区特权表
    const CARD_ORDER_TABLE = 'pft_annual_card_order';       //年卡订单记录表

    const PRODUCT_TABLE = 'uu_products';                //产品信息表
    const TICKET_TABLE = 'uu_jq_ticket';               //门票信息表
    const LAND_TABLE = 'uu_land';                    //景区表
    const SALE_LIST_TABLE = 'pft_product_sale_list';      //一级转分销表

    public function __construct($parent_tid = 0, $sid = 0)
    {
        parent::__construct();
        $this->parent_tid = $parent_tid;
        $this->cacheKey = "crd:{$sid}";
        $this->cache = Cache::getInstance('redis');
    }

    /**
     * 根据字段获取年卡信息
     *
     * @param  [type] $identify 值
     * @param  string $field 字段
     *
     * @return [type]           [description]
     */
    public function getAnnualCard($identify, $field = 'id', $options = [], $action = 'find')
    {

        if (in_array($action, ['find,select'])) {
            return false;
        }

        if (isset($options['where'])) {
            $where = 1;
        } else {
            $where = [$field => $identify];
        }

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->$action($options);

    }

    

    /**
     * 获取年卡配置
     *
     * @param  [type] $tid [description]
     *
     * @return [type]      [description]
     */
    public function getAnnualCardConfig($tid)
    {

        return $this->table(self::CARD_CONFIG_TABLE)
            ->join('c left join uu_jq_ticket t on c.tid=t.id')
            ->where(['tid' => $tid])
            ->field('c.id,c.auto_act_day,c.srch_limit,t.delaytype,t.delaydays,t.order_start,t.order_end')
            ->find();
    }

    /**
     * 获取指定产品的关联年卡
     *
     * @return [type] [description]
     */
    public function getAnnualCards($sid, $pid, $options = [], $action = 'select')
    {

        $where = [
            'sid' => $sid,
            'pid' => $pid,
        ];

        if (isset($options['status'])) {
            $where['status'] = $options['status'];
        }

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'id,virtual_no,card_no,physics_no,create_time';

        if ($action == 'select') {

            return $this->table(self::ANNUAL_CARD_TABLE)
                ->where($where)
                ->field($field)
                ->limit($limit)
                ->select();

        } else {

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();

        }

    }

    /**
     * 获取指定供应商的年卡产品列表
     *
     * @param  [type] $sid 供应商id
     *
     * @return [type]      [description]
     */
    public function getAnnualCardProducts($sid, $options = [], $action = 'select')
    {

        $where = [
            'apply_did' => $sid,
            'p_type'    => 'I',
            'p_status'  => '0',
        ];

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'id,p_name';

        if ($action == 'select') {
            return $this->table(self::PRODUCT_TABLE)
                ->where($where)
                ->field($field)
                ->limit($limit)
                ->select();

        } else {
            return $this->table(self::PRODUCT_TABLE)->where($where)->count();
        }

    }

    /**
     * 生成年卡
     *
     * @return [type] [description]
     */
    public function createAnnualCard($list, $sid, $pid)
    {

        $insert_data = [];

        foreach ($list as $item) {
            $insert_data[] = [
                'sid'         => $sid,
                'pid'         => $pid,
                'virtual_no'  => $item['virtual_no'],
                'physics_no'  => $item['physics_no'],
                'card_no'     => $item['card_no'],
                'status'      => 3,
                'create_time' => time(),
            ];
        }

        if (!$this->table(self::ANNUAL_CARD_TABLE)->addAll($insert_data)) {
            return false;
        }

        return true;
    }

    /**
     * 生成虚拟卡号
     *
     * @return [type] [description]
     */
    private function _createVirtualNo()
    {
        $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $number = '0123456789';
        $mix = $string . $number;

        $virtual_no = '';
        $head = str_shuffle($string)[0];
        $second_part = substr(str_shuffle($string), 0, 3);
        $third_part = substr(str_shuffle($number), 0, 3);
        $virtual_no .= $head . $second_part . $third_part;
        $tail = array_sum(str_split($virtual_no));
        $virtual_no .= $virtual_no . $tail;

        return $virtual_no;
    }

    /**
     * 删除年卡
     *
     * @return [type] [description]
     */
    public function deleteAnnualCard($where)
    {
        //TODO:log it
        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->delete();

    }

    /**
     * 绑定物理卡
     *
     * @param  [type] $sid        [description]
     * @param  [type] $virtual_no [description]
     * @param  [type] $card_no    [description]
     * @param  [type] $physics_no [description]
     *
     * @return [type]             [description]
     */
    public function bindAnnualCard($sid, $virtual_no, $card_no, $physics_no)
    {
        $where = [
            'card_no'    => $card_no,
            'physics_no' => $physics_no,
            '_logic'     => 'OR',
        ];

        $find = $this->table(self::ANNUAL_CARD_TABLE)->where($where)->find();

        if ($find) {
            return false;
        }

        $update = [
            'card_no'    => $card_no,
            'physics_no' => $physics_no,
        ];

        $where = [
            'sid'        => $sid,
            'virtual_no' => $virtual_no,
            'card_no'    => '',
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->save($update);
    }


    /**
     * 获取年卡产品包含的特权产品
     *
     * @param  [type] $pid 产品pid
     *
     * @return [type]      [description]
     */
    public function getPrivileges($pid)
    {

        $ticket = (new Ticket())->getTicketInfoByPid($pid);

        //TODO:是否需要判断产品状态
        $where = [
            'pri.parent_tid' => $ticket['id'],
            'pri.status'     => 1,
        ];
        $result = $this->table(self::CARD_PRIVILEGE_TABLE)
            ->join('pri left join uu_jq_ticket t on pri.tid=t.id left join uu_land l on t.landid=l.id')
            ->where($where)
            ->field('pri.tid,pri.limit_count,t.title,t.pid,l.title as ltitle')
            ->select();

        return $result ?: [];

    }

    /**
     * 获取年卡库存
     *
     * @param  [type] $sid  供应商id
     * @param  string $type 虚拟卡 OR 物理卡
     *
     * @return [type]       [description]
     */
    public function getAnnualCardStorage($sid, $pid, $type = 'virtual')
    {
        if ($type == 'virtual') {
            $where = [
                'sid'     => $sid,
                'card_no' => '',
                'status'  => 3,
            ];
        } else {
            $where = [
                'sid'     => $sid,
                'card_no' => array('neq', ''),
                'status'  => 3,
            ];
        }

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();
    }

    /**
     * 激活会员卡
     *
     * @param  [type] $card_id  年卡id
     * @param  [type] $memberid 会员id
     *
     * @return [type]           [description]
     */
    public function activateAnnualCard($card_id, $memberid)
    {

        $data = [
            'id'          => $card_id,
            'memberid'    => $memberid,
            'status'      => 1,
            'update_time' => time(),
            'active_time' => time(),
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->save($data);
    }

    /**
     * 禁用会员卡
     *
     * @param  [type] $card_id 年卡id
     *
     * @return [type]          [description]
     */
    public function forbiddenAnnualCard($card_id)
    {
        $data = [
            'id'          => $card_id,
            'status'      => 2,
            'update_time' => time(),
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->save($data);
    }

    /**
     * 获取年卡会员列表
     *
     * @param  int   $sid     供应商id
     * @param  array $options 额外条件
     *
     * @return [type]          [description]
     */
    public function getMemberList($sid, $options = [], $action = 'select')
    {
        $where = [
            'sid'      => $sid,
            'memberid' => ['gt', 0],
            'status'   => (int)$options['status'],
        ];

        if ($options['identify']) {
            $identify = $options['identify'];
            $where['_string'] = "card_no='{$identify}' or virtual_no='{$identify}'";
        }

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'id,memberid,activate_source,pid';

        if ($action == 'select') {

            return $this->table(self::ANNUAL_CARD_TABLE)
                ->where($where)->field($field)
                ->limit($limit)
                ->select();

        } else {

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();

        }
    }

    /**
     * 获取会员详细信息
     *
     * @param  [type] $sid      [description]
     * @param  [type] $memberid [description]
     *
     * @return [type]           [description]
     */
    public function getMemberDetail($sid, $memberid)
    {
        $where = [
            'sid'      => $sid,
            'memberid' => $memberid,
        ];

        $field = 'memberid,card_no,virtual_no,physics_no,status';

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->field($field)->select();
    }

    /**
     * 年卡库存判断
     *
     * @param [type] $sid  [description]
     * @param [type] $pid  [description]
     * @param [type] $num  [description]
     * @param string $type [description]
     */
    public function storageCheck($sid, $pid, $num, $type = 'virtual')
    {

        $left = $this->getAnnualCardStorage($sid, $pid, $type);

        return $left >= $num ? true : $left;
    }

    /**
     * 年卡消费合法性检测
     *
     * @return [type] [description]
     */
    // public function consumeCheck($card_info) {
    //     $card_info = $this->getAnnualCard('555555', 'physics_no');  //调试代码

    //     extract($card_info);

    //     //这里逻辑有错
    //     $ticket = (new Ticket())->getTicketInfoByPid($pid);

    //     $ticket['id'] = 28460;  //调试代码
    //     $config = $this->getAnnualCardConfig($ticket['id']);

    //     //年卡有效期检测
    //     if (!$this->_periodOfValidityCheck($card_info, $config)) {
    //         return false; 
    //     }

    //     //次数限制检测
    //     if (!$this->_consumeTimesCheck($ticket['id'], $memberid, $sid, $config)) {
    //         return false;
    //     }

    //     return true;

    // }

    /**
     * 年卡有效期检测
     *
     * @param  [type] $card_info [description]
     * @param  [type] $config    [description]
     *
     * @return [type]            [description]
     */
    public function _periodOfValidityCheck($card_info, $config)
    {
        //是否处于未激活状态(待定)
        if ($card_info['status'] != 1) {
            return false;
        }

        switch ($config['delaytype']) {

            case 0: //激活后有效
                $valid_time = $card_info['active_time'] + $config['delaydays'] * 24 * 3600;

                if (time() > $valid_time) {
                    return false;
                }

                break;

            case 1: //售出后有效
                $valid_time = $card_info['sale_time'] + $config['delaydays'] * 24 * 3600;

                if (time() > $valid_time) {
                    return false;
                }

                break;

            case 2: //固定时间段有效
                $begin_time = strtotime($config['order_start']);
                $end_time = strtotime($config['order_end']);

                if (time() > $end_time || time() < $begin_time) {
                    return false;
                }

                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * 消费次数限制
     *
     * @param  [type] $tid      [description]
     * @param  [type] $memberid [description]
     * @param  [type] $sid      [description]
     *
     * @return [type]           [description]
     */
    private function _consumeTimesCheck($tid, $memberid, $sid, $config)
    {

        //限制消费次数
        if ($config['use_limit'] == 0) {
            return true;
        }

        $limit_count = explode(',', $config['limit_count']);

        $loop = [
            [
                //每日次数
                date('Y-m-d') . ' 00:00:00',
                date('Y-m-d') . ' 23:59:59',
            ],
            [
                //每月次数
                date('Y-m-01') . ' 00:00:00',
                date('Y-m-t') . ' 23:59:59',
            ],
            []  //总次数
        ];

        foreach ($loop as $key => $time) {
            $count = $this->_countTimeRangeOrder($tid, $memberid, $time);

            if ($count >= $limit_count[ $key ]) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取[当日,当月,总数]已使用
     * @param  [type] $tid      特权产品tid
     * @param  [type] $memberid 会员id
     *
     * @return [type]           [description]
     */

    public function getRemainTimes($sid, $tid, $memberid, $only_all = false) {


        $loop = [
            [
                //每日次数
                date('Y-m-d') . ' 00:00:00',
                date('Y-m-d') . ' 23:59:59',
            ],
            [
                //每月次数
                date('Y-m-01') . ' 00:00:00',
                date('Y-m-t') . ' 23:59:59',
            ],
            []  //总次数
        ];

        if ($only_all) {
            $all = $this->_countTimeRangeOrder($sid, $tid, $memberid, $loop[2]);
            return (int)$all;
        }

        $today = $this->_countTimeRangeOrder($sid, $tid, $memberid, $loop[0]);

        $month = $this->_countTimeRangeOrder($sid, $tid, $memberid, $loop[1]);

        return [(int)$today, (int)$month, (int)$all];

    }


    /**
     * 统计时间段内的订单总数
     *
     * @param  [type] $tid      [description]
     * @param  [type] $memberid [description]
     * @param  [type] $time     [description]
     *
     * @return [type]           [description]
     */
    private function _countTimeRangeOrder($sid, $tid, $memberid, $time)
    {

        $where = [
            'aid'      => $sid,
            'memberid' => $memberid,
            'tid'      => $tid,
            'status'   => 1,
        ];

        if ($time) {
            $where['create_time'] = ['between', array_map('strtotime', $time)];
        }

        return $this->table(self::CARD_ORDER_TABLE)->where($where)->sum('num');
    }

    /**
     * 可添加到年卡特权的产品(自供应 + 转分销一级)
     *
     * @return [type] [description]
     */
    public function getLands($sid, $keyword)
    {
        $where = [
            'fid'    => $sid,
            'status' => 0,
        ];

        $evolute = $this->table(self::SALE_LIST_TABLE)->where($where)->field('pids')->select();
        $evolute = $evolute ?: [];

        $pid_arr = [];
        foreach ($evolute as $item) {
            if ($item['pids'] && $item['pids'] != 'A') {
                $pid_arr = array_merge($pid_arr, explode(',', $item['pids']));
            }
        }

        $where = [
            'p.id'          => ['in', implode(',', $pid_arr)],
            'l.title'       => ['like', "%{$keyword}%"],
            'p.p_status'    => 0,
            'p.apply_limit' => 1,
        ];

        $lands = $this->table(self::LAND_TABLE)
            ->join('l left join uu_products p on l.id=p.contact_id')
            ->where($where)
            ->field('distinct(l.id),l.title,l.apply_did')
            ->select();

        return $lands ?: [];
    }

    /**
     * [可添加到年卡特权的门票(自供应 + 转分销一级)
     *
     * @param  [type] $sid [description]
     * @param  [type] $aid [description]
     * @param  [type] $lid [description]
     *
     * @return [type]      [description]
     */
    public function getTickets($sid, $aid, $lid)
    {

        $where = [
            't.landid'      => $lid,
            'p.p_status'    => 0,
            'p.apply_limit' => 1,
        ];

        $tickets = $this->table(self::TICKET_TABLE)
            ->join('t left join uu_products p on p.id=t.pid')
            ->where($where)
            ->field('t.id,t.title,t.pid,t.apply_did')
            ->select();

        return $tickets ?: [];
    }

    /**
     * 记录年卡订单
     *
     * @param  [type] $ordernum [description]
     * @param  [type] $tid      [description]
     * @param  [type] $memberid [description]
     *
     * @return [type]           [description]
     */
    public function annualOrderRecord($ordernum, $tid, $memberid, $aid, $num)
    {
        $data = [
            'ordernum'    => $ordernum,
            'tid'         => $tid,
            'memberid'    => $memberid,
            'aid'         => $aid,
            'num'         => $num,
            'create_time' => time(),
            'status'      => 1,
        ];

        return $this->table(self::CARD_ORDER_TABLE)->add($data);
    }

    /**
     * 取消年卡订单
     *
     * @param  [type] $ordernum [description]
     *
     * @return [type]           [description]
     */
    public function cancelOrder($ordernum)
    {
        $update = [
            'status' => 0,
        ];

        return $this->table(self::CARD_ORDER_TABLE)->where(['ordernum' => $ordernum])->save($update);
    }

    public function changeOrder($ordernum, $num)
    {
        $update = [
            'num' => $num,
        ];

        return $this->table(self::CARD_ORDER_TABLE)->where(['ordernum' => $ordernum])->save($update);
    }


    /**
     * 保存年卡激活配置信息
     *
     * @param array $data
     *
     * @return mixed
     */
    public function saveCardConfig($parent_tid, $crdConf, $crdPriv)
    {
        $ret1 = $this->saveCrdConf($crdConf);
        $ret2 = $this->setCardPrivilege($parent_tid, $crdPriv);
    }


    /**
     * 保存年卡激活配置信息
     *
     * @param array $data
     *
     * @return mixed
     */
    public function saveCrdConf($data)
    {

        $result = $this->table(self::CARD_CONFIG_TABLE)->add($data);

        return $result;
    }

    /**
     * 获取年卡激活配置信息
     * @param  [type] $tid [description]
     * @return [type]      [description]
     */
    public function getCrdConf($tid) {

        $return = [];

        $config = $this->table(self::CARD_CONFIG_TABLE)->where(['tid' => $tid])->find();

        $return['auto_act_day'] = $config['auto_act_day'];
        $return['srch_limit'] = $config['srch_limit'];
        $return['cert_limit'] = $config['cert_limit'];

        switch ($config['act_notice']) {
            case 0:
                $retrun['nts_tour'] = $return['nts_sup'] = 0;
                break;

            case 1:
                $return['nts_tour'] = 1;
                $retrun['nts_sup'] = 0;
                break;

            case 2:
                $return['nts_tour'] = 0;
                $retrun['nts_sup'] = 1;
                break;

            case 3:
                $return['nts_tour'] = 1;
                $retrun['nts_sup'] = 1;
                break;

            default:
                $retrun['nts_tour'] = $return['nts_sup'] = 0;
                break;
        }

        $return['pri'] = $this->table(self::CARD_PRIVILEGE_TABLE)
            ->join('p left join uu_jq_ticket t on p.tid=t.id left join uu_land l on l.id=t.landid')
            ->where(['p.parent_tid' => $tid, 'p.status' => 1])
            ->field('p.tid,p.use_limit,p.limit_count,l.title as ltitle,t.title')
            ->select();

        return $return;

    }


    public function getCache()
    {
        return $this->cache->get($this->cacheKey);
    }

    public function setCache($json)
    {
        return $this->cache->set($this->cacheKey, $json, '', 1800);
    }

    public function rmCache()
    {
        return $this->cache->rm($this->cacheKey);
    }
    //

    /**
     * 配置年卡特权景区（增/删/改门票对应特权景区）
     *
     * @param $parentId
     * @param $data
     *
     * @return bool
     */
    public function setCardPrivilege($parentId, $data)
    {
        $tid_before = $this->getPrivilegeInfo(['parent_id' => $parentId], 'tid');
        $tid_after = array_column($data, 'tid');

        //计算哪些特权景区被更新、删除或添加
        if ($tid_before === null) {
            $tid_add = $tid_after;
        } else {
            $tid_add = array_diff($tid_after, $tid_before);
            $tid_delete = array_diff($tid_before, $tid_after);
            $tid_update = array_diff($tid_after, $tid_add, $tid_delete);
        }
        $condition['parent_tid'] = $parentId;
        $condition_delete = $condition_add = $condition_update = $condition;
        foreach ($data as $setting) {
            if (in_array($setting['tid'], $tid_delete)) {
                $condition_delete['_complex'][] = [
                    'tid' => $setting['tid'],
                    'aid' => $setting['aid'],
                ];
                $data_delete[] = $setting;
            } elseif (in_array($setting['tid'], $tid_update)) {
                $condition_update[] = [
                    'tid' => $setting['tid'],
                    'aid' => $setting['aid'],
                ];
                $this->updateCardPrivilege($condition, $setting);
                $condition_update = $condition;
            } elseif (in_array($setting['tid'], $tid_add)) {
                $data_add[] = $setting;
            } else {
                continue;
            }
        }
        if (isset($data_delete)) {
            if ($condition_delete['_complex'] > 1) {
                $condition_delete['_complex'] += ['_logic' => 'or'];
            }
            $this->deleteCardPrivilege($condition_delete);
        }

        if (isset($data_add)) {
            $this->addCardPrivilege($data_add);
        }

        return true;
    }

    /**
     * 获取年卡产品特权信息
     *
     * @param $condition 特权条件
     * @param $field     获取的字段
     *
     * @return mixed
     */
    public function getPrivilegeInfo($condition, $field)
    {
        return $this->table(self::CARD_PRIVILEGE_TABLE)->where($condition)->getField($field, true);
    }

    /**
     * 保存年卡景区特权信息
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function addCardPrivilege(array $data)
    {
        $result = $this->table(self::CARD_PRIVILEGE_TABLE)->addAll($data);

        return $result;
    }


    /**
     * 删除年卡特权景区
     *
     * @param $condition
     *
     * @return bool
     */
    public function deleteCardPrivilege($condition)
    {
        return $this->table(self::CARD_PRIVILEGE_TABLE)->where($condition)->setField('status', 0);
    }

    /**
     * 更新年卡特权景区信息
     *
     * @param $condition
     * @param $data
     *
     * @return bool
     */
    public function updateCardPrivilege($condition, $data)
    {
        return $this->table(self::CARD_PRIVILEGE_TABLE)->where($condition)->data($data)->save();
    }

}