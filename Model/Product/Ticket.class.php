<?php
/**
 * 门票信息模型
 */

namespace Model\Product;
use Library\Model;
use pft\Member\MemberAccount;

class Ticket extends Model {

    const __TICKET_TABLE__          = 'uu_jq_ticket';    //门票信息表
    const __TICKET_TABLE_EXT__      = 'uu_land_f';    //门票信息表
    const __PRODUCT_TABLE__         = 'uu_products';    //产品信息表
    const __LAND_TABLE__            = 'uu_land';   //景区信息表

    const __SALE_LIST_TABLE__       = 'pft_product_sale_list';    //一手供应商产品表
    const __EVOLUTE_TABLE__         = 'pft_p_apply_evolute';    //转分销产品表

    const __PRODUCT_PRICE_TABLE__   = 'uu_product_price';   //产品价格表

    const __ORDER_TABLE__           = 'uu_ss_order';
    const __ORDER_DETAIL_TABLE__    = 'uu_order_fx_details';

    private $ticket_filed = [
        'landid', 'title', 'tprice', 'reb', 'discount', 'delaydays', 'status', 'pay',
        'notes', 'ddays', 'getaddr', 'smslimit', 's_limit_up', 's_limit_low', 'buy_limit_up',
        'buy_limit_low', 'open_time', 'end_time', 'apply_did', 'pid', 'cancel_cost', 'reb_type',
        'order_start', 'max_order_days', 'Mdetails', 'Mpath', 'sourceT', 'cancel_auto_onMin',
        'delaytype', 'delaytime', 'order_end', 'order_limit', 'overdue_refund',
        'overdue_auto_cancel', 'overdue_auto_check', 'batch_check', 'batch_day_check',
        'batch_diff_identities', 'refund_audit', 'refund_rule', 'refund_early_time',
        'cancel_notify_supplier',
    ];
	/**
	 * 根据票类id获取票类信息
     * @author wengbin 
	 * @param  int $id 票类id
	 * @return array   
	 */
	public function getTicketInfoById($id) {
		return $this->table(self::__TICKET_TABLE__)
            ->field($this->ticket_filed)
            ->find($id);
	}

    /**
     * 获取门票扩展属性
     * @author Guangpeng Chen
     * @param int $tid 门票ID
     * @param string $field
     * @return mixed
     */
    public function getTicketExtInfoByTid($tid, $field="*") {
		return $this->table(self::__TICKET_TABLE_EXT__)
            ->field($field)
            ->where(['tid'=>':tid'])
            ->bind([':tid'=>$tid])
            ->find();
	}

    /**
     * 根据productid获取票类信息
     * @author wengbin 
     * @param  int $id product_id
     * @return array   
     */
    public function getTicketInfoByPid($pid) {

        return $this->table(self::__TICKET_TABLE__)
            ->field( $this->ticket_filed )
            ->where(array('pid' => $pid))->find();
    }
    /**
     * 获取产品类型
     * @param  int $pid productID
     * @return [type]      [description]
     */
    public function getProductType($pid) {
        return $this->table(self::__PRODUCT_TABLE__)
                    ->join('p left join uu_land l on p.contact_id=l.id')
                    ->where(array('p.id' => $pid))
                    ->getField('l.p_type');
    }

    public function getPackageInfoByTid($tid){
        $table = 'uu_jq_ticket AS t';
        $join = 'join uu_land AS l ON l.id=t.landid';
        $where = ['t.id' => $tid];
        $field = 'l.attribute';
        $jsonRes = $this->table($table)->join($join)->where($where)->field($field)->find();
        if($jsonRes){
            $result = json_decode($jsonRes);
        }else{
            $result=false;
        }
        return $result;
    }

