<?php
/**
 * User: Fang
 * Time: 15:03 2016/3/11
 */

namespace Action;

use Library\Controller;
use Model\Order\OrderTools;
use Model\Order\OrderTrack;
use Model\Order\RefundAudit;
use Model\Product\Ticket;

class RefundAuditAction extends BaseAction
{
    const MODIFY_CODE_IN_AUDIT         = 2;      //退款审核表中修改申请的stype值
    const CANCEL_CODE_IN_AUDIT         = 3;      //退款审核表中取消申请的stype值
    const INNER_SOURCE_IN_TRACK        = 16;     //订单追踪表中来自内部接口的请求
    const UNDEFINED_SOURCE_IN_TRACK    = 18;     //订单追踪表中表示未定义的请求来源
    const APPLY_AUDIT_CODE_IN_TRACK    = 9;      //订单追踪表中表示发起退款审核
    const OPERATE_AUDIT_IN_TRACK_TABLE = 10;     //订单追踪表中表示退款审核已处理
    private $noticeURL = 'http://localhost/module/api/RefundNotice.php';

    /**
     * 判断订单是否需要退票审核
     *
     * @param int $orderNum
     * @param int $modifyType
     * @param int $operatorID
     *
     * @return int
     */
    public function checkRefundAudit(
        $orderNum,
        $modifyType,
        $operatorID
    ) {
        $auditNeeded = 100; //100-默认不需要退票审核

        //检测传入参数
        $orderNum = intval(trim($orderNum));
        if ( ! $orderNum) {
            return (202); //订单号缺失或格式错误
        }

        $operatorID = intval(trim($operatorID));
        if ( ! $operatorID) {
            return (203);//操作人ID缺失或格式错误
        }

        //获取订单信息
        $orderModel = new OrderTools();
        $orderInfo  = $orderModel->getOrderInfo($orderNum);
        if ( ! $orderInfo) {
            return (204); //订单号不存在
        }
        $orderDetail = $orderModel->getOrderDetail($orderNum, 1);
        if ( ! $orderDetail || ! is_array($orderDetail)) {
            return (205);//订单信息不全
        }
        $orderExtend = $orderModel->getOrderAddonInfo($orderNum);
        if ( ! $orderExtend || ! is_array($orderExtend)) {
            return (205);//订单信息不全
        }
        //获取票类信息
        $tid         = $orderInfo['tid'];
        $ticketModel = new Ticket();
        $ticketInfo  = $ticketModel->getTicketInfoById($tid);
        if ( ! $ticketInfo) {
            return (206); //对应门票不存在
        }

        //对需要审核的订单，需判断是否满足订单变更条件
        if ($ticketInfo['refund_audit'] != 0) {
            $auditNeeded = 200;//需要退票审核
            //检查订单使用状态
            $result = $this->checkUseStatus($orderInfo['status'], $modifyType,
                $ticketInfo['apply_did'], $orderInfo['aid']);
            if ($result != 200) {
                return $result;
            }
            //检查订单支付状态
            $result = $this->checkPayStatus($orderInfo['paymode'],
                $orderDetail['pay_status']);
            if ($result != 200) {
                return $result;
            }
            //检查取消的发起者是否是末级分销商
            //套票子票的审核判断不考虑发起人，因其依赖于主票属性，套票子票并不能单独取消，
            if ($orderDetail['aids'] && $orderExtend['ifpack'] != 2) {
                $aids = explode(',', $orderDetail['aids']);
                if ($modifyType == self::CANCEL_CODE_IN_AUDIT) {
                    if ($operatorID != current($aids)
                        && $operatorID != end($aids)
                    ) {
                        return (230);//中间分销商不允许取消订单
                    }
                }
            }
        } else {
            //对无需退款审核的订单需要作联票和套票判断
            //判断套票是否需要退票审核
            if ($orderExtend['ifpack'] == 1) {//套票主票
                $subOrders = $orderModel->getPackageSubOrder($orderNum);
                if ( ! $subOrders || ! is_array($subOrders)) {
                    return (207);
                }//套票信息出错
                foreach ($subOrders as $subOrder) {
                    $auditNeeded = $this->checkRefundAudit($subOrder['orderid'],
                        $modifyType, $operatorID);
                    if ($auditNeeded == 200) {//套票中有任一子票需要退票审核，则该套票需要审核
                        break;
                    }
                }
            }

            //取消联票主票的时候，要判断对应子票是否需要退票审核
            if ($orderNum == $orderDetail['concat_id']
                && $modifyType == self::CANCEL_CODE_IN_AUDIT
            ) {
                $subOrders = $orderModel->getLinkSubOrder($orderNum);
                foreach ($subOrders as $subOrder) {
                    if ($subOrder['orderid'] != $orderNum) {
                        $auditNeeded
                            = $this->checkRefundAudit($subOrder['orderid'],
                            $modifyType, $operatorID);
                        if ($auditNeeded == 200) {
                            break;
                        }
                    }
                }
            }
        }

        return $auditNeeded;
    }

