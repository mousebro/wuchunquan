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
        $where = array(
            'o.ordernum' => $orderNum,
        );
        $join  = array(
            "join {$this->_landTable} AS l ON o.lid=l.id",
            "join {$this->_orderAppendixTable} AS oa ON o.ordernum=oa.orderid",
            "join {$this->_ticketTable} AS t ON o.tid=t.id",
            "join {$this->_orderDetailTable} AS od ON o.ordernum=od.orderid",
            //            "left join {$this->_refundAuditTable} AS a ON a.ordernum=o.ordernum"
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
            //            't.mdetails',
            //            't.sourceT',
            //            'o.ordernum',
            //            'o.status',
            //            'l.p_type',
            't.refund_audit',
            'od.concat_id',
            'od.aids',
            //            'a.id as audit_id',
            //            'a.tnum as audit_tnum',
            //            'a.dstatus'
        );
//        $order = "a.id desc";

        $result = $this->table($table)
                       ->where($where)
                       ->join($join)
                       ->field($field)
//                       ->order($order)
                       ->find();

//        $this->test();
        return $result;
    }
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
     * 获取对应退款审核记录
     *
     * @param $auditID
     *
     * @return mixed
     */
    public function getAuditByID($auditID)
    {
        $where = ['id' => $auditID];
        $table = $this->_refundAuditTable;

        return $this->table($table)->where($where)->find();
    }

    /**
     * 获取部分使用订单的剩余票数
     *
     * @param $orderNum
     *
     * @return mixed
     */
    public function getRemainTicketNumber($orderNum)
    {
        $table = 'pft_order_track';
        $field = 'sum(*) as verify_num';
        $where = ['ordernum' => $orderNum,
                  'action' => 5
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
            ));
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
                //                't.mdetails',
                't.sourceT'
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
//            print_r($result);
            return $result;
        }
    }

    /**
     *
     * 查询联票中子票是否需要退票审核
     * @param $mainOrder
     *
     * @return mixed
     */
    public function requireAuditByLinkSubOrder($mainOrder){
        $table = "{$this->_orderTable} AS o";
        $join = array(
            "{$this->_orderDetailTable} AS od on od.orderid=o.ordernum",
            "{$this->_ticketTable} AS t on t.id=o.tid",
        );
        $where = array(
            "t.refund_audit" => 1,
            "od.concat_id" => $mainOrder,
        );
        $field = array(
            "o.id");
        $result = $this
            ->table($table)
            ->join($join)
            ->where($where)
            ->field($field)
            ->find();
//        $this->test();
        return $result;
    }
    /**
     * @param $page
     * @param $limit
     *
     * @return mixed
     */
    public function getNoticeList(array $orderNum, $page = 1, $limit = 20)
    {
        $limit  = $limit ? $limit : 20;
        $page   = $page ? $page : 1;
        $table  = "{$this->_refundAuditTable} AS a";
        $join   = array(
            "JOIN {$this->_landTable} AS l ON a.lid=l.id",
            "JOIN {$this->_memberTable} AS m ON m.id=a.fxid",
        );
        $where  = array(
            "a.dstatus"  => array('in', [1, 2]),
            "m.dcodeURL" => array('neq', ''),
            "a.ordernum" => array('in', $orderNum),
        );
        $field  = array(
            'a.ordernum',
            'a.stype',
            'a.dtime',
            'a.stime',
            'a.fxid',
            'a.dstatus',
            'm.dname',
            'l.title',
        );
        $order  = array(
            'a.dtime desc',
        );
        $result = $this->table($table)
                       ->join($join)
                       ->where($where)
                       ->field($field)
                       ->order($order)
                       ->page($page)
                       ->limit($limit)
                       ->select();

        return $result;
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
            "{$this->_ticketTable} AS t ON o.tid=t.id",
        );
        $where  = array(
            "o.ordernum" => $ordernum,
        );
        $field  = array(
            "t.refund_audit",
            "t.apply_did",
            "o.status",
            "o.paymode",
            "o.tnum",
            "od.concat_id",
            "od.pay_status",
            "oa.ifpack",
            "oa.pack_order",
        );
        $result = $this->table($table)
                       ->join($join)
                       ->where($where)
                       ->field($field)
                       ->find();

        // $this->test();
        return $result;
    }

    /**
     * 获取门票名称
     *
     * @param $orderNum
     *
     * @return mixed
     */
    public function getTicketTitle($orderNum)
    {
        $table               = "{$this->_ticketTable} AS t";
        $join                = "{$this->_orderTable} AS o ON o.tid=t.id";
        $field               = ["t.title"];
        $result              = $this->table($table)->join($join)->field($field);
        $where["o.ordernum"] = $orderNum;
        $result              = $result->where($where)->find();
        // print_r($result);
        // $this->test();
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

}