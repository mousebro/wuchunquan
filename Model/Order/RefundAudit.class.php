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
    private $_orderTable       = 'uu_ss_order';
    private $_landTable        = 'uu_land';
    private $_ticketTable      = 'uu_jq_ticket';

    /**
     * 添加订单变更审核记录
     *
     * @param int $orderNum   平台订单号
     * @param int $targetTnum 修改后票数
     * @param int $modifyType 修改类型 0-撤改 1-撤销 2-修改 3-取消
     * @param int $requestTime
     *
     * @return int $lastInsertId
     */
    public function addRefundAudit($orderNum,$targetTnum,$modifyType = null,$operatorID,$requestTime = 0, $callTnum)
    {
        $orderInfo = $this->getOrderInfoForAudit($orderNum);
        if ( ! is_array($orderInfo)) {
            return false;
        }
        if ($modifyType === null) {
            if ($orderInfo['status'] == 1) {
                $modifyType = ($targetTnum == 0) ? 1 : 0;
            } else {
                $modifyType = ($targetTnum == 0) ? 3 : 2;
            }
        }
        $dstatus = ($orderInfo['refund_audit']==0 && strtoupper($orderInfo['p_type'])!= 'F') ? 1 : 0; //如果门票本身不需要退款审核时，则自动设置为同意退票（用于套票）
        $table        = $this->_refundAuditTable;
        $data         = [
            'ordernum' => $orderInfo['ordernum'],
            'terminal' => $orderInfo['terminal'],
            'salerid'  => $orderInfo['salerid'],
            'lid'      => $orderInfo['lid'],
            'tid'      => $orderInfo['tid'],
            'tnum'     => $targetTnum,
            'dstatus'  => $dstatus, /*状态0未操作1同意2拒绝*/
            'stime'    => ($requestTime) ? $requestTime : date('Y-m-d H:i:s'),
            'stype'    => $modifyType,
            'fxid'=>$operatorID, //字段含义不详
        ];
        if($dstatus==1){
            $data['reason'] = '系统自动审核';
            $data['dadmin'] = 1;
            $data['dtime'] = date('Y-m-d H:i:s');
        }
        $lastInsertId = $this->table($table)->data($data)->add();
        if($callTnum) {
            return array($targetTnum,$orderInfo['tnum']);
        }else{
            return $lastInsertId;
        }
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
        $table  = "{$this->_orderTable} AS o";
        $where  = ['o.ordernum' => $orderNum];
        $join   = array(
            "join {$this->_landTable} AS l ON o.lid=l.id",
            "join {$this->_ticketTable} AS t ON o.tid=t.id");
        $field  = array(
            'o.ordernum',
            'o.salerid',
            'o.lid',
            'o.tid',
            'o.status',
            'l.terminal',
            'l.p_type',
            'o.tnum',
            't.refund_audit',
        );
        $result = $this->table($table)
                       ->where($where)
                       ->join($join)
                       ->field($field)
                       ->find();
        return $result;
    }

    /**
     * 查询订单是否处于退款审核状态: 单个订单同一种变更类型只允许提交一次请求
     *
     * @param int $orderNum
     * @param int $modifyType
     *
     * @return int
     */
    public function underAudit($orderNum, $modifyType)
    {
        $table  = $this->_refundAuditTable;
        $where  = array(
            'ordernum' => $orderNum,
//            'dstatus' => 0,
            'stype'   => $modifyType,
        );
        $field  = ['id'];
        $result = $this->table($table)->where($where)->field($field)->find();
//        $this->test();
        return $result;
    }

    /**
     * 更新退款审核结果
     *
     * @param $auditID
     * @param $auditResult
     * @param $auditNote
     * @param $orderNum
     * @param $operatorID
     *
     * @return bool
     */
    public function updateAudit($auditID, $auditResult, $auditNote, $orderNum, $operatorID)
    {
        $table = $this -> _refundAuditTable;
        $where = array(
          'id'=>$auditID,
          'ordernum' => $orderNum,
        );
        $data = array(
          'dstatus' => $auditResult,
          'reason' => $auditNote,
          'dadmin' => $operatorID,
          'dtime' => date('Y-m-d H:i:s'),
        );
        $result = $this->table($table)->where($where)->data($data)->save();
        //        $this->test();
        return $result;
    }
    //todo：判断订单是否是套票
    //打印sql语句
    private function test(){
        $str = $this -> getLastSql();
        print_r($str);
    }
}