    /**
     * 添加退票审核记录
     *
     * @param int     $orderNum
     * @param int     $targetTicketNum
     * @param int     $operatorID
     * @param int $source 操作来源
     * @param   $requestTime
     *
     * @return int
     */
    public function addRefundAudit(
        $orderNum,
        $targetTicketNum,
        $operatorID,
        $source = self::UNDEFINED_SOURCE_IN_TRACK,
        $requestTime = 0
    ) {

//        $this->writeLog(array($orderNum, $targetTicketNum,$operatorID),'refund_0321');
        //参数初始化
        $addResult = false;

        $modifyType = $targetTicketNum == 0 ? self::CANCEL_CODE_IN_AUDIT
            : self::MODIFY_CODE_IN_AUDIT;

        //1 获取订单信息
        $refundModel = new RefundAudit();
        $underAudit  = $refundModel->isUnderAudit($orderNum);
        if ($underAudit) {
            return (240);//订单正在审核
        }
        $orderInfo = $refundModel->getOrderInfoForAudit($orderNum);
        if ( ! $orderInfo || ! is_array($orderInfo)) {
            return (205);//订单信息不全
        }
        $auditorID     = current(explode(',', $orderInfo['aids']));
        $operateStatus = $orderInfo['mdetails'] ? 4 : 0; //需要第三方平台审核的操作状态默认为4
        //添加订单追踪记录
        $trackModel = new OrderTrack();
        $trackModel->addTrack(
            $orderNum,
            self::APPLY_AUDIT_CODE_IN_TRACK,
            $orderInfo['tid'],
            $orderInfo['tnum'],
            $targetTicketNum,
            $source,
            $orderInfo['terminal'],
            0,
            0,
            $operatorID);
        //2 添加审核记录
        $orderModel = new orderTools();
        if ($orderInfo['ifpack'] == 1) {//套票主票
            $addResult = $refundModel->addRefundAudit(
                $orderNum,
                $orderInfo['terminal'],
                $orderInfo['salerid'],
                $orderInfo['lid'],
                $orderInfo['tid'],
                $modifyType,
                $targetTicketNum,
                $operatorID,
                $operateStatus,
                $auditorID
            );

            $subOrders = $orderModel->getPackageSubOrder($orderNum);
            if ( ! $subOrders || ! is_array($subOrders)) {
                return (207);//套票信息出错
            }
            foreach ($subOrders as $subOrder) {
                if ($targetTicketNum == 0) {
                    $targetSubOrderTnum = 0;
                } else {
                    $subOrderInfo
                                        = $orderModel->getOrderInfo($subOrder['orderid']);
                    $targetSubOrderTnum = floor($subOrderInfo['tnum']
                                                * ($targetTicketNum
                                                   / $orderInfo['tnum']));
                }
                $addSubOrder = $this->addRefundAudit(
                    $subOrder['orderid'],
                    $targetSubOrderTnum,
                    $operatorID,
                    $source,
                    $requestTime);
                if ($addSubOrder == 240 || $addSubOrder == 200) {
                    continue;
                } else {
                    return $addSubOrder;
                }
            }
        } elseif ($orderInfo['concat_id'] == $orderNum
                  && $modifyType == self::CANCEL_CODE_IN_AUDIT
        ) {
            $addResult = $refundModel->addRefundAudit(
                $orderNum,
                $orderInfo['terminal'],
                $orderInfo['salerid'],
                $orderInfo['lid'],
                $orderInfo['tid'],
                $modifyType,
                $targetTicketNum,
                $operatorID,
                $operateStatus,
                $auditorID
            );
            //联票主票取消时所有子票都一并取消
            $subOrders = $orderModel->getLinkSubOrder($orderNum);
            foreach ($subOrders as $subOrder) {
                if ($subOrder['orderNum'] != $orderNum) {
                    $addSubOrder = $this->addRefundAudit($subOrder['orderid'],
                        $targetTicketNum, $orderInfo, $source, $requestTime);
                    if ($addSubOrder == 240 || $addSubOrder == 200) {
                        continue;
                    } else {
                        return $addSubOrder;
                    }
                }
            }
        } else {
            if ( ! $orderInfo['refund_audit']) {
                $addResult = $refundModel->addRefundAudit(
                    $orderNum,
                    $orderInfo['terminal'],
                    $orderInfo['salerid'],
                    $orderInfo['lid'],
                    $orderInfo['tid'],
                    $modifyType,
                    $targetTicketNum,
                    $operatorID,
                    1,
                    1,
                    date('Y-m-d H:i:s'),
                    '系统自动审核',
                    date('Y-m-d H:i:s')
                );
                $trackModel->addTrack(
                    $orderNum,
                    self::OPERATE_AUDIT_IN_TRACK_TABLE,
                    $orderInfo['tid'],
                    $orderInfo['tnum'],
                    $targetTicketNum,
                    $source,
                    $orderInfo['terminal'],
                    0,
                    0,
                    $operatorID);
            } else {
                $addResult = $refundModel->addRefundAudit(
                    $orderNum,
                    $orderInfo['terminal'],
                    $orderInfo['salerid'],
                    $orderInfo['lid'],
                    $orderInfo['tid'],
                    $modifyType,
                    $targetTicketNum,
                    $operatorID,
                    $operateStatus,
                    $auditorID
                );
            }
        }
        if ( ! $addResult) {
            return (241);//数据添加失败
        }

        return 200;//数据添加成功
    }

