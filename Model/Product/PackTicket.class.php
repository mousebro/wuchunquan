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
    private $land_table             = 'uu_land';

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
    public $paymode_continer = array();//


    public function __construct($parent_tid=0, $initData=true)
    {
        parent::__construct('localhost', 'pft');
        $this->parent_tid = $parent_tid;
        if ($parent_tid>0 && $initData===true) {
            $this->childTicketData();
        }
        $this->cacheKey   = "pkg:{$_SESSION['memberID']}";
        /** @var $cache \Library\Cache\CacheRedis*/
        $this->cache = Cache::getInstance('redis');
    }

    public function getChildTickets()
    {
        if (!is_null($this->childTickets)) return $this->childTicketData();
        return $this->childTickets;
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
            ->field("$this->package_ticket_table.*,l.title as ltitle,l.imgpath,t.title as ttitle,p.id,t.ddays,t.pay,t.order_start,t.order_end,t.delaytype,t.delaydays,f.dhour")
            ->join("left join {$this->land_table} l ON l.id={$this->package_ticket_table}.lid")
            ->join("left join {$this->ticket_table} t ON t.pid={$this->package_ticket_table}.pid")
            ->join("left join {$this->ticket_ext_table} f ON f.pid={$this->package_ticket_table}.pid")
            ->join("left join {$this->products_table} p ON p.id={$this->package_ticket_table}.pid")
            ->where(['parent_tid'=>$this->parent_tid])->select();
        //echo $this->getLastSql();
        $this->childTickets = $data;
        $this->ChkSales();
        return $data;
    }

    /**
     * 检测门票是否在售
     *
     * @param string $pid_list
     * @return mixed
     */
    private function ChkSales($pid_list='')
    {
        // 获取关联子票
        if (!$pid_list && count($this->childTickets))
            foreach($this->childTickets as $child) $pid_list[] = $child['pid'];

        $count = $this->relationChildCount = count($pid_list);
        $data = $this->table($this->ticket_table .' t')
            ->field("l.title as ltitle,t.title as ttitle,p.id as pid,t.ddays,t.pay,t.order_start,t.order_end,t.delaytype,t.delaydays,f.dhour")
            ->join("left join {$this->land_table} l ON l.id=t.landid")
            ->join("left join {$this->ticket_ext_table} f ON f.pid=t.pid")
            ->join("left join {$this->products_table} p ON p.id=t.pid")
            ->where([
                't.pid'=>['in', $pid_list],
                'p.apply_limit'=>1,
                'p.p_status'=>['elt',6],
            ])
            ->limit($count)
            ->select();
        //var_dump($this->getDbError());
        //var_dump($this->getLastSql());
        //
        return $data;
    }

    public function childTempTicketsInfo(){
        //[{"lid":"8264","pid":"14624","aid":"3385","num":"1"},{"lid":"8264","pid":"21656","aid":"3385","num":"1"}]﻿
        $child_info = $this->getCache();
        if (empty($child_info)) return [];
        $child_info = json_decode($child_info, true);
        $pid_list   = [];
        foreach ($child_info as $info) {
            $pid_list[(int)$info['pid']] = $info;
        }
        $child_info = $this->ChkSales(array_keys($pid_list));
        $data = [];
        foreach ($child_info as $key => $row) {
            $child_info[$key]['lid'] = $pid_list[$row['pid']]['lid'];
            $child_info[$key]['tid'] = $pid_list[$row['pid']]['tid'];
            $child_info[$key]['num'] = $pid_list[$row['pid']]['num'];
            //$data[] = array(
            //    'ltitle' => $row['ltitle'],
            //    'ttitle' => $row['ttitle'],
            //    'pid'   => $row['pid'],
            //    'lid'   =>
            //    'tid'   => $pid_list[$row['pid']]['tid'],
            //    'num'   => $pid_list[$row['pid']]['num'],
            //);
        }
        $this->childTickets = $child_info;
        return $child_info;
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
        if($this->relationChildCount!=count($this->childTickets))
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
        $this->advance = $this->childTickets[0]['ddays'];// 提前购买天数
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

    /**
     * 根据套票的门票ID获取拥有的子票
     *
     * @return mixed
     */
    public function getTickets()
    {
        $tickets = $this->table($this->package_ticket_table)
            ->field('lid,pid,num,aid')
            ->where(['parent_tid'=>$this->parent_tid])
            ->select();
        return $tickets;
    }

    /**
     * 套票产品连带关系检测,若子票下架，主票会跟着下架
     *
     * @param $pid
     * @return bool
     */
    public function PackageCheckByPid($pid)
    {
        $tid_list = $this->table($this->package_ticket_table)
            ->where(['pid'=>$pid])
            ->getField('parent_tid', true);
        if ($tid_list) {
            $tid_list = array_unique($tid_list);//去重
            $pid_list = $this->table($this->ticket_table)
                ->where(['id'=>['in', $tid_list]])
                ->getField('pid', true);

            //$stateMsg['S:'.$row['id']] = array(
            //    'timer'=>date('Y年m月d日 H:i:s'),
            //    'message'=>'套票关联子票被下架或删除，系统自动下架该套票',
            //);
            //buildMess($stateMsg);

            return $this->table($this->products_table)
                ->where(['id'=>$pid_list])
                ->limit(count($pid_list))
                ->save(['apply_limit'=>2]);
        }
        return true;
    }

    /**
     * 子票提前预定时间更新后,同时更新套票的提前预定时间属性
     * @param  [type] $pid [description]
     * @param  [type] $day [description]
     * @return [type]      [description]
     */
    public function updateParentAdvanceAttr($pid, $day) {
        $parents = $this->table($this->package_ticket_table)
            ->where(['pid' => $pid])
            ->field('parent_tid')
            ->select();

        $parents_tid = [];
        foreach ($parents as $item) {
            $parents_tid[] = $item['parent_tid'];
        }

        $where = [
            't.id'          => ['in', implode(',', $parents_tid)],
            'p.p_status'    => ['in', [0,3,4,5]]
        ];

        $parent_ddays = $this->table($this->ticket_table)
            ->join('t left join '.$this->products_table.' p on t.pid=p.id')
            ->where($where)
            ->field('t.id,t.ddays')
            ->select();

        $to_update = [];
        foreach ($parent_ddays as $item) {
            if ($item['ddays'] < $day) {
                $to_update[] = $item['id'];
            }
        }

        if (count($to_update) == 0) return true;

        return $this->table($this->ticket_table)
            ->where(['id' => ['in', implode(',', $to_update)]])
            ->save(['ddays' => $day]);


    }
}