    /**
     * 获取自供应可出售产品
     * @author wengbin 
     * @param  int      $memberid [description]
     * @param  array    额外筛选条件
     * @return array
     */
    public function getSaleProducts($memberid, $options = array()) {
        // $sale_list = $this->table(self::__SALE_LIST_TABLE__)->where(['fid' => $memberid, 'status' => 0])->select();

        // if (!$sale_list) return array();

        // $sale_pid_arr = $sale_aid_arr = array();
        // foreach ($sale_list as $item) {
        //     if ($memberid == $item['aid']) {
        //         $sale_pid_arr[$item['aid']] = array('A');
        //     } else {
        //         $sale_pid_arr[$item['aid']] = explode(',', $item['pids']);
        //     }
        //     $sale_aid_arr[] = $item['aid'];
        // }
        // var_dump($sale_pid_arr);die;
        $where = array(
            'p.p_status' => 0,
            'p.apply_limit' => 1,
            'l.status' => 1,
            'p.apply_did' => $memberid
        );

        if (isset($options['where'])) {
            $where = array_merge($where, $options['where']);
            unset($options['where']);
        }

        $data = $this->getProductsDetailInfo($where, $options);

        $result = array();
        if ($data) {
            foreach ($data as $item) {
            // $pid_arr = $sale_pid_arr[$item['apply_did']];
            // if (is_array($pid_arr) && ($pid_arr[0] == 'A' || in_array($item['pid'], $pid_arr))) {
                $item['apply_sid'] = $memberid;
                $item['sapply_sid'] = $item['apply_did'];
                $result[] = $item;
            // }
            }
        }
        
        return $result;
    }

