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
        $operatorID=1,
        $auditStatus = 0,
        $auditorID = 0,
        $requestTime = 0,
        $auditNote = '',
        $auditTime = 0
    ) {
        $table = $this->_refundAuditTable;
        $requestTime = ($requestTime) ? $requestTime : date('Y-m-d H:i:s');
        $data  = [
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
        $where = ['o.ordernum' => $orderNum];
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
            'o.personid',
            'l.terminal',
            'oa.ifpack',
            'oa.pack_order',
            'o.tnum',
            't.mdetails',
            //            'o.ordernum',
            //            'o.status',
            //            'l.p_type',
            't.refund_audit',
            'od.concat_id',
            'od.aids',
        );

        return $this->table($table)
                    ->where($where)
                    ->join($join)
                    ->field($field)
                    ->find();
    }

    /**
     * 查询订单是否处于退款审核状态
     *
     * @param int $orderNum   订单号
     * @param int $modifyType 变更类型：2-修改 3-取消
     *
     * @return int
     */
    public function isUnderAudit($orderNum,$modifyType=null)
    {
        $table  = $this->_refundAuditTable;
        $where  = array(
            'ordernum' => $orderNum,
            'dstatus'  => 0,
        );
        if(is_numeric($modifyType))  $where['stype'] = $modifyType;

        $field  = ['id'];
        $result = $this->table($table)->where($where)->field($field)->find();

        return $result;
    }

    /**
     * 检查套票主票是否有未审核通过的子票订单
     * @param $orderNum
     *
     * @return mixed
     */
    public function hasUnauditedPackSubOrders($orderNum){
        $table = "{$this->_refundAuditTable} AS a";
        $join = array(
            "LEFT JOIN {$this->_orderAppendixTable} AS oa ON a.ordernum=oa.orderid",
            "JOIN {$this->_ticketTable} AS t ON a.tid=t.id",
        );
        $where = array(
          "t.refund_audit" => 1,
          "a.dstatus"=>0,
          "oa.pack_order"=>$orderNum,
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
     * 获取退款审核列表
     *
     * @param      $memberID
     * @param null $landTitle
     * @param null $noticeType
     * @param null $applyTime
     * @param null $auditStatus
     * @param null $auditTime
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
        $applyTime = null,
        $auditStatus = null,
        $auditTime = null,
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
            "l.status" => array('lt', 3),
            "_complex" => array(
                array(
//                    't.refund_audit'=>1,
                    't.refund_audit'=>array('in',array(0,1)), //测试时使用
                    'oa.ifpack'=>1,
//                    'od.concat_id'=>array('neq',0), //TODO:联票显示逻辑上未验证
                    '_logic'=>'or',
                ),
                array(
                    array(
                        "od.concat_id" => array(0,array('exp','=od.orderid'),'or'),
                        "a.stype"=>3
                    ),
                    "a.stype"=>array("in",[0,1,2]),
                    '_logic'=>'or',
                )
            )
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
                            'oa.ifpack'=>array('in',array(0,2)),
                            'a.stype'=>array('in',array(2,3)),
                        ),
                        array(
                            'a.stype'=>array('in',array(0,1)),
                        ),
                        '_logic'=>'or',
                    ),
                ),
                array(
                    'a.fxid'=>$memberID,
                    'oa.ifpack'=>array('in',array(0,1)),
                ),
                '_logic'=>'or',
            );
        }

        if($orderNum){
            $where['a.ordernum'] = $orderNum;
        }else{
            if ($landTitle) {
                $where['l.title'] = array("like", "%{$landTitle}%");
            }
            if ($noticeType !== null) {
                $where['a.stype'] = $noticeType;
            }
            if ($applyTime) {
                $where['a.stime'] = $applyTime;
            }
            if ($auditTime) {
                $where['a.dtime'] = $auditTime;
            }
            if ($auditStatus != null) {
                $where['a.dstatus'] = $auditStatus;
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
            $field  = array(
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
                't.mdetails',
            );
            $order  = array(
                'stime DESC',
                'dstatus ASC',
            );
            $map = $this->table($table)
                           ->join($join)
                           ->where($where)
                           ->field($field)
                           ->page($page)
                           ->limit($limit)
                           ->order($order);
            if($limit==1){
                $result = $map->find();
            }else{
                $result = $map->select();
            }
//            $this->test();
            return $result;
        }
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

    public function areUnderAudit(array $orders){
        $table = $this->_refundAuditTable;
        $where['ordernum'] = array('in',$orders);
        $where['dstatus']=0;
        $field = array(
          "id",
          "ordernum",
        );
        return $this->table($table)->where($where)->field($field)->select();
    }
}