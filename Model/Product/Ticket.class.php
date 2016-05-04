<?php
/**
 * 门票信息模型
 */

namespace Model\Product;
use Library\Model;

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

    public function UpdateProducts($where, $params)
    {
        //uu_products
        return $this->table(self::__PRODUCT_TABLE__)->where($where)->save($params);
    }

    public function SaveTicket($oneTicket)
    {
        $isSectionTicket = false;// 是否是期票
        if($oneTicket['order_start'] && $oneTicket['order_end']) $isSectionTicket = true;
        // 价格判断
        if(isset($oneTicket['price_section']) && count($oneTicket['price_section']) ){

            $compareSec = array();
            $changeNote = array();
            $original_price = getPriceSection($soap, $oneTicket['pid']);
            foreach($oneTicket['price_section'] as $row)
            {
                // 期票模式（有效期是时间段）只能全部有价格
                if($isSectionTicket && ($row['weekdays']!='0,1,2,3,4,5,6')) return array('status'=>'fail', 'msg'=>'期票模式必须每天都有价格');
                if(($tableId = ($row['id']+0))==0) continue; // 已存在表ID
                $section = $row['sdate'].' 至 '.$row['edate'];
                $diff_js = $original_price[$tableId]['js'] - $row['js'];
                $diff_ls = $original_price[$tableId]['ls'] - $row['ls'];
                if($diff_js) $changeNote[] = $section.' 供货价变动，原:'.($original_price[$tableId]['js']/100).'，现:'.($row['js']/100);
                if($diff_ls) $changeNote[] = $section.' 零售价变动，原:'.($original_price[$tableId]['ls']/100).'，现:'.($row['ls']/100);
            }
        }

        // 整合数据
        $jData = $fData = array();
        $jData['title']   = $oneTicket['ttitle'];
        $jData['landid']  = $oneTicket['lid']+0;
        $jData['tprice']  = $oneTicket['tprice']+0;    // 门市价
        $jData['pay']     = $oneTicket['pay']+0;       // 支付方式 0 现场 1 在线
        $jData['ddays']   = $oneTicket['ddays']+0;     // 提前下单时间
        $jData['getaddr'] = $oneTicket['getaddr'];     // 取票信息
        $jData['notes']   = $oneTicket['notes'];       // 产品说明
        // $jData['order_limit']   = $oneTicket['order_limit'];    // 验证限制
        $jData['buy_limit_up']  = $oneTicket['buy_limit_up']+0; // 购买上限
        $jData['buy_limit_low'] = $oneTicket['buy_limit_low']+0;
        $jData['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $oneTicket['order_limit'])));

        if(($jData['buy_limit_up']>0) && $jData['buy_limit_low']>$jData['buy_limit_up'])
            return array('status'=>'fail', 'msg'=>'最少购买张数不能大于最多购买张数');

        // 延迟验证
        $delaytime = array(0,0);
        if(!isset($oneTicket['vtimehour']) && $oneTicket['vtimehour']) $delaytime[0] = $oneTicket['vtimehour']+0;
        if(!isset($oneTicket['vtimeminu']) && $oneTicket['vtimeminu']) $delaytime[1] = $oneTicket['vtimeminu']+0;
        $jData['delaytime'] = implode('|', $delaytime);

        // 闸机绑定
        $jData['uuid'] = isset($oneTicket['uuid']) ? $oneTicket['uuid']:'';

        if($jData['uuid'])
        {
            $sql = "select jiutian_auth from pft_member_extinfo where fid={$_SESSION['sid']} limit 1";
            $GLOBALS['le']->query($sql);
            $GLOBALS['le']->fetch_assoc();
            if($GLOBALS['le']->f('jiutian_auth')) $jData['sourceT'] = 1;
        }

        if(isset($oneTicket['tid']) && $oneTicket['tid']>0)
        {
            $tid = $oneTicket['tid'];
            $sql = "select sourceT from uu_jq_ticket where id=$tid limit 1";
            $GLOBALS['le']->query($sql);
            if($GLOBALS['le']->fetch_assoc()) if($GLOBALS['le']->f('sourceT')==2) $jData['sourceT'] = 2;
        }
        if($jData['buy_limit_low']<=0) return array('status'=>'fail', 'msg'=>'购买下限不能小于0');

        $jData['max_order_days']    = isset($oneTicket['max_order_days']) ? $oneTicket['max_order_days']+0:'-1';// 提前预售天数
        $jData['cancel_auto_onMin'] = abs($oneTicket['cancel_auto_onMin']); // 未支付多少分钟内自动取消

        // 取消费用（统一）
        $jData['reb']      = $oneTicket['reb']+0;   // 实际值以分为单位
        $jData['reb_type'] = $oneTicket['reb_type'];// 取消费用类型 0 百分比 1 实际值
        if($jData['reb_type']==0) {
            $jData['reb'] = $jData['reb'] / 100;
            if($jData['reb']>100 || $jData['reb']<0) return array('status'=>'fail', 'msg'=>'取消费用百分比值不合法');
        }

        // 阶梯取消费用设置
        if(isset($oneTicket['cancel_cost']) && $oneTicket['cancel_cost'])
        {
            $c_days = array();
            foreach($oneTicket['cancel_cost'] as $row)
            {
                if(in_array($row['c_days'], $c_days))
                    return array('status'=>'fail', 'msg'=>'退票手续费日期重叠');
                $c_days[] = $row['c_days'];
            }
        }

        $jData['cancel_cost'] = (isset($oneTicket['cancel_cost'])) ? json_encode($oneTicket['cancel_cost']):'';
        $jData['cancel_cost'] = addslashes($jData['cancel_cost']);
        // exit;

        // 订单有效期 类型 0 游玩时间 1 下单时间 2 区间
        $jData['delaytype'] = $oneTicket['validTime']+0;
        $jData['delaydays'] = $oneTicket['delaydays']+0;
        $jData['order_end'] = $jData['order_start'] = '';
        if($oneTicket['validTime']==2){
            if($oneTicket['order_end']=='' || $oneTicket['order_start']=='')
                return array('status'=>'fail', 'msg'=>'有效期时间不能为空');
            $jData['order_end']   = date('Y-m-d 23:59:59', strtotime($oneTicket['order_end']));// 订单截止有效日期
            $jData['order_start'] = date('Y-m-d 00:00:00', strtotime($oneTicket['order_start']));
        }

        // 退票规则 0 有效期内、过期可退 1 有效期内可退 2  不可退
        $jData['refund_rule'] = $jData['refund_early_time'] = 0;
        if(!isset($oneTicket['refund_rule'])) $jData['refund_rule'] = $oneTicket['refund_rule']+0;
        if(!isset($oncTicket['refund_early_time'])) $jData['refund_early_time'] = $oncTicket['refund_early_time']+0;

        // 过期退票规则
        // $jData['overdue_refund'] = 0;// 不可退
        // if(isset($oneTicket['overdue_refund'])) $jData['overdue_refund'] = $oneTicket['overdue_refund']+0;
        // $jData['overdue_auto_check']  = isset($oneTicket['overdue_auto_check']) ? $oneTicket['overdue_auto_check']+0:0;
        // $jData['overdue_auto_cancel'] = isset($oneTicket['overdue_auto_cancel']) ? $oneTicket['overdue_auto_cancel']+0:0;

        // 退票审核
        $jData['refund_audit'] = (isset($oneTicket['refund_audit']) && $oneTicket['refund_audit']) ? 1:0;

        $cancel_sms  = 0;// 取消是否通知游客
        $cancel_sms  = isset($oneTicket['cancel_sms']) ? $oneTicket['cancel_sms']+0:0;
        $confirm_sms = isset($oneTicket['confirm_sms']) ? $oneTicket['confirm_sms']+0:0;
        $fData['confirm_sms']  = bindec($cancel_sms.$confirm_sms);


        // 取消通知供应商 0 不通知 1 通知
        if(isset($oncTicket['cancel_notify_supplier'])) $jData['cancel_notify_supplier'] = $oncTicket['cancel_notify_supplier']+0;
        // 分批验证设置
        $jData['batch_check']     = $oneTicket['batch_check']+0;
        $jData['batch_day_check'] = $oneTicket['batch_day_check']+0;
        $jData['batch_diff_identities'] = $oneTicket['batch_diff_identities']+0;
        // 景点类别属性（二次交互）
        $jData['Mpath'] = '';
        if(isset($oneTicket['mpath']))    $jData['Mpath'] = $oneTicket['mpath'];

        $jData['Mdetails'] = ($jData['Mpath']) ? 1:0;

        if(isset($oneTicket['re_integral'])) $jData['re_integral'] = $oneTicket['re_integral'] + 0;

        $jData['apply_did'] = $oneTicket['apply_did'];// 产品供应商

        // 验证景区是否存在
        $lid = $oneTicket['lid']+0;
        $sql = "select title,id,p_type from uu_land where id=$lid limit 1";
        $GLOBALS['le']->query($sql);
        if(!$GLOBALS['le']->fetch_assoc()) return array('status'=>'fail', 'msg'=>'景区不存在');
        $ltitle = $GLOBALS['le']->f('title');
        $p_type = $GLOBALS['le']->f('p_type');



        // 扩展属性 uu_land_f
        $fData['confirm_wx']   = $oneTicket['confirm_wx']+0;
        $fData['sendVoucher']  = $oneTicket['sendVoucher']+0;
        // $fData['confirm_sms']  = $oneTicket['confirm_sms']+0;
        $fData['tourist_info'] = $oneTicket['tourist_info']+0;

        // 提前预定小时  01:00:00 - 23:59:00
        $fData['dhour'] = str_pad($oneTicket['dhour'], 5, 0, STR_PAD_LEFT).':00';
        if($p_type=='H') $fData['zone_id'] = $oneTicket['zone_id']+0;

        // 验证时间 08:00|18:00
        $fData['v_time_limit'] = 0;
        if(isset($oneTicket['v_time_limit']) && $oneTicket['v_time_limit'])
        {
            $arr1 = explode('|', $oneTicket['v_time_limit']);
            $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
            $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
            $fData['v_time_limit'] = implode('|', $arr1);
        }

        if($p_type=='B')
        {
            $fData['rdays'] = $oneTicket['rdays']+0;// 游玩天数
            $fData['series_model'] = '';
            if(isset($oneTicket['g_number']) && $oneTicket['g_number']) $fData['series_model'] = $oneTicket['g_number'].'{fck_date}';
            if(isset($oneTicket['s_number']) && $oneTicket['s_number'] && $fData['series_model']) $fData['series_model'].= '-'.$oneTicket['s_number'];
            $ass_station = $oneTicket['ass_station'];
            $ass_station = str_replace('；', ';', $ass_station);
            $fData['ass_station'] = addslashes(serialize(explode(';', $ass_station)));
        }


        if(isset($oneTicket['tid']) && $oneTicket['tid']>0)
        {   // 以下编辑操作

            $tid = $oneTicket['tid']+0;
            $sql = "select * from uu_jq_ticket t left join uu_products p on t.pid=p.id where t.id=$tid limit 1";
            $GLOBALS['le']->query($sql);// 缓存原设置
            if(($original_info = $GLOBALS['le']->fetch_assoc())){
                $original_info['memberID'] = $_SESSION['memberID'];
                $original_info['REQUESTD'] = $_REQUEST;
                // write_logs(json_encode($original_info), 'before_ticket_'.date('Ymd').'.txt');
            }else return array('status'=>'fail', 'msg'=>'票类不存在');

            $pid = $original_info['pid'];
            $sql = buildUpdateSql($jData, 'uu_jq_ticket', "where id=$tid limit 1"); // echo $sql;
            if(!$GLOBALS['le']->query($sql)) return array('status'=>'fail', 'msg'=>'其他错误,请联系客服');
            $sql = buildUpdateSql($fData, 'uu_land_f', "where tid=$tid limit 1");
            if(!$GLOBALS['le']->query($sql)) return array('status'=>'fail', 'msg'=>'其他错误,请联系客服');

            $sql = "UPDATE uu_products SET verify_time=now() WHERE id=$pid LIMIT 1";
            $GLOBALS['le']->query($sql);
            $daction = "对 $ltitle".$jData['title']." 进行编辑";

            // 产品有效期监控
            if(count($original_info))
            {
                $oneTicket['pid']    = $pid;
                $oneTicket['action'] = 'CreateNewTicket';
                $oneTicket['add_ticket']  = ($tid==0) ? 1:0;
                $oneTicket['validHtml_2'] = htmlValid($jData);
                $oneTicket['validHtml_1'] = htmlValid($original_info);
                fsockNoWaitPost("http://".IP_INSIDE."/new/d/call/detect_prod.php", $oneTicket);
            }
        }
        else
        {
            // 以下新增操作
            $tid = $this->table(self::__TICKET_TABLE__)->data($jData)->add();

            if(!$tid) {
                return ['status'=>'fail', 'msg'=>'操作失败,错误信息:' . $this->getDbError()];
            }
            $pid = $this->QueryTicketInfo(['t.id'=>$tid], 'pid');
            // $tid = 0; $pid = 0;
            $fData['lid'] = $lid;
            $fData['pid'] = $pid[0]['pid'];
            $fData['tid'] = $tid;
            $last_id = $this->table(self::__TICKET_TABLE_EXT__)->data($fData)->add();
            if($last_id) return array('status'=>'fail', 'msg'=>'添加失败！错误信息：'. $this->getDbError());
            $daction = '添加门票.'.$ltitle.$jData['title'];
        }

        $apply_limit = $oneTicket['apply_limit']+0;
        $this->UpdateProducts(['id'=>$fData['pid']], ['apply_limit'=>$apply_limit, 'p_status'=>0]);

        include_once BASE_WWW_DIR.'/class/MemberAccount.class.php';
        if($_SESSION['dtype']==6) pft\Member\MemberAccount::StuffOptLog($_SESSION['memberID'], $_SESSION['sid'], $daction);

        // 保存或修改价格判断
        // print_r($original_price);
        if(isset($oneTicket['price_section']) && count($oneTicket['price_section']) && $pid){

            foreach($oneTicket['price_section'] as $row)
            {

                if(($tableId = ($row['id']+0))>0)
                {

                    $intersect = array();
                    $intersect = array_diff_assoc($row, $original_price[$tableId]);
                    if(count($intersect)==0) continue;
                }
                $action = ($tableId>0) ? 1:0;// 0 插入 1 修改
                $sdate  = date('Y-m-d', strtotime($row['sdate']));
                $edate  = date('Y-m-d', strtotime($row['edate']));
                $apiret = $soap->In_Dynamic_Price_Merge($pid, $sdate, $edate, $row['js'], $row['ls'], 0, $action, $tableId, '', $row['weekdays'], ($row['storage']+0));
                // print_r(array($pid, $sdate, $edate, $row['js'], $row['ls'], 0, $action, $tableId, '', $row['weekdays'], ($row['storage']+0)));
                if($apiret!=100) return array('status'=>'fail', 'msg'=>$apiret);
            }
        }
        return array('status'=>'success','data'=>array('lid'=>$lid, 'tid'=>$tid, 'pid'=>$pid, 'ttitle'=>$jData['title']));
    }
}