    /**
     * 获取转分销可出售产品
     * @author wengbin 
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function getSaleDisProducts($memberid, $option1 = array(), $option2 = array()) {
        $where = array(
            'fid' => $memberid,
            // 'sid' => array('exp', ' <> sourceid'),
            'sourceid' => array('neq', $memberid),
            'status' => 0,
            'active' => 1
        );

        if ($option1) {
            $where = array_merge($where, $option1);
        }

        $sale_list = $this->table(self::__EVOLUTE_TABLE__)->where($where)->select();

        if (!$sale_list) return array();

        $pid_arr = $tmp = array();
        foreach ($sale_list as $item) {
            $pid_arr[] = $item['pid'];
            $tmp[$item['pid']] = $item; 
        }

        $where = array(
            'p.p_status' => 0,
            'p.apply_limit' => 1,
            'l.status' => 1,
            'p.id' => array('in', implode(',', $pid_arr))
        );

        if (isset($option2['where'])) {
            $where = array_merge($where, $option2['where']);
            unset($option2['where']);
        }

        $data = $this->getProductsDetailInfo($where, $option2);

        $result = array();
        if ($data) {
            foreach ($data as $item) {
                $item['apply_sid'] = $tmp[$item['id']]['sid'];
                $item['sapply_sid'] = $tmp[$item['id']]['sourceid'];
                $result[] = $item;
            }
        }
        
        return $result;
    }

    /**
     * 获取产品详细信息
     * @author wengbin 
     * @param  [type] $where [description]
     * @return [type]        [description]
     */
    protected function getProductsDetailInfo($where, $options = array()) {
        return $this->table(self::__PRODUCT_TABLE__)
            ->join('p left join '.self::__LAND_TABLE__.' l on p.contact_id=l.id 
                left join uu_jq_ticket t on p.id=t.pid')
            ->field('p.id,p.p_type,p.p_name,p.salerid,t.title,t.id as tid,t.landid,t.pid,l.title,l.area,l.address,l.px,l.apply_did,l.imgpath')
            ->where($where)
            ->select($options);
    }


    /**
     * 获取门票零售价
     * @author wengbin 
     * @param  [type] $pid   productID
     * @param  string $date 日期,2016-03-26
     * @return [type]       [description]
     */
    public function getRetailPrice($pid, $date = '') {
        $date = $date ?: date('Y-m-d', time());
        //日历模式
        $retail_price = $this->table(self::__PRODUCT_PRICE_TABLE__)
                    ->where(['pid' => $pid, 'start_date' => $date, 'ptype' => 1, 'status' => 0])
                    ->getField('l_price');

        if (!$retail_price) {
            //时间段模式
            $price_info = $this->table(self::__PRODUCT_PRICE_TABLE__)
                ->where(['pid' => $pid, 'end_date' => ['egt', $date], 'ptype' => 0, 'status' => 0])
                ->field('l_price,start_date,end_date')
                ->find();

            if (!$price_info) return false;
            
            $start_time = strtotime($price_info['start_date']);
            $end_time   = strtotime($price_info['end_date']);
            $cur_time   = strtotime($date);
            if ($start_time <= $cur_time && $end_time >= $cur_time) {
                $retail_price = $price_info['l_price'];
            } else {
                return false;
            }
        }

        return $retail_price / 100;
    }


    /**
     * 一次性获取多产品零售价
     * @author wengbin 
     * @param  [type] $pid_arr array(1,2)
     * @param  string $date    日期
     * @return [type]          array(1 => 0.01, 2 => 0.02)
     */
    public function getMuchRetailPrice($pid_arr, $date = '') {
        $date = $date ?: date('Y-m-d', time());
        $result = $find_pid = array();
        //日历模式
        $retail_price = $this->table(self::__PRODUCT_PRICE_TABLE__)
                    ->where(['pid' => array('in', implode(',', $pid_arr)), 'start_date' => $date, 'ptype' => 1, 'status' => 0])
                    ->field('pid,l_price')->select();
        
        if ($retail_price) {
            foreach ($retail_price as $item) {
                $find_pid[] = $item['pid'];
                $result[$item['pid']] = $item['l_price'] / 100;
            }
        }
        $pid_arr = array_diff($pid_arr, $find_pid);

        if ($pid_arr) {
            //时间段模式
            $price_info = $this->table(self::__PRODUCT_PRICE_TABLE__)
                ->where(['pid' => array('in', implode(',', $pid_arr)), 'end_date' => ['egt', $date], 'ptype' => 0, 'status' => 0])
                ->field('pid,l_price,min(start_date) as start_date,min(end_date) as end_date')
                ->group('pid')
                ->select();

            // echo $this->_sql();die;
            if ($price_info && is_array($price_info)) {
                foreach ($price_info as $item) {
                    $start_time = strtotime($item['start_date']);
                    $end_time   = strtotime($item['end_date']);
                    $cur_time   = strtotime($date);
                    if ($end_time >= $cur_time) {
                        $result[$item['pid']] = $item['l_price'] / 100;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 一次性获取多产品的实时库存
     * @author wengbin 
     * @param  [type] $pid_arr array(1,2)
     * @param  string $date    日期
     * @return [type]          array(1 => 2, 2 => 3)
     */
    public function getMuchStorage($pid_arr, $date = '') {
        $date = $date ?: date('Y-m-d', time());
        $result = $find_pid = array();
        //日历模式
        $storage = $this->table(self::__PRODUCT_PRICE_TABLE__)
                    ->where(['pid' => array('in', implode(',', $pid_arr)), 'start_date' => $date, 'ptype' => 1, 'status' => 0])
                    ->field('pid,storage')->select();
        
        if ($storage) {
            foreach ($storage as $item) {
                $find_pid[] = $item['pid'];
                $result[$item['pid']] = $item['storage'];
            }
        }
        $pid_arr = array_diff($pid_arr, $find_pid);

        if ($pid_arr) {
            //时间段模式
            $storage_info = $this->table(self::__PRODUCT_PRICE_TABLE__)
                ->where(['pid' => array('in', implode(',', $pid_arr)), 'end_date' => ['egt', $date], 'ptype' => 0, 'status' => 0])
                ->field('pid,storage,min(start_date) as start_date,min(end_date) as end_date')
                ->group('pid')
                ->select();
            if (!$storage_info) return false;

            foreach ($storage_info as $item) {
                $start_time = strtotime($item['start_date']);
                $end_time   = strtotime($item['end_date']);
                $cur_time   = strtotime($date);
                if ($end_time >= $cur_time) {
                    $result[$item['pid']] = $item['storage'];
                }
            }
        }

        //获取产品对应的tid
        $tids = $this->table(self::__TICKET_TABLE__)
                    ->where(array('pid' => array('in', implode(',', $pid_arr))))
                    ->field('id,pid')
                    ->select();
        $p_t_map = array();
        foreach ($tids as $item) {
            if ($result[$item['pid']] == -1) {
                continue;
            }
            $p_t_map[$item['id']] = $item['pid'];
        }
        //TODO:判断是否使用分销库存
        //获取指定日期已使用库存
        $use_storage = $this->getUseStorage(array_keys($p_t_map), $date);
        $p_storage_map = array();
        foreach ($use_storage as $item) {
            $p_storage_map[$p_t_map[$item['tid']]] = $item['tnum'];
        }

        foreach ($result as $pid => $item) {
            if (isset($p_storage_map[$pid])) {
                $result[$pid] = $item - $p_storage_map[$pid];
            }
        }

        return $result;
    }

    /**
     * 获取指定日期已使用库存
     * @param  [type] $tid_arr [description]
     * @param  string $date    [description]
     * @return [type]          [description]
     */
    public function getUseStorage($tid_arr, $date = '') {
        $date = $date ?: date('Y-m-d', time());

        $use_storage = $this->table(self::__ORDER_TABLE__)
            ->join(' s left join '.self::__ORDER_DETAIL_TABLE__.' fx on s.ordernum=fx.orderid')
            ->where(array(
                's.tid' => array('in', implode(',', $tid_arr)),
                's.begintime' => $date,
                'fx.pay_status' => array('lt', 2),
                's.status' => array('in', array(0,1,6))))
            ->field('tid,sum(s.tnum) as tnum')
            ->select();

        return $use_storage;
    }


    /**
     * 获取有设置零售价的最小/最大的日期
     * @param  [type] $pid [description]
     * @return [type]      [description]
     */
    public function getHasRetailPriceDate($pid, $type = 'min') {
        if ($type == 'min') {
            $date_type = 'start_date';
            $sort = 'asc';
        } else {
            $date_type = 'end_date';
            $sort = 'desc';
        }
        $daily_date = $this->table(self::__PRODUCT_PRICE_TABLE__)
                    ->where(['pid' => $pid, 'start_date' => array('egt', date('Y-m-d')), 'ptype' => 1, 'status' => 0])
                    ->order('start_date '.$sort)
                    ->getField($date_type);

        $period_date = $this->table(self::__PRODUCT_PRICE_TABLE__)
                    ->where(['pid' => $pid, 'end_date' => array('egt', date('Y-m-d')), 'ptype' => 0, 'status' => 0])
                    ->order('start_date '.$sort)
                    ->getField($date_type);

        if ($type == 'min') {
            if (strtotime($period_date) < strtotime(date('Y-m-d'))) {
                $period_date = date('Y-m-d');
            }
        }

        if (!$daily_date && !$period_date) {
            return false;
        }

        if (!$daily_date && $period_date) {
            return $period_date;
        }

        if ($daily_date && !$period_date) {
            return $daily_date;
        }

        if ($type == 'min') {
            return strtotime($daily_date) > strtotime($period_date) ? $period_date : $daily_date;
        } else {
            return strtotime($daily_date) > strtotime($period_date) ? $daily_date : $period_date;
        }
        
    }

    /**
     * 获取产品门市价
     * @param  [type] $id  pid/tid
     * @return [type]      [description]
     */
    public function getMarketPrice($id, $type = 'tid') {
        if ($type != 'tid') {
            $where = array('pid' => $id);
        } else {
            $where = array('id' => $id);
        }
        return $this->table(self::__TICKET_TABLE__)->where($where)->getField('tprice');
    }

    /**
     * 判断是否是自供应产品
     * @return boolean [description]
     */
    public function isSelfApplyProduct($memberid, $pid) {
        $find = $this->table(self::__PRODUCT_TABLE__)->where(array('id' => $pid, 'apply_did' => $memberid))->find();
        return $find ? true : false;
    }

    /**
     * 根据不同的条件获取门票表的数据
     * @author Guangpeng Chen
     *
     * @param int $id ID字段
     * @param string|array $where 查询条件
     * @param string|array $field 需要查询的字段
     * @param string  $join 关联查询
     * @return mixed
     */
    public function QueryTicketInfo($where, $field='', $join='')
    {
        if (!$where) throw_exception('查询条件不能为空');
        $field = !$field ?  $this->ticket_filed : $field;
        $query = $this->table(self::__TICKET_TABLE__ .' t ')
            ->field( $field )
            ->where($where);
        if ($join) $query->join($join);
        $data = $query->select();
        //echo $this->getLastSql();
        return $data;
    }

    /**
     * 获取该景区底下其他门票
     *
     * @author Guangpeng Chen
     * @param $lid
     * @return array
     */
    public function GetLandTickets($lid)
    {
        $where = [
            't.landid'=>$lid,
            'p.apply_limit'=>['neq',6],
        ];
        $data = $this->table(self::__TICKET_TABLE__ . ' t')->join(self::__PRODUCT_TABLE__ . " p on t.pid=p.id")
            ->where($where)
            ->field('t.title,t.id as tid')
            ->select();
        if ($data!==false) return ['code'=>200,'data'=>$data];
        return ['code'=>0, 'data'=>'', 'msg'=>$this->getDbError()];
    }

    /**
     * 获取时间段价格
     *
     * @param int $pid 产品ID
     * @return array
     */
    public function getPriceSection($pid)
    {
        $today  = date('Y-m-d');
        $priceModel = new PriceRead();
        $price = $priceModel->get_Dynamic_Price_Merge($pid, '', 0, '', '', 0, 1);
        $price_section = array();
        foreach($price as $val){
            if($val['ptype']==0 && $val['end_date']>=$today){
                $price_section[$val['id']] = array(
                    'js'    => $val['n_price'],
                    'ls'    => $val['l_price'],
                    'id'    => $val['id'],
                    'sdate' => $val['start_date'],
                    'edate' => $val['end_date'],
                    'storage'  => $val['storage'],
                    'weekdays' => $val['weekdays'],
                );
            }
        }
        return $price_section;
    }
    public function UpdateProducts($where, $params)
    {
        //uu_products
        return $this->table(self::__PRODUCT_TABLE__)->where($where)->save($params);
    }

    /**
     * 修改票类属性
     *
     * @param array $where
     * @param array $params
     * @param string $table
     * @return bool
     */
    public function UpdateTicketAttributes($where, $params, $table='uu_jq_ticket')
    {
        $res = $this->table($table)->where($where)->save($params);
        if ($res===false) {
            return false;
        }
        return true;
    }

    public function CreateTicket($ticketData)
    {
        $lastid = $this->table(self::__TICKET_TABLE__)->data($ticketData)->add();
        if ($lastid!==false) {
            return ['code'=>200, 'data'=>['lastid'=>$lastid], 'msg'=>'添加成功'];
        }
        return ['code'=>0, 'data'=>'', 'msg'=>'添加失败,错误信息:' . $this->getDbError()];
    }

    public function CreateTicketExtendInfo($extData)
    {
        $lastid = $this->table(self::__TICKET_TABLE_EXT__)->data($extData)->add();
        if ($lastid!==false) {
            return ['code'=>200, 'data'=>['lastid'=>$lastid], 'msg'=>'添加成功'];
        }
        return ['code'=>0, 'data'=>'', 'msg'=>'添加失败,错误信息:' . $this->getDbError()];
    }

    public function OptLog()
    {
        include_once BASE_WWW_DIR.'/class/MemberAccount.class.php';
        if($_SESSION['dtype']==6) MemberAccount::StuffOptLog($_SESSION['memberID'], $_SESSION['sid'], $daction);
    }
}
