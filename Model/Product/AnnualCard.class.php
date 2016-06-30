<?php

namespace Model\Product;

use Library\Cache\Cache;
use Library\Model;
use Library\Exception;
use Model\Order\OrderTools;
use Model\Product\Ticket;
use Model\Member\Member;

class AnnualCard extends Model
{

    const ANNUAL_CARD_TABLE     = 'pft_annual_card';            //卡片信息表
    const CARD_CONFIG_TABLE     = 'pft_annual_card_conf';       //年卡激活配置表
    const CARD_PRIVILEGE_TABLE  = 'pft_annual_card_privilege';  //年卡景区特权表
    const CARD_ORDER_TABLE      = 'pft_annual_card_order';      //年卡订单记录表
    const CARD_MAPPING_TABLE    = 'pft_annual_card_mapping';      

    const PRODUCT_TABLE         = 'uu_products';                //产品信息表
    const TICKET_TABLE          = 'uu_jq_ticket';               //门票信息表
    const LAND_TABLE            = 'uu_land';                    //景区表
    const SALE_LIST_TABLE       = 'pft_product_sale_list';      //一级转分销表

    const VIRTUAL_LEN           = 8;    //虚拟卡号长度 

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
     * 是否需要填写身份证信息
     * @param  [type]  $sid [description]
     * @param  [type]  $tid [description]
     * @return boolean      [description]
     */
    public function isNeedID($sid, $tid) {

        $where = [
            'aid' => $sid,
            'tid' => $tid
        ];

        return $this->table(self::CARD_CONFIG_TABLE)->where($where)->getField('cert_limit');

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
            'p.apply_did' => $sid,
            'p.p_status'  => '0',
            'l.p_type'    => 'I',
        ];

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'p.id,p.p_name';

        $join_str = 'p left join uu_land l on p.contact_id=l.id';

        if ($action == 'select') {
            return $this->table(self::PRODUCT_TABLE)
                ->join($join_str)
                ->where($where)
                ->field($field)
                ->limit($limit)
                ->select();

        } else {
            return $this->table(self::PRODUCT_TABLE)->join($join_str)->where($where)->count();
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

        $physics_arr = [];
        foreach ($list as $item) {
            if ($item['physics_no']) {
                $physics_arr[] = $item['physics_no'];
            }
            
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

        if ($physics_arr) {
            $where = [
                'physics_no' => ['in', implode(',', $physics_arr)]
            ];

            $count = $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();

            if ($count > 0) {
                return false;
            }
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

        $virtual_no     = '';
        $head           = str_shuffle($string)[0];
        $second_part    = substr(str_shuffle($string), 0, 3);
        $third_part     = substr(str_shuffle($number), 0, 3);
        $virtual_no    .= $head . $second_part . $third_part;
        $tail           = array_sum(str_split($virtual_no));
        $virtual_no    .= $virtual_no . $tail;

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
     * 解析标识的类型(手机号|卡号|物理卡号|虚拟卡后)
     * @param  [type] $identify [description]
     * @return [type]           [description]
     */
    public function parseIdentifyType($identify, $type = 'physics') {

        if ($type == 'physics') {
            return 'physics_no';
        }

        if (ismobile($identify)) {
            return 'mobile';
        }

        if (strlen($identify) == self::VIRTUAL_LEN) {
            if (ctype_alpha($identify[0])) {
                return 'virtual_no';
            } 
        }

        if (ctype_digit($identify)) {
            return $type == 'physics' ? 'card_no' : 'physics_no';
        }
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
            ->field('pri.tid,pri.use_limit,t.title,t.pid,l.title as ltitle')
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
                'pid'     => $pid,
                'card_no' => '',
                'status'  => 3,
            ];
        } else {
            $where = [
                'sid'     => $sid,
                'pid'     => $pid,
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
    public function activateAnnualCard($virtual_no, $memberid)
    {
        $data = [
            'memberid'    => $memberid,
            'status'      => 1,
            'update_time' => time(),
            'active_time' => time(),
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->where(['virtual_no' => $virtual_no])->save($data);
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
     * 成为分销商
     * @param  [type] $sid      [description]
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function createRelationShip($sid, $memberid) {

        include '/var/www/html/new/d/class/MemberAccount.class.php';

        if (!isset($GLOBALS['le'])) {
            include_once("/var/www/html/new/conf/le.je");
            $le = new \go_sql();
            $le->connect();
            $GLOBALS['le'] = $le;
        }

        $MemberAccount = new \pft\Member\MemberAccount($GLOBALS['le']);
        $MemberAccount->createRelationship($sid, $memberid);

    }

    /**
     * [updateStatusForOrder description]
     * @return [type] [description]
     */
    public function updateStatusForOrder($type, $virtual_no) {
        if ($type == 'virtual') {
            //购买虚拟卡，直接激活
            $where['virtual_no'] = $virtual_no;
            $data = ['status' => 1];
        } else {
            $where['virtual_no'] = ['in', $virtual_no];
            $data = ['status' => 0];
        } 

        $data['sale_time'] = time();  
        
        $this->table(self::ANNUAL_CARD_TABLE)->where($where)->save($data);
    }

    public function orderMapping($ordernum, $virtual_no) {
        $data = [
            'ordernum'      => $ordernum,
            'virtual_no'    => $virtual_no
        ];

        $this->table(self::CARD_MAPPING_TABLE)->add($data);
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

        $field = 'id,sid,virtual_no,card_no,sale_time,memberid,activate_source,pid,status';

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
        if ($sid != 1) {
            $where['sid'] = $sid;
        }

        $where['memberid'] = $memberid;

        $field = 'id,sid,pid,memberid,card_no,virtual_no,physics_no,status,active_time,sale_time';

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

    public function checkStatusForSale(array $virtual_arr) {
        if (count($virtual_arr) < 1) {
            return false;
        }

        $where = [
            'virtual_no' => ['in', implode(',', $virtual_arr)],
            'status'    => 3
        ];

        $count = $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();

        return $count == count($virtual_arr);
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

    public function getPeriodOfValidity($sid, $tid, $sale_time, $active_time) {

        $config = $this->getAnnualCardConfig($tid);

        $format = 'Y-m-d H:i:s';
        $day = 3600 * 24;


        switch ($config['delaytype']) {

            case 0 :
                return date($format, $active_time) . '~' . date($format, $active_time + $config['delaydays'] * $day);
                break;

            case 1 :
                return date($format, $sale_time) . '~' . date($format, $sale_time + $config['delaydays'] * $day);
                break;

            case 2:
                return $config['order_start'] . '~' . $config['order_end'];
                break;

            default:
                return 0;

        }
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
            'l.p_type'      => ['in', ['A', 'B']],
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
            't.pay'         => 1
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


    public function replaceAnnualCard($memberid, $virtual_no, $sid) {

        $res = $this->table(self::ANNUAL_CARD_TABLE)
            ->where(['sid' => $sid, 'memberid' => $memberid])
            ->save(['status' => 2]);

        if ($res) {
            $data = [
                'memberid'      => $memberid,
                'status'        => 1,
                'active_time'   => time(),
                'update_time'   => time()
            ];

            $where = [
                'sid'           => $sid,
                'virtual_no'    => $virtual_no
            ];

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->save($data);
        }


    }

    public function orderSuccess($ordernum) {

        $virtual_no=  $this->table(self::CARD_MAPPING_TABLE)->where(['ordernum' => $ordernum])->getField('virtual_no');

        if (!$virtual_no) {
            return [];
        }

        $where = [
            'virtual_no' => ['in', $virtual_no]
        ];

        $field = 'virtual_no,physics_no';

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->field($field)->select();

    }

    public function getCardName($pid_arr) {

        $where = [
            'id' => ['in', implode(',', $pid_arr)]
        ];

        $list = $this->table(self::PRODUCT_TABLE)->where($where)->field('id,p_name')->select();

        $return = [];
        foreach ($list as $item) {
            $return[$item['id']] = $item['p_name'];
        }

        return $return;
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

        if ($ret1 && $ret2) {
            return 'success';
        } else {
            return 'fail';
        }
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
        $where = [
            'tid' => $data['tid'],
            'aid' => $data['aid']
        ];

        $exist = $this->table(self::CARD_CONFIG_TABLE)->where($where)->getField('id');

        if ($exist) {
            $result = $this->table(self::CARD_CONFIG_TABLE)->where(['id' => $exist])->save($data);
        } else {
            $result = $this->table(self::CARD_CONFIG_TABLE)->add($data);
        }

        return $result !== false ? true : false;
    }

    /**
     * 获取年卡激活配置信息
     * @param  [type] $tid [description]
     * @return [type]      [description]
     */
    public function getCrdConf($tid) {

        $return = [];

        $config = $this->table(self::CARD_CONFIG_TABLE)->where(['tid' => $tid])->find();

        $return['auto_active_days'] = $config['auto_act_day'];
        $return['search_limit'] = $config['srch_limit'];
        $return['cert_limit'] = $config['cert_limit'];

        switch ($config['act_notice']) {
            case 0:
                $return['nts_tour'] = $return['nts_sup'] = 0;
                break;

            case 1:
                $return['nts_tour'] = 1;
                $return['nts_sup'] = 0;
                break;

            case 2:
                $return['nts_tour'] = 0;
                $return['nts_sup'] = 1;
                break;

            case 3:
                $return['nts_tour'] = 1;
                $return['nts_sup'] = 1;
                break;

            default:
                $return['nts_tour'] = $return['nts_sup'] = 0;
                break;
        }

        $return['priv'] = $this->table(self::CARD_PRIVILEGE_TABLE)
            ->join('p left join uu_jq_ticket t on p.tid=t.id left join uu_land l on l.id=t.landid')
            ->where(['p.parent_tid' => $tid, 'p.status' => 1])
            ->field('p.tid,p.aid,p.use_limit,l.title as ltitle,t.title')
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
        // return $this->cache->rm($this->cacheKey);
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

        //已记录在库的特权门票
        $exists_tids = $this->getPrivilegeInfo(['parent_tid' => $parentId], 'tid,id');
        $exists_tids = $exists_tids ?: [];
        $exists_tids = array_keys($exists_tids);

        //本次提交的特权门票
        $submit_tids = array_keys($data);

        $to_insert = array_diff($submit_tids, $exists_tids);

        $to_update = array_intersect($submit_tids, $exists_tids);

        $to_delete = array_diff($exists_tids, $submit_tids);

        //TODO:事务
        if ($to_delete) {
            $this->deleteCardPrivilege($parentId, $to_delete);
        }

        if ($to_update) {
            $this->updateCardPrivilege($parentId, $data, $to_update);
        }

        if ($to_insert) {
            $this->addCardPrivilege($parentId, $data, $to_insert);
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
    public function addCardPrivilege($parent_tid, $data, $to_insert)
    {
        $insert = [];
        foreach ($to_insert as $tid) {
            $insert[] = [
                'parent_tid'    => $parent_tid,
                'aid'           => $data[$tid]['aid'],
                'tid'           => $tid,
                'use_limit'     => $data[$tid]['use_limit'],
                'status'        => 1
            ];
        }   

        return $this->table(self::CARD_PRIVILEGE_TABLE)->addAll($insert);
    }


    /**
     * 删除年卡特权景区
     *
     * @param $condition
     *
     * @return bool
     */
    public function deleteCardPrivilege($parent_tid, $tid_arr)
    {
        $where = [
            'parent_tid'    => $parent_tid,
            'tid'            => ['in', implode(',', $tid_arr)],
            'status'        => 1
        ];

        return $this->table(self::CARD_PRIVILEGE_TABLE)->where($where)->setField('status', 0);
    }

    /**
     * 更新年卡特权景区信息
     *
     * @param $condition
     * @param $data
     *
     * @return bool
     */
    public function updateCardPrivilege($parent_tid, $data, $tid_arr)
    {
        foreach ($tid_arr as $tid) {

            $where = [
                'parent_tid' => $parent_tid,
                'tid'        => $tid,
            ];

            $update = [
                'use_limit'     => $data[$tid]['use_limit'],
                'status'        => 1
            ];

            $this->table(self::CARD_PRIVILEGE_TABLE)->where($where)->save($update);
        }
    }

    public function createDefaultParams() {
        $default = [
            'ddays'                 => 0,
            'v_time_limit'          => 0,
            'order_limit'           => '1,2,3,4,5,6,7',
            'refund_audit'          => 0,
            'refund_rule'           => 0,
            'cancel_notify_supplier'=> 0,
            'p_type'                => 'I',
            'confirm_sms'           => 0,
            'sendVoucher'           => 0,
            'pid'                   => 0,
            'reb_type'              => 1,
            'buy_limit_low'         => 1,
            'buy_limit_up'          => 0,
        ];

        return $default;
    }

}