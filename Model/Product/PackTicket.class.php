<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/3-003
 * Time: 15:24
 * Copy From 宝椿
 */

namespace Model\Product;


use Library\Cache\Cache;
use Library\Model;

class PackTicket extends Model
{
    private $ticket_table           = 'uu_jq_ticket';
    private $ticket_ext_table       = 'uu_land_f';
    private $package_ticket_table   = 'pft_package_tickets';
    private $products_table         = 'uu_products';

    private $parent_tid             = 0;
    private $childTickets           = null;
    private $cacheKey              = '';
    private $cache  = null;
    public $paymode = 0;// 支付方式
    public $advance = 0;// 套票需要提前多少天购买
    public $section = 0;// 是否存在验证区间，1 存在
    public $relationChildCount = 0;// 关联子票数量
    public $usedate = array();// 套票有效使用日期数组
    public $message = array();// 提示/错误消息记录
    public $attribute = array();// 景区套票属性

    public function getChildTickets()
    {
        if (!is_null($this->childTickets)) return $this->childTicketData();
        return $this->childTickets;
    }

    public function __construct($parent_tid)
    {
        parent::__construct('localhost', 'pft');
        $this->parent_tid = $parent_tid;
        $this->cacheKey   = "pkg:{$_SESSION['memberID']}";
        /** @var $cache \Library\Cache\CacheRedis*/
        $this->cache = Cache::getInstance('redis');
        if ($parent_tid>0 ) $this->childTicketData($parent_tid);
    }