    /**
     * 更新审核记录
     *
     * @param int $auditID
     * @param int $auditResult
     * @param string $auditNote
     * @param int $orderNum
     * @param int $operatorID
     * @param int $auditTnum
     *
     * @return int
     */
    public function update_audit(
        $auditID,
        $auditResult,
        $auditNote,
        $orderNum,
        $operatorID,
        $auditTnum
    ) {
        $refundModel = new RefundAudit();
        $auditInfo   = $refundModel->getOrderInfoForAudit($orderNum);
        $trackModel  = new OrderTrack();
        $trackModel->addTrack(
            $orderNum,
            self::OPERATE_AUDIT_IN_TRACK_TABLE,
            $auditInfo['tid'],
            $auditInfo['tnum'],
            $auditTnum,
            self::INNER_SOURCE_IN_TRACK,
            $auditInfo['terminal'],
            0,
            0,
            $operatorID);
        if ($auditID == 0) {
            return (205); //订单信息不全
        }
        if ($auditResult == 0) {
            return (250); //请选择审核结果
        }
        if ($auditNote == '') {
            return (251);//备注信息不可为空
        }
        $refundModel = new RefundAudit();
        $result      = $refundModel->updateAudit($auditID, $auditResult,
            $auditNote, $orderNum, $operatorID);
        if ($result) {
            return (200);
        } else {
            return (241);//数据更新失败,请联系管网站管理员
        }
    }

