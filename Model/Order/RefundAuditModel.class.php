<?php
/**
 * User: Fang
 * Time: 15:32 2016/3/11
 */

namespace Model\Order;


use Library\Model;

class RefundAuditModel extends Model
{
    private $_refundAuditTable = 'uu_order_terminal_change';
    private $_orderTable = 'uu_ss_order';
    private $_orderAppendixTable = 'uu_order_addon';
    private $_landTable = 'uu_land';
    private $_ticketTable = 'uu_jq_ticket';
    private $_orderDetailTable = 'uu_order_fx_details';
    private $_memberTable = 'pft_member';

    /**
     * @param int    $orderNum    平台订单号
     * @param int    $terminal    终端号
     * @param int    $salerid     景区6位编号
     * @param int    $lid         景区id
     * @param int    $tid         门票id
     * @param int    $modifyType  修改类型 0-撤改 1-撤销 2-修改 3-取消
     * @param int    $targetTnum  变更后票数
     * @param int    $operatorID  退票发起人
     * @param int    $auditStatus 退票审核状态 0-未处理 1-同意 2-拒绝 3-等待第三方自动审核
     * @param int    $requestTime 申请时间
     * @param string $auditNote   审核备注
     * @param int    $auditorID   审核人
     * @param int    $auditTime   审核时间
     *
     * @return mixed
     */
    public function addRefundAudit(
        $orderNum,
        $terminal,
        $salerid,
        $lid,
        $tid,
        $modifyType,
        $targetTnum,
        $operatorID = 1,
        $auditStatus = 0,
        $auditorID = 0,
        $requestTime = 0,
        $auditNote = '',
        $auditTime = 0
    ) {
        $table       = $this->_refundAuditTable;
        $requestTime = ($requestTime) ? $requestTime : date('Y-m-d H:i:s');
        $data        = [
            'ordernum' => $orderNum,
            'terminal' => $terminal,
            'salerid'  => $salerid,
            'lid'      => $lid,
            'tid'      => $tid,
            'stype'    => $modifyType,
            'tnum'     => $targetTnum,
            'dstatus'  => $auditStatus,        /*状态0未操作1同意2拒绝*/
            'stime'    => $requestTime,
            'fxid'     => $operatorID, //申请发起人
            'dadmin'   => $auditorID,
        ];
        if ($auditTime) {
            $data['dtime'] = $auditTime;
        }
        if ($auditNote) {
            $data['reason'] = $auditNote;
        }

        return $this->table($table)->data($data)->add();
    }

    /**
     * 获取退款审核的订单信息
     *
     * @param int $orderNum 平台订单号
     *
     * @return int
     */
    public function getOrderInfoForAudit($orderNum)
    {
        $table = "{$this->_orderTable} AS o";

        //这里的ordernum需要是string才会使用上索引
        $orderNum = strval($orderNum);

        $where = array(
            'o.ordernum' => $orderNum,
        );
        $join  = array(
            "join {$this->_landTable} AS l ON o.lid=l.id",
            "join {$this->_orderAppendixTable} AS oa ON o.ordernum=oa.orderid",
            "join {$this->_ticketTable} AS t ON o.tid=t.id",
            "join {$this->_orderDetailTable} AS od ON o.ordernum=od.orderid",
        );
        $field = array(
            'o.salerid',
            'o.lid',
            'o.tid',
            'o.status',
            'o.personid',
            'l.terminal',
            'l.apply_did as aid',
            'oa.ifpack',
            'oa.pack_order',
            'o.tnum',
            't.refund_audit',
            'od.concat_id',
            'od.aids',

        );

        $result = $this->table($table)->where($where)->join($join)->field($field)->find();

        return $result;
    }

    /**
     * 获取套票信息
     * @param $orderNum
     *
     * @return mixed
     */
    public function getPackOrderInfo($orderNum){
        $table = $this->_orderAppendixTable;
        $where = array(
            "orderid" =>$orderNum,
        );
        $field = array(
            "pack_order",
            "ifpack",
        );
        $result = $this->table($table)->where($where)->field($field)->find();
        return $result;
    }