    /**
     * 检测套票数据是否合法
     *
     * @param $json
     * @return bool
     */
    public function checkPackData($json)
    {
        $arr_list = json_decode($json, true);
        //[{"lid":"8264","pid":"14624","aid":"3385","num":"1"},{"lid":"8264","pid":"21656","aid":"3385","num":"1"}]﻿
        $limit_key_list = ['lid','pid','aid','num'];
        foreach ($arr_list as $arr) {
            foreach ($arr as $key=>$val) {
                if(!in_array($key, $limit_key_list) || !is_numeric($val)) {
                    echo $key, $val;
                    return false;
                }
            }
        }
        return true;
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
    // 获取关联子票数据
    public function childTicketData(){
        $data = $this->table($this->package_ticket_table)
            ->field("$this->package_ticket_table.*,p.p_name,p.id,t.ddays,t.pay,t.order_start,t.order_end,t.delaytype,t.delaydays,f.dhour")
            ->join("left join {$this->ticket_table} t ON t.pid={$this->package_ticket_table}.pid")
            ->join("left join {$this->ticket_ext_table} f ON f.pid={$this->package_ticket_table}.pid")
            ->join("left join {$this->products_table} p ON p.id={$this->package_ticket_table}.pid")
            ->where(['parent_tid'=>$this->parent_tid])->select();
        //echo $this->getLastSql();
        $this->childTickets = $data;
        return $data;
    }

    public function childTempTicketsInfo(){
        //[{"lid":"8264","pid":"14624","aid":"3385","num":"1"},{"lid":"8264","pid":"21656","aid":"3385","num":"1"}]﻿
        $child_info = $this->getCache();
        if (empty($child_info)) return [];
        $child_info = json_decode($child_info, true);
        $pid_list = [];
        foreach ($child_info as $info) {
            $pid_list[] = $info['pid'];
        }
        $data = $this->table($this->ticket_table .' t')
            ->field("p.p_name,p.id,t.ddays,t.pay,t.order_start,t.order_end,t.delaytype,t.delaydays,f.dhour")
            ->join("left join {$this->ticket_ext_table} f ON f.pid=t.pid")
            ->join("left join {$this->products_table} p ON p.id=t.pid")
            ->where(['t.pid'=>['in', $pid_list]])->select();
        //echo $this->getLastSql();
        $this->childTickets = $data;
        return $data;
    }
    /**
     * 保存套票子票数据
     *
     * @author Guangpeng Chen
     * @param array $data
     * @return bool
     */
    public function savePackageTickets(Array $data)
    {
        return $this->table($this->package_ticket_table)->addAll($data);
    }

    /**
     * 删除子票
     *
     * @param $id
     * @return mixed
     */
    public function rmChildTicket($id)
    {
       return $this->table($this->package_ticket_table)->delete($id);
    }
    // 检查套票是否合法有效
    public function checkEffectivePack(){
        //var_dump($this->childTicketDatas);

        if($this->relationChildCount!=count($this->childTicketDatas))
        {
            $this->message[] = '子票非所有都可销售';
            return false;// 子票非所有都可销售
        }
        // 获取套票的有效时间段 如果开始时间大于结束时间，表示无效
        $useDate = $this->useDate();// 获取时间交集
        if($useDate['sDate']>$useDate['eDate'])
        {
            $this->message[] = '套票的有效时间有误';
            return false;
        }
        // 所有支付方式都必须一直
        // print_r(array_count_values($this->paymode_continer));
        if(count(array_count_values($this->paymode_continer))>1)
        {
            $this->message[] = '支付方式存在不一致';
            return false;
        }
        return true;
    }
    // 获取套票有效验证（游玩）日期区间
    public function useDate($playDate=''){
        //var_dump( $this->childTickets);
        $this->paymode = $this->childTickets[0]['pay'];// 支付方式
        $this->advance = $this->childTicketDatas[0]['ddays'];// 提前购买天数
        $orderDate = date('Y-m-d 00:00:00');// 下单时间

        // 获取最大提前天数
        if(is_null($this->childTickets)){
            return array(
                'sDate'   => '2015-04-02 00:00:00', // 有效开始时间
                'eDate'   => '2015-04-01 00:00:00', // 有效结束时间
                'oDate'   => '2015-04-01 00:00:00', // 开始下单时间
                'mDate'   => '2015-04-01 00:00:00', // 最大开始时间
                'section' => 0,
            );
        }
        foreach($this->childTickets as $key=>$data){
            if($data['dhour'] < date('H:i:s'))   $data['ddays'] += 1;
            if($data['ddays'] > $this->advance)  $this->advance = $data['ddays'];// 提前天数最大值
            $this->paymode = $data['pay'];// 支付方式
            $this->paymode_continer[] = $data['pay'];
        }

        // 初始第一个子票信息
        $iniDate = $this->effectiveDateSection($this->childTickets[0], $orderDate, $playDate);
        foreach($this->childTickets as $key=>$data){
            //$playDate = date('Y-m-d 00:00:00', time()+($data['ddays'] * 86400));// 使用时间

            $date_t = $this->effectiveDateSection($data, $orderDate, $playDate);
            if($key==0) $iniDate = $date_t;

            if($date_t['sDate']>$iniDate['sDate']) $iniDate['sDate'] = $date_t['sDate'];
            if($date_t['eDate']<$iniDate['eDate'] && $date_t['section']>0) $iniDate['eDate'] = $date_t['eDate'];
            if($date_t['section']==1){// 计算套票最大下单时间
                if($iniDate['section']==1){
                    $iniDate['oDate'] = $iniDate['oDate'] > $date_t['oDate'] ? $date_t['oDate']:$iniDate['oDate'];
                    $iniDate['mDate'] = $iniDate['mDate'] > $date_t['mDate'] ? $date_t['mDate']:$iniDate['mDate'];
                }else{
                    $iniDate['section'] = 1;
                    $iniDate['oDate'] = $date_t['oDate'];
                    $iniDate['mDate'] = $date_t['mDate'];
                }
            }
            // print_r($iniDate);
        }
        if($iniDate['section']==1){
            $iniDate['oDate'] = date('Y-m-d 23:59:59',(strtotime($iniDate['oDate']) - $this->advance * 86400));
        }
        $this->usedate = $iniDate;
        return $iniDate;
    }
    public function effectiveDateSection(Array $data, $orderDate='', $playDate=''){
        $arr = array();
        if($orderDate=='') $orderDate = date('Y-m-d 00:00:00');// 下单时间
        if($playDate=='') $playDate = date('Y-m-d 00:00:00');// 游玩时间
        if($data['order_start']!='' && $data['order_end']){
            // 提前天数
            if($data['ddays']>0) $date_tmp = date('Y-m-d 00:00:00',time()+ $data['ddays'] * 86400);

            $arr['sDate'] = ($date_tmp > $data['order_start']) ? $date_tmp:$data['order_start'];
            $arr['eDate'] = $data['order_end'];
            $arr['oDate'] = $data['order_end'];
            $arr['mDate'] = $data['order_end'];
            $arr['section'] = 1;
        }elseif($data['order_start']=='' && $data['order_end']){// 只有结束时间
            $arr['eDate'] = date('Y-m-d 00:00:00');
            $arr['eDate'] = $data['order_end'];
            $arr['oDate'] = $data['order_end'];
            $arr['mDate'] = $data['order_end'];
            $arr['section'] = 1;
        }else{
            if($data['delaytype']==0){// 游玩时间
                if($data['delaydays']==0) $data['delaydays'] = $this->advance;// 当天有效的获取最大提前的那一天
                $arr['sDate'] = $playDate;
                $arr['eDate'] = date('Y-m-d 23:59:59',strtotime($arr['sDate']) + $data['delaydays']*86400);
            }else{
                $arr['sDate'] = date('Y-m-d 00:00:00',strtotime($orderDate) + $data['ddays']*86400);
                $arr['eDate'] = date('Y-m-d 23:59:59',strtotime($arr['sDate']) + ($data['delaydays']-$data['ddays'])*86400);
            }
            $arr['section'] = 0;
            $arr['oDate'] = $arr['eDate'];
            $arr['mDate'] = $arr['mDate'];
        }
        return $arr;
    }
}