    /**
     * 检查订单使用状态
     *
     * @param $useStatus  0未使用|1已使用|2已过期|3被取消|4凭证码被替代|5被终端修改|6被终端撤销|7部分使用
     * @param $modifyType 0撤改|1撤销|2修改|3取消
     * @param $ticketAid
     * @param $operatorId
     *
     * @return int
     */
    public function checkUseStatus(
        $useStatus,
        $modifyType,
        $ticketAid,
        $operatorId
    ) {
        $useStatus = intval($useStatus);
        switch ($useStatus) {
            case 1:
                return (210);//订单已使用:不可取消或修改
                break;
            case 2:
                if ($modifyType == self::MODIFY_CODE_IN_AUDIT) {
                    return (211);//订单已过期:不允许修改
                } else {
                    if ($operatorId != $ticketAid) {
                        return (212);//订单已过期：只有供应商可以取消
                    } else {
                        return (100);//供应商取消已过期订单：无需退票审核
                    }
                }
                break;
            case 3:
                return (213);//订单已取消:不可再取消或修改
                break;
            case 5:
                return (214);//订单已被终端撤改:不可取消或修改
                break;
            case 6:
                return (215);//订单已被终端撤销:不可取消或修改
                break;
            default://(0-未使用 7-部分使用 [4-不处理])
                continue;
        }

        return (200);
    }

    /**
     *
     * 检查支付方式和支付状态
     *
     * @param int $payMode   支付方式：1在线支付|2授信支付|3自供自销|4到付|5微信支付|7银联支付|8环迅支付
     * @param int $payStatus 0景区到付|1已成功|2未支付
     *
     * @return int
     */
    public function checkPayStatus($payMode, $payStatus)
    {
        $payMode   = intval(trim($payMode));
        $payStatus = intval(trim($payStatus));
        //检查支付方式
        $onlinePay = array(1, 5, 7, 8);
        if (in_array($payMode, $onlinePay)) {
            return (100);//在线支付订单:无需退票审核
        } elseif ($payMode == 3) {
            return (100);//自供自销订单:无需退票审核
        } elseif ($payMode == 4) {
            return (100);//到付订单:无需退票审核
        }
        //检查支付状态
        if ($payStatus == 2) {
            return (100);//未支付订单:无需退票审核
        }

        return (200);
    }

//返回接口数据
    public function apiReturn($code, $data = [], $msg = '')
    {
        $code    = intval($code);
        $msgList = array(
            100 => '无需退票审核',
            200 => '操作成功',
            201 => '缺少传入参数',
            202 => '订单号缺失或格式错误',
            203 => '操作人ID缺失或格式错误',
            204 => '订单号不存在',
            205 => '订单信息不全',
            206 => '对应门票不存在',
            207 => '套票信息出错',
            210 => '订单已使用:不可取消或修改',
            211 => '订单已过期:不可修改',
            212 => '订单已过期:非供应商不可取消',
            213 => '订单已取消:不可再取消或修改',
            214 => '订单已被终端撤改:不可取消或修改',
            215 => '订单已被终端撤销:不可取消或修改',
            221 => '余票不足',
            230 => '中间分销商不允许取消订单',
            240 => '订单已在审核中，请您耐心等待',
            241 => '数据更新失败,请联系管网站管理员',
            250 => '请选择审核结果',
            251 => '备注信息不可为空',
            252 => '审核时操作失败',
            253 => '未知错误',
        );
        if ( ! $msg && array_key_exists($code, $msgList)) {
            $msg = $msgList[$code];
        }
        if ( ! $msg) {
            $msg = '';
        }
        exit(json_encode(array(
            "code" => $code,
            "data" => $data,
            "msg"  => $msg,
        )));
    }

    /**
     * @param int $action
     * @param int $ordernum
     * @param int $targetTicketNum
     * @param int $auditResult 审核结果
     * @return int
     */
    public function noticeAuditResult($action,$ordernum,$targetTicketNum,$auditResult){
        $data = array(
          'action' => $action,
          'ordernum' => $ordernum,
          'tnum' =>$targetTicketNum,
          'dstatus' => $auditResult,
        );
        $url = $this->noticeURL;
        $result = $this->raw_post($url,$data);
        if($result){
            return 200;
        }else{
            return 252;
        }
    }
}