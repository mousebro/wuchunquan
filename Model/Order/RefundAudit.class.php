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
    private $_orderAppendixTable = 'uu_order_addon';
    private $_landTable = 'uu_land';
    private $_ticketTable = 'uu_jq_ticket';
    private $_orderDetailTable = 'uu_order_fx_details';

    /**
     * @param int    $orderNum    平台订单号
     * @param int    $terminal    终端号
     * @param int    $salerid     景区6位编号
     * @param int    $lid         景区id
     * @param int    $tid         门票id
     * @param int    $modifyType  修改类型 0-撤改 1-撤销 2-修改 3-取消
     * @param int    $targetTnum  变更后票数
     * @param int    $operatorID  退票发起人
     * @param int    $dstatus     退票审核状态 0-未处理 1-同意 2-拒绝 3-等待第三方自动审核
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
        $operatorID,
        $dstatus = 0,
        $auditorID=0,
        $requestTime = 0,
        $auditNote='',
        $auditTime=0
    ) {
        $table = $this->_refundAuditTable;
        $data  = [
            'ordernum' => $orderNum,
            'terminal' => $terminal,
            'salerid'  => $salerid,
            'lid'      => $lid,
            'tid'      => $tid,
            'stype'    => $modifyType,
            'tnum'     => $targetTnum,
            'dstatus'  => $dstatus,        /*状态0未操作1同意2拒绝*/
            'stime'    => ($requestTime) ? $requestTime : date('Y-m-d H:i:s'),
            'fxid'     => $operatorID, //申请发起人
            'dadmin'=> $auditorID
        ];
        if($auditTime) $data['dtime']=$auditTime;
        if($auditNote) $data['reason']=$auditNote;
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
            'l.terminal',
            'oa.ifpack',
            'o.tnum',
            't.mdetails',
            //            'o.ordernum',
            //            'o.status',
            //            'l.p_type',
            't.refund_audit',
            'od.concat_id',
            'od.aids'
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
    public function isUnderAudit($orderNum)
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