    /**
     * 获取订单退票申请信息
     * @param     $orderNum
     * @param int $dstatus
     *
     * @return mixed
     */
    public function getAuditInfoForAudit($orderNum,$dstatus=0){
        $table = "$this->_refundAuditTable AS a";
        $join = array(
            "{$this->_orderAppendixTable} AS oa ON oa.orderid=a.ordernum",
        ) ;
        $where = array(
            "a.dstatus" => $dstatus,
            "a.orderid" => $orderNum,
        );
        $field = array(
            "a.tnum as audit_tnum",
            "oa.ifpack",
            "oa.pack_order",
        );
        $order = "id desc";
        $result = $this->table($table)->join($join)->where($where)->field($field)->order($order)->find();
        return $result;
    }
    /**
     * 查询订单是否处于退款审核状态
     *
     * @param int $orderNum   订单号
     * @param int $modifyType 变更类型：2-修改 3-取消
     *
     * @return int
     */
    public function isUnderAudit($orderNum, $modifyType = null)
    {
        $table = $this->_refundAuditTable;
        $where = array(
            'ordernum' => $orderNum,
            'dstatus'  => 0,
        );
        if ($modifyType!==null) {
            $where['stype'] = $modifyType;
        }

        $field  = ['id'];
        $result = $this->table($table)->where($where)->field($field)->find();

        return $result;
    }

    /**
     * 检查套票主票是否有未审核通过的子票订单
     *
     * @param $orderNum
     *
     * @return mixed
     */
    public function hasUnAuditSubOrder($orderNum)
    {
        $table = "{$this->_refundAuditTable} AS a";
        $join  = array(
            "LEFT JOIN {$this->_orderAppendixTable} AS oa ON a.ordernum=oa.orderid",
            "JOIN {$this->_ticketTable} AS t ON a.tid=t.id",
        );
        $where = array(
            "t.refund_audit" => 1,
            "a.dstatus"      => 0,
            "oa.pack_order"  => $orderNum,
        );

        return $this->table($table)->join($join)->where($where)->find();

    }

    /**
     *  更新退款审核结果
     *
     * @param int $orderNum    订单号
     * @param int $auditResult 审核结果 1-同意 2-决绝
     * @param int $auditNote   审核意见
     * @param int $operatorID  审核人
     * @param int $auditID     审核记录ID
     * @param int $auditTime   审核时间
     *
     * @return bool
     */
    public function updateAudit(
        $orderNum,
        $auditResult,
        $auditNote,
        $operatorID = 1,
        $auditTime = 0,
        $auditID = 0
    ) {
        $table = $this->_refundAuditTable;
        //审核记录ID和订单至少要传入一个才能更新 订单号优先
        if ($auditID) {
            $where['id'] = $auditID;
        } elseif ($orderNum) {
            $where = array(
                'ordernum' => $orderNum,
                'dstatus'  => 0,
            );
        } else {
            return false;
        }

        $auditTime = ($auditTime) ? $auditTime : date('Y-m-d H:i:s');

        $data   = array(
            'dstatus' => $auditResult,
            'reason'  => $auditNote,
            'dadmin'  => $operatorID,
            'dtime'   => $auditTime,
        );
        $result = $this->table($table)->where($where)->data($data)->save();

        return $result;
    }

    /**
     * 获取部分使用订单的已验证票数
     *
     * @param $orderNum
     *
     * @return mixed
     */
    public function getVerifiedTnum($orderNum)
    {
        $table = 'pft_order_track';
        $field = 'sum(tnum) as verify_num';
        $where = ['ordernum' => $orderNum,
            'action'   => 5,
        ];
        $order = 'id desc';

        $result =  $this->table($table)
                    ->where($where)
                    ->field($field)
                    ->order($order)
                    ->find();
        if($result){
            return $result['verify_num'];
        }else{
            return $result;
        }
    }

    /**
     * 获取拒绝票数
     *
     * @param $orderNum
     *
     * @return mixed
     */
    public function getAuditTargetTnum($orderNum)
    {
        $table = $this->_refundAuditTable;
        $where = array(
            'ordernum' => $orderNum,
            'dstatus'  => 0,
        );
        $filed = 'tnum as audit_tnum';
        $order = 'id desc';

        return $this->table($table)
                    ->where($where)
                    ->field($filed)
                    ->order($order)
                    ->find();
    }

    /**
     * 获取退款审核列表
     *
     * @param      $memberID
     * @param null $landTitle
     * @param null $noticeType
     * @param null $applyDate
     * @param null $auditStatus
     * @param null $auditDate
     * @param null $orderNum
     * @param bool $getTotalPage
     * @param int  $page
     * @param int  $limit
     *
     * @return mixed
     */
    public function getAuditList(
        $memberID,
        $landTitle = null,
        $noticeType = null,
        $applyDate = null,
        $auditStatus = null,
        $auditDate = null,
        $orderNum = null,
        $getTotalPage = false,
        $page = 1,
        $limit = 20
    ) {
        $table = "$this->_refundAuditTable AS a";
        $join  = array(
            "left join {$this->_landTable} AS l ON l.id=a.lid",
            "left join {$this->_orderDetailTable} AS od ON od.orderid=a.ordernum",
            "left join {$this->_orderAppendixTable} AS oa ON a.ordernum=oa.orderid",
            "left join {$this->_memberTable} AS m ON m.id=l.apply_did",
            "left join {$this->_ticketTable} AS t ON a.tid=t.id",
        );
        $where = array(
            "l.status" => array('lt', 3), //产品处于上架状态
            "_complex" => array(
                //默认只显示需要退票审核的订单
                //如果是套票主票的话，不管是否需要退票审核都显示（分销商）
                //联票不单独处理，所有都显示 2016-4-11修正
                array(
                    't.refund_audit' => 1,
                    'oa.ifpack'      => 1,
                    "a.stype" => array("in", [0, 1]), //撤销撤改的不受以上限制
                    '_logic'         => 'or',
                ),
            ),
        );
        //根据传入参数确定查询条件
        //2016-3-27 供应商能看到套票子票，分销商能看到套票主票
        //2016-3-28 修改撤销撤改记录的显示
        if ($memberID != 1) {
            $where['_complex'][] = array(
                array(
                    'l.apply_did' => $memberID,
                    array(
                        array(
                            'oa.ifpack' => array('in', array(0, 2)),
                            'a.stype'   => array('in', array(2, 3)),
                        ),
                        array(
                            'a.stype' => array('in', array(0, 1)),
                        ),
                        '_logic' => 'or',
                    ),
                ),
                array(
                    'a.fxid'    => $memberID,
                    'oa.ifpack' => array('in', array(0, 1)),
                ),
                array(
                    't.apply_did'    => $memberID,
                ),
                array(
                    '_string' => "find_in_set('{$memberID}',od.aids)",
                    'oa.ifpack' => array('in', array(0, 1)),
                ),
                '_logic' => 'or',
            );
        }

        if ($orderNum) {
            $where['_complex'][]=array(
                    'a.ordernum' => $orderNum,
                    'od.concat_id'=> $orderNum,
                    '_logic' => 'or',
                );
        } else {
            if ($landTitle) {
                $where['l.title'] = array("like", "%{$landTitle}%");
            }
            if ($noticeType !== null) {
                $where['a.stype'] = $noticeType;
            }
            if ($applyDate) {
                $applyDate        = substr($applyDate, 0, 10);
                $bTime1           = $applyDate . " 00:00:00";
                $eTime1           = $applyDate . " 23:59:59";
                $where['a.stime'] = array('between', "{$bTime1},{$eTime1}");
            }
            if ($auditStatus != null) {
                $where['a.dstatus'] = $auditStatus;
            }
            if ($auditDate) {
                $auditDate        = substr($auditDate, 0, 10);
                $bTime2           = $auditDate . " 00:00:00";
                $eTime2           = $auditDate . " 23:59:59";
                $where['a.dtime'] = array('between', "{$bTime2},{$eTime2}");
                if ($auditStatus == 0) {
                    $where['a.dstatus'] = array('in', array(1, 2));
                }
            }
        }
        //获取记录总数
        if ($getTotalPage) {
            $field = array("count(*)");

            return $this->table($table)
                        ->join($join)
                        ->where($where)
                        ->field($field)
                        ->find();
        } else {
            //查询记录详情
            $field = array(
                'a.id',
                'a.ordernum',
                'a.stype',
                'a.tnum',
                'a.dstatus',
                'a.reason',
                'a.stime',
                'a.dtime',
                'l.title AS ltitle',
                'l.apply_did',
                'od.concat_id',
                'm.dcodeURL',
                'oa.ifpack',
                'oa.pack_order',
                't.sourceT',
            );
            $order = array(
                'stime DESC',
                'dstatus ASC',
            );
            $map   = $this->table($table)
                          ->join($join)
                          ->where($where)
                          ->field($field)
                          ->page($page)
                          ->limit($limit)
                          ->order($order);
            if ($limit == 1) {
                $result = $map->find();
            } else {
                $result = $map->select();
            }

//            $this->test();
//           print_r($result);
            return $result;
        }
    }


    /**
     * 获取ota通知列表
     *
     * @param null $orderNum
     * @param null $noticeType
     * @param null $noticeDate
     * @param null $memberId
     * @param int  $page
     * @param int  $limit
     *
     * @return array|bool
     */
    public function getLogList($orderNum=null,$noticeType=null,$noticeDate=null,$memberId=null,$page=1,$limit=20){
        $orderNum   = $orderNum ? $orderNum : null;
        $noticeType = (in_array($noticeType,[2,3])) ? $noticeType : null;
        $page       = $page ? $page : 1;
        $limit      = $limit ? $limit : 20;
        $table  = "{$this->_refundAuditTable} AS a";
        $join   = array(
            "JOIN {$this->_landTable} AS l ON a.lid=l.id",
            "JOIN {$this->_memberTable} AS m ON m.id=a.fxid",
            "JOIN {$this->_orderAppendixTable} AS oa ON oa.orderid=a.ordernum",
        );
        $where  = array(
            "oa.ifpack" => array('in', [1,0]),
            "a.dstatus"  => array('in', [1, 2]),
            "m.dcodeURL" => array('neq', ''),
            'm.dtype'=>array('in',[0,1,7]),
            'm.status'=>array('in',[0,3]),
            'length(m.account)' => 6,
        );
        if($orderNum) {
            $where['a.ordernum'] = $orderNum; //订单号优先级最高
        }else{
            //变动通知类型
            if($noticeType){
                $where['a.stype'] = $noticeType;
            }
            //通知日期
            if($noticeDate){
                $noticeDate = substr($noticeDate,0,10);
                $bTime = $noticeDate . ' 00:00:00';
                $eTime = $noticeDate . ' 23:59:59';
                $where['a.dtime'] = array('between',"{$bTime},{$eTime}");
            }
            //通知接口
            if($memberId){
                $where['a.fxid'] = $memberId;
            }
        }
        $field  = array(
            'a.id as notice_id',
            'a.tnum',
            'a.ordernum',
            'l.title as ltitle',
            'a.stype as change_type',
            'a.stime as apply_time',
            'a.dstatus as handle_res',
            'a.reason',
            'm.dname as ota_name',
//            'a.dtime as push_time',
//            'a.fxid',
        );
        $order  = array(
            'a.dtime desc',
        );
        $list = $this->table($table)
            ->join($join)
            ->where($where)
            ->field($field)
            ->order($order)
            ->page($page)
            ->limit($limit)
            ->select();
//        $this->test();
        if(!$list){
            return false;
        }
        $total = $this->table($table)->join($join)->where($where)->count();
        return array('total'=>$total, 'list'=>$list);
    }

    public function getTicketInfoById($tid, $field = 'id')
    {
        return $this->table('uu_jq_ticket')->where(['id' => $tid])->getField($field);
    }

    /**
     * 获取判断退票审核的相关信息
     *
     * @param $ordernum
     *
     * @return mixed
     */
    public function getInfoForAuditCheck($ordernum)
    {
        $table  = "{$this->_orderTable} AS o";
        $join   = array(
            "{$this->_orderDetailTable} AS od ON o.ordernum=od.orderid",
            "{$this->_orderAppendixTable} AS oa ON o.ordernum=oa.orderid",
        );

        //这里的ordernum需要是string才会使用上索引
        $ordernum = strval($ordernum);

        $where  = array(
            "o.ordernum" => $ordernum,
        );
        $field  = array(
            "o.status",
            "o.aid",
            "o.paymode",
            "o.tid",
            "o.tnum",
            "od.concat_id",
            "od.pay_status",
            "od.aids",
            "oa.ifpack",
            "oa.pack_order",
        );
        $result = $this->table($table)
                       ->join($join)
                       ->where($where)
                       ->field($field)
                       ->find();

        return $result;
    }

    /**
     * 获取通知接口列表
     *
     * @param string $ota_name 可按照用户名查询
     *
     * @param int    $page
     * @param int    $limit
     *
     * @return mixed
     */
    public function getReceiverList($ota_name = null,$page=1,$limit=20){
        $table = $this->_memberTable;
        $where = array(
            'dcodeURL' => array('neq',''),
            'dtype'=>array('in',[0,1,7]),
            'status'=>array('in',[0,3]),
            'length(account)' => 6,
        );
        if ($ota_name){
            $where['dname'] = array('like', "%{$ota_name}%");
        }
        $field = array(
            'dname as ota_name',
            'id as memberid',
        );
        $data = $this->table($table)->where($where)->field($field)->page($page)->limit($limit)->select();
//        $this->test();
        $total = $this->table($table)->where($where)->count();
        $result = array(
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'data' => $data,
        );
        return $result;
    }
    /**
     * 测试用：打印调用的sql语句
     *
     * @return string
     */
    private function test()
    {
        $str = $this->getLastSql();
        print_r($str . PHP_EOL);
    }

    public function logTime($last, $content)
    {
        $now  = microtime(true);
        $time = $now - $last;
        $content .= ":$time";
        pft_log('refund', $content);

        return $now;
    }
}