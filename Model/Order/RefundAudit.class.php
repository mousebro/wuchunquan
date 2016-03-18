<?php
/**
 * User: Fang
 * Time: 15:32 2016/3/11
 */

namespace Model\Order;


use Library\Model;

class RefundAudit extends Model
{
    private $_refundAuditTable = 'uu_order_terminal_change';
    private $_orderTable = 'uu_ss_order';
    private $_landTable = 'uu_land';
    private $_ticketTable = 'uu_jq_ticket';

    /**
     * 添加订单变更审核记录
     *
     * @param int $orderNum    平台订单号
     * @param int $terminal    终端号
     * @param int $salerid     景区6位编号
     * @param int $lid         景区id
     * @param int $tid         门票id
     * @param int $targetTnum  修改后票数
     * @param int $modifyType  修改类型 0-撤改 1-撤销 2-修改 3-取消
     * @param int $operatorID  退票发起人
     * @param int $dstatus     退票审核状态 0-未处理 1-同意 2-拒绝
     * @param int $requestTime 申请时间
     *
     * @return mixed
     */
    public function addRefundAudit(
        $orderNum,
        $terminal,
        $salerid,
        $lid,
        $tid,
        $targetTnum,
        $modifyType,
        $operatorID,
        $dstatus = 0,
        $requestTime = 0
    ) {
        $table = $this->_refundAuditTable;
        $data  = [
            'ordernum' => $orderNum,
            'terminal' => $terminal,
            'salerid'  => $salerid,
            'lid'      => $lid,
            'tid'      => $tid,
            'tnum'     => $targetTnum,
            'dstatus'  => $dstatus,        /*状态0未操作1同意2拒绝*/
            'stime'    => ($requestTime) ? $requestTime : date('Y-m-d H:i:s'),
            'stype'    => $modifyType,
            'fxid'     => $operatorID, //申请发起人
        ];

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
            //            "join {$this->_ticketTable} AS t ON o.tid=t.id"
        );
        $field = array(
            'o.salerid',
            'o.lid',
            'o.tid',
            'l.terminal',
            //            'o.ordernum',
            //            'o.status',
            //            'l.p_type',
            //            'o.tnum',
            //            't.refund_audit',
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
     * @param int $orderNum
     * @param int $modifyType
     *
     * @return int
     */
    public function isUnderAudit($orderNum, $modifyType)
    {
        $table  = $this->_refundAuditTable;
        $where  = array(
            'ordernum' => $orderNum,
            'dstatus'  => 0,
            //            'stype'   => $modifyType,
        );
        $field  = ['id'];
        $result = $this->table($table)->where($where)->field($field)->find();

        return $result;
    }


    /**
     * 更新退款审核结果
     *
     * @param     $auditID
     * @param     $auditResult
     * @param     $auditNote
     * @param     $orderNum
     * @param     $operatorID
     * @param int $auditTime
     *
     * @return bool
     */
    public function updateAudit(
        $auditID,
        $auditResult,
        $auditNote,
        $orderNum,
        $operatorID,
    $auditTime=0
    ) {
        $table  = $this->_refundAuditTable;
        $where  = array(
            'id'       => $auditID,
            'ordernum' => $orderNum,
        );
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

}