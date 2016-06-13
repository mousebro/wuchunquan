<?php

namespace Model\Product;

use Library\Cache\Cache;
use Library\Model;
use Library\Exception;
use Model\Order\OrderTools;

class AnnualCard extends Model
{

    const ANNUAL_CARD_TABLE         = 'pft_annual_card';            //卡片信息表
    const CARD_CONFIG_TABLE         = 'pft_annual_card_conf';       //年卡激活配置表
    const CARD_PRIVILEGE_TABLE      = 'pft_annual_card_privilege';  //年卡景区特权表
    const PRODUCT_TABLE             = 'uu_products';                //产品信息表
    const TICKET_TABLE              = 'uu_jq_ticket';               //门票信息表
    const LAND_TABLE                = 'uu_land';                    //景区表
    const SALE_LIST_TABLE           = 'pft_product_sale_list';      //一级转分销表

    public function __construct($parent_tid = 0)
    {
        parent::__construct();
        $this->parent_tid = $parent_tid;
        $this->cacheKey   = "crd:{$_SESSION['memberID']}";
        $this->cache      = Cache::getInstance('redis');
    }

    /**
     * 根据字段获取年卡信息
     *
     * @param  [type] $identify [description]
     * @param  string $field [description]
     *
     * @return [type]           [description]
     */
    public function getAnnualCard($identify, $field = 'id')
    {

        return $this->table(self::ANNUAL_CARD_TABLE)->where([$field => $identify])->find();

    }

    /**
     * 获取年卡配置
     * @param  [type] $tid [description]
     * @return [type]      [description]
     */
    public function getAnnualCardConfig($tid) {

        return $this->table(self::CARD_CONFIG_TABLE)->where(['tid' => $tid])->find();
   
    }

    /**
     * 获取指定产品的关联年卡
     *
     * @return [type] [description]
     */
    public function getAnnualCards($sid, $pid, $options = [], $action = 'select') {

        $where = [
            'sid' => $sid,
            'pid' => $pid,
        ];

        if (isset($options['status'])) {
            $where['status'] = $options['status'];
        }

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'id,virtual_no,card_no,physics_no,update_time';

        if ($action == 'select') {

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->field($field)->limit($limit)->select();

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
            return $this->table(self::PRODUCT_TABLE)->where($where)->field($field)->limit($limit)->select();
        } else {
            return $this->table(self::PRODUCT_TABLE)->where($where)->count();
        }
        
    }

    /**
     * 生成年卡
     *
     * @return [type] [description]
     */
    public function createAnnualCard($num, $sid, $pid)
    {
        $insert_data = $return = [];

        while (1) {
            $virtual_no = $this->_createVirtualNo();

            if ( ! $this->getAnnualCard($virtual_no, 'virtual_no')) {
                $insert_data[] = ['sid' => $sid, 'pid' => $pid, 'virtual_no' => $virtual_no, 'status' => 3];
            }

            $return[] = $virtual_no;

            if (count($insert_data) == $num) {
                break;
            }
        }

        if ( ! $this->table(self::ANNUAL_CARD_TABLE)->addAll($insert_data)) {
            return false;
        }

        return $return;
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
        $mix    = $string . $number;

        $virtual_no  = '';
        $head        = str_shuffle($string)[0];
        $second_part = substr(str_shuffle($string), 0, 3);
        $third_part  = substr(str_shuffle($number), 0, 3);
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
     * 获取年卡库存
     * @param  [type] $sid  [description]
     * @param  string $type 虚拟卡 OR 物理卡
     *
     * @return [type]       [description]
     */
    public function getAnnualCardStorage($sid, $pid, $type = 'virtual') {
        if ($type == 'virtual') {
            $where = [
                'sid'     => $sid,
                'card_no' => '',
                'status'  => 3,
            ];
        } else {
            $where = [
                'sid'    => $sid,
                'card_no'   => array('neq', ''),
                'status' => 3,
            ];
        }

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();
    }

    /**
     * 激活会员卡
     * @param  [type] $card_id  [description]
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function activateAnnualCard($card_id, $memberid) {

        $data = [
            'id'            => $card_id,
            'memberid'      => $memberid,
            'status'        => 1,
            'update_time'   => time()
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->save($data);
    }

    /**
     * 禁用会员卡
     * @param  [type] $card_id [description]
     * @return [type]          [description]
     */
    public function forbiddenAnnualCard($card_id) {
        $data = [
            'id'            => $card_id,
            'status'        => 2,
            'update_time'   => time()
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->save($data);
    }

    /**
     * 获取年卡会员列表
     * @param  [type] $sid     [description]
     * @param  [type] $options [description]
     * @return [type]          [description]
     */
    public function getMemberList($sid, $options = [], $action = 'select') {
        $where = [
            'sid'       => $sid,
            'memberid'  => ['gt', 0],
            'status'   => (int)$options['status']
        ];

        if ($options['identify']) {
            $identify = $options['identify'];
            $where['_string'] = "card_no='{$identify}' or virtual_no='{$identify}'";
        }

        $limit = ($options['page'] - 1) * $options['page_size'] . ',' . $options['page_size'];

        $field = 'id,memberid,activate_source,pid';

        if ($action == 'select') {

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->field($field)->limit($limit)->select();

        } else {

            return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();

        }
    }

    /**
     * 获取会员详细信息
     * @param  [type] $sid      [description]
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function getMemberDetail($sid, $memberid) {
        $where = [
            'sid'       => $sid,
            'memberid'  => $memberid,
        ];

        $field = 'memberid,card_no,virtual_no,physics_no,status';

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->field($field)->select();
    }

    /**
     * 年卡消费合法性检测
     * @return [type] [description]
     */
    public function consumeCheck($memberid, $sid, $tid) {
        $config = $this->getAnnualCardConfig($tid);

        $OrderModel = new OrderTools();

        //限制消费次数
        $use_check = false;
        switch ($config['use_limit']) {
            case 0 :
                $use_check = true;
                break;

            case 1 :
                $options = [];

            case 2:
                $options = [
                    'begin_time'    => date('Y-m-d') . ' 00:00:00',
                    'end_time'      => date('Y-m-d') . ' 23:59:59'
                ];

            case 3:
                $options = [
                    'begin_time'    => date('Y-m-01') . ' 00:00:00',
                    'end_time'      => date('Y-m-t')  . ' 23:59:59'
                ];

            case 4:
                $count = $OrderModel->countOrderForTicket($tid, $memberid, $sid, $options);
                if ($count < $config['limit_count']) {
                    $use_check = true;
                }
                break;
            default:
                break;
        }

        if (!$use_check) {
            return false;
        }

    }

    /**
     * 可添加到年卡特权的产品(自供应 + 转分销一级)
     * @return [type] [description]
     */
    public function getLands($sid, $keyword) {
        $where = [
            'fid'    => $sid, 
            'status' => 0
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

    public function getTickets($sid, $aid, $lid) {

        $where = [
            't.landid'      => $lid,
            'p.p_status'    => 0,
            'p.apply_limit' => 1
        ];

        $tickets = $this->table(self::TICKET_TABLE)
            ->join('t left join uu_products p on p.id=t.pid')
            ->where($where)
            ->field('t.id,t.title,t.pid,t.apply_did')
            ->select();

        return $tickets ?: [];
    }
    

     /**
     * 保存年卡激活配置信息
     *
     * @param array $data
     *
     * @return mixed
     */
    public function saveCardConfig($crdConf, $crdPriv)
    {
        $this->startTrans();
        $ret1 = $this->saveCrdConf($crdConf);
        $ret2 = $this->saveCrdPriv($crdPriv);
        if ($ret1 && $ret2) {
            $this->commit();

            return true;
        } else {
            $this->rollback();

            return false;
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

        $result = $this->table(self::CARD_CONFIG_TABLE)->add($data);
        $this->log_sql();

        return $result;
    }


    /**
     * 保存年卡景区特权信息
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function saveCrdPriv(array $data)
    {
        $result = $this->table(self::CARD_PRIVILEGE_TABLE)->addAll($data);
        $this->log_sql();

        return $result;
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

    public function checkPriv($arr_list)
    {
//        $arr_list = json_decode($json, true);
        if ( ! is_array($arr_list)) {
            throw new Exception("年卡特权数据出错");
        }
        $limit_key_list = ['aid', 'tid', 'use_limit', 'limit_count'];
        foreach ($arr_list as $arr) {
            foreach ($arr as $key => $val) {
                if ( ! in_array($key, $limit_key_list) || ! is_numeric($val)) {
                    echo $key, $val;

                    return false;
                }
            }
        }

        return $arr_list;
    }

    public function log_sql()
    {
        if (ENV != 'production') {
            $sql   = $this->getLastSql();
            $error = $this->getDbError();
            $sql .= $error ? $error : '';

            \pft_log('annual_card/sql', 'sql#' . $sql . 'err#' . $error);

        }

    }
}