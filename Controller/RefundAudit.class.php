<?php
/**
 * User: Fang
 * Time: 15:03 2016/3/11
 */

namespace Controller;

use Library\Controller;
use Model\Order\OrderTools;
use Model\Order\OrderTrack;
use Model\Order\RefundAuditModel;
use Model\Product\Ticket;

class RefundAudit extends Controller
{
    const MODIFY_CODE = 2;      //退款审核表中修改申请的stype值
    const CANCEL_CODE = 3;      //退款审核表中取消申请的stype值
    const APPLY_AUDIT_CODE = 10;      //订单追踪表中表示发起退款审核
    const OPERATE_AUDIT_CODE = 11;     //订单追踪表中表示退款审核已处理
    private $noticeURL = 'http://localhost/new/d/module/api/RefundNotice.php';

    /**
     * 判断订单是否需要退票审核
     *
     * @param int $orderNum
     * @param int $targetTnum
     * @param int $operatorID
     * @param int $circle 循环调用次数
     *
     * @return int
     */
    public function checkRefundAudit(
        $orderNum,
        $targetTnum = 0,
        $operatorID = 1,
        $circle = 0
    ) {
        $auditNeeded = 100; //100-默认不需要退票审核
        $modifyType  = $targetTnum == 0 ? 3 : 2;
        //检测传入参数
        $orderNum = intval(trim($orderNum));
        if ( ! $orderNum) {
            return (202); //订单号缺失或格式错误
        }

        $operatorID = intval(trim($operatorID));

        //获取订单信息
        $orderModel = new OrderTools();
        $orderInfo  = $orderModel->getOrderInfo($orderNum);
        if ( ! $orderInfo) {
            return (204); //订单号不存在
        }
        $orderDetail = $orderModel->getOrderDetail($orderNum);
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
            //自供自销的订单可自行取消
            if ($ticketInfo['apply_did'] == $operatorID
                && $modifyType == self::CANCEL_CODE
            ) {
                return 100;
            }
        } else {
            //对无需退款审核的订单需要作联票和套票判断
            //判断套票是否需要退票审核
            if ($orderExtend['ifpack']) {
                if ($orderExtend['ifpack'] == 1) {
                    //套票主票
                    $subOrders = $orderModel->getPackSubOrder($orderNum);
                    if ( ! $subOrders || ! is_array($subOrders)) {
                        return (207);
                    }//套票信息出错
                    foreach ($subOrders as $subOrder) {
                        $auditNeeded
                            = $this->checkRefundAudit($subOrder['orderid'],
                            $targetTnum, $operatorID);
                        if ($auditNeeded == 200) {//套票中有任一子票需要退票审核，则该套票需要审核
                            break;
                        }
                    }
                } else {
                    $refundModel = new RefundAuditModel();;
                    $mainOrderIsUnderAudit
                        = $refundModel->isUnderAudit($orderExtend['pack_order']);
                    if ($mainOrderIsUnderAudit) {
                        $auditNeeded = 200;
                    }
                }
            }
            //取消联票的情况 取消联票主票时，对应子票也都要取消；
            if ($circle == 0 && $orderDetail['concat_id']
                && $modifyType == self::CANCEL_CODE
            ) {
                //取消联票时候，要判断其他子票是否需要退票审核
                $mainOrder = $orderDetail['concat_id'];
                $subOrders = $orderModel->getLinkSubOrder($mainOrder);
                foreach ($subOrders as $subOrder) {
                    if ($subOrder['orderid'] != $orderNum) {
                        $auditNeeded
                            = $this->checkRefundAudit($subOrder['orderid'],
                            $targetTnum, $operatorID, 1);
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
     * 添加退票审核记录：
     * 只支持取消和修改，不支持撤销撤改
     *
     * @param     $orderNum
     * @param     $targetTicketNum
     * @param     $operatorID
     * @param int $source
     * @param int $requestTime
     *
     * @return int
     */
    public function addRefundAudit(
        $orderNum,
        $targetTicketNum,
        $operatorID,
        $source = 18,
        $requestTime = 0
    ) {
        //查询是否存在审核记录

        $refundModel = new RefundAuditModel();
        $underAudit  = $refundModel->isUnderAudit($orderNum);
        if ($underAudit) {
            return (240);//订单正在审核
        }

        //参数初始化
        $auditStatus = 0; //所有未审核记录的的dstatus都为0
        $action      = self::APPLY_AUDIT_CODE;
        $requestTime = ($requestTime) ? $requestTime : date('Y-m-d H:i:s');
        $modifyType  = $targetTicketNum == 0 ? self::CANCEL_CODE
            : self::MODIFY_CODE;

        $orderModel = new orderTools();
        $orderInfo  = $refundModel->getOrderInfoForAudit($orderNum);

        if ( ! $orderInfo || ! is_array($orderInfo)) {
            return (205);//订单信息不全
        }
        if ($orderInfo['aids']) {
            $auditorID = reset(explode(',', $orderInfo['aids']));
        } else {
            $auditorID = 1; //2016-3-27添加
        }

        //添加订单追踪记录
        $this->addRefundAuditOrderTrack(
            $orderNum,
            $source,
            $operatorID,
            $action,
            $auditStatus,
            null,
            $orderInfo);

        //添加审核记录
        $addResult = $refundModel->addRefundAudit($orderNum,
            $orderInfo['terminal'],
            $orderInfo['salerid'],
            $orderInfo['lid'],
            $orderInfo['tid'],
            $modifyType,
            $targetTicketNum,
            $operatorID,
            $auditStatus,
            $auditorID,
            $requestTime
        );

        if ( ! $addResult) {
            return (241);//数据添加失败
        }

        //套票主票
        if ($orderInfo['ifpack'] == 1) {

            $subOrders = $orderModel->getPackSubOrder($orderNum);
            if ( ! $subOrders || ! is_array($subOrders)) {
                return (207);//套票信息出错
            }

            foreach ($subOrders as $subOrder) {

                //计算子票变更后票数
                if ($targetTicketNum == 0) {
                    $targetSubOrderTnum = 0;
                } else {
                    $subOrderInfo
                                        = $orderModel->getOrderInfo($subOrder['orderid']);
                    $targetSubOrderTnum = floor($subOrderInfo['tnum']
                                                * ($targetTicketNum
                                                   / $orderInfo['tnum']));
                }

                //子票添加订单追踪记录
                $this->addRefundAuditOrderTrack(
                    $subOrder['orderid'],
                    $source,
                    $operatorID,
                    $action,
                    $auditStatus,
                    $targetSubOrderTnum);

                //子票添加审核记录
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
        }

        return 200;//数据添加成功
    }

    /**
     * 更新审核记录
     *
     * @param $orderNumber
     * @param $auditResult
     * @param $auditNote
     * @param $operatorID
     * @param $auditTime
     * @param $auditID
     * @param $source
     *
     * @return int
     */
    public function updateRefundAudit(
        $orderNumber,
        $auditResult,
        $auditNote,
        $operatorID,
        $auditTime,
        $auditID = 0
    ) {
        //检查传入参数
        if ( ! in_array($auditResult, [1, 2])) {
            return 250; //审核结果只能是同意或拒绝
        }

        //参数初始化
        $refundModel = new RefundAuditModel();
        $result = 241;
        $orderInfo = $refundModel->getOrderInfoForAudit($orderNumber);
        if ( ! $orderInfo) {
            return 205;//订单信息不全
        }
        $orderModel = new OrderTools();
        //套票的特殊处理
        switch ($orderInfo['ifpack']) {
            case 1://套票主票
                return 255; //套票主票无人工审核权限
            case 2://套票子票
                //更新当前门票记录
                $result = $this->updateAudit(
                    $refundModel,
                    2,//ifpack
                    $orderNumber,
                    $auditResult,
                    $auditNote,
                    $operatorID,
                    $auditTime,
                    $auditID);
                if ( ! $result) {
                    return 241;
                }
                $mainOrder          = $orderInfo['pack_order'];
                $ordersAutoUpdate   = $orderModel->getPackSubOrder($mainOrder);
                $ordersAutoUpdate[] = array('orderid' => $mainOrder);
                switch ($auditResult) {
                    case 1://同意退票
                        //检查是否所有子票都通过审核
                        if ($refundModel->hasUnAuditSubOrder($mainOrder)) {
                            break;
                        }
                        //自动更新主票审核记录
                        foreach ($ordersAutoUpdate as $order) {
                            if ( ! $refundModel->isUnderAudit($order['orderid'])) {
                                continue;
                            }
                            $ifpack = ($order['orderid']==$mainOrder) ? 1 : 2;
                            $result = $this->updateAudit(
                                $refundModel,
                                $ifpack,
                                $order['orderid'],
                                $auditResult,
                                '系统:全部子票通过退票审核',
                                1);
                        }
                        break;
                    case 2:
                        foreach ($ordersAutoUpdate as $order) {
                            if ( ! $refundModel->isUnderAudit($order['orderid'])) {
                                continue;
                            }
                            $ifpack = ($order['orderid']==$mainOrder) ? 1 : 2;
                            $result = $this->updateAudit(
                                $refundModel,
                                $ifpack,
                                $order['orderid'],
                                $auditResult,
                                '系统:部分子票的退票申请被拒绝',
                                1);
                        }
                        break;
                }
                break;
            default://非套票
                if ($orderInfo['concat_id']) {
                    $mainOrder  = $orderInfo['concat_id'];
                    $subOrders  = $orderModel->getLinkSubOrder($mainOrder);
                    foreach ($subOrders as $subOrder) {
                        $subOrderID = $subOrder['orderid'];
                            $result = $this->updateAudit(
                                $refundModel,
                                0,//ifpack
                                $subOrderID,
                                $auditResult,
                                $auditNote,
                                $operatorID
                            );
                    }
                }else{
                    $result = $this->updateAudit(
                        $refundModel,
                        0,
                        $orderNumber,
                        $auditResult,
                        $auditNote,
                        $operatorID,
                        $auditTime,
                        $auditID);
                }
        }
        return $result; //操作成功
    }

    /**
     * @param     $refundModel
     * @param     $ifpack
     * @param     $orderNum
     * @param     $auditResult
     * @param     $auditNote
     * @param int $operatorID
     * @param int $auditTime
     * @param int $auditID
     *
     * @return mixed
     */
    private function updateAudit(
        $refundModel,
        $ifpack,
        $orderNum,
        $auditResult,
        $auditNote,
        $operatorID = 1,
        $auditTime = 0,
        $auditID = 0
    ) {
        //参数初始化
        if(!$refundModel){
            $refundModel =  new RefundAuditModel();
        }
        $action = self::OPERATE_AUDIT_CODE;
        $source = 16;
        $return = $refundModel -> updateAudit($orderNum,$auditResult,$auditNote,$operatorID,$auditTime,$auditID);
        if($auditResult == 2){
            $this->addRefundAuditOrderTrack($orderNum, $source, $operatorID,
                $action, $auditResult, null);
            if($ifpack != 2){
                $this->noticeAuditResult('reject',$orderNum,null,$auditResult);
            }
        }
        $result = $return ? 200 : 241;
        return $result;
    }
    /**
     * 获取撤改审核列表数据
     *
     * @param $operatorID
     * @param $landTitle
     * @param $noticeType
     * @param $applyTime
     * @param $auditStatus
     * @param $auditTime
     * @param $page
     * @param $limit
     */
    public function getAuditList(
        $operatorID = null,
        $landTitle = null,
        $noticeType = null,
        $applyTime = null,
        $auditStatus = null,
        $auditTime = null,
        $orderNum = null,
        $page = 1,
        $limit = 20
    ) {
        //参数初始化
        $limit       = ($limit && is_numeric($limit)) ? $limit : 20;
        $page        = ($page && is_numeric($page)) ? $page : 1;
        $r           = array();
        $refundModel = new RefundAuditModel();;
        //获取记录详情
        $refundRecords = $refundModel->getAuditList($operatorID, $landTitle,
            $noticeType,
            $applyTime, $auditStatus, $auditTime, $orderNum, false, $page,
            $limit);
        if (is_array($refundRecords) && count($refundRecords) > 0) {
            foreach ($refundRecords as $row) {
                $row['action'] = false;
                $row['repush'] = false;
                if (($row['apply_did'] == $operatorID || $operatorID == 1)
                    && ! $row['mdetails']
                    && $row['ifpack'] != 1
                ) {
                    $row['action'] = true;
                    if ($row['dcodeURL'] && in_array($row['dstatus'], [1, 2])) {
                        $row['repush'] = true;
                    }
                }
                unset($row['dcodeURL']);
                unset($row['mdetails']);
                $r[] = $row;
            }
            //获取记录总数
            $rnum  = $refundModel->getAuditList($operatorID, $landTitle,
                $noticeType,
                $applyTime, $auditStatus, $auditTime, $orderNum, true, $page,
                $limit);
            $total = $rnum['count(*)'];
        } else {
            $total = 0;
        }
        $data = array(
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'audit_list' => $r,
        );
        $this->ajaxReturn(200, $data);
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
    private function checkUseStatus(
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
                if ($modifyType == self::MODIFY_CODE) {
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
    private function checkPayStatus($payMode, $payStatus)
    {
        $payMode   = intval(trim($payMode));
        $payStatus = intval(trim($payStatus));
        //检查支付方式
        $onlinePay = array(1, 5, 7, 8);
        if (in_array($payMode, $onlinePay)) {
            return (100);//在线支付订单:无需退票审核
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
            208 => '传入参数有误',
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
            242 => '联票子票无法单独取消',
            243 => '套票子票审核成功',
            250 => '审核参数出错', //审核结果只能是同意或拒绝
            251 => '备注信息不可为空',
            252 => '审核时操作失败',
            253 => '未知错误',
            254 => '子票未全部通过审核，主票无法变更',
            255 => '套票主票不支持人工审核，请等待系统自动审核',
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
     *
     * @return int
     */
    public function noticeAuditResult(
        $action,
        $ordernum,
        $targetTicketNum,
        $auditResult
    ) {
        if(!is_numeric($targetTicketNum)){
            $refundModel = new RefundAuditModel();
            $auditInfo = $refundModel->getAuditedTnum($ordernum);
            $targetTicketNum = $auditInfo['tnum'];
        }
        $data   = array(
            'action'   => $action,
            'ordernum' => $ordernum,
            'num'     => $targetTicketNum,
            'dstatus'  => $auditResult,
        );
        $url    = $this->noticeURL;
        $result = $this->raw_post($url, $data);
        if ($result) {
            return 200;
        } else {
            return 252;
        }
    }

    /**
     * 返回json格式的数据
     *
     * @param mixed  $code
     * @param string $data
     * @param string $msg
     * @param string $type
     * @param int    $json_option
     *
     * @return string
     */
    public function ajaxReturn(
        $code,
        $data = '',
        $msg = '',
        $type = 'JSON',
        $json_option = 0
    ) {
        $return = array(
            'code' => $code,
            'data' => $data,
            'msg'  => $msg,
        );

        parent::ajaxReturn($return, $type, $json_option);
    }

    /**
     * 添加订单追踪记录
     *
     * @param       $orderNum
     * @param       $source
     * @param       $operatorID
     * @param       $action 10-提交退票请求 11-操作退票审核
     * @param       $auditResult
     * @param       $targetNum
     * @param array $orderInfo
     */
    public function addRefundAuditOrderTrack(
        $orderNum,
        $source,
        $operatorID,
        $action,
        $auditResult,
        $targetNum = null,
        array $orderInfo = []
    ) {
        $refundModel = new RefundAuditModel();
        if ( ! count($orderInfo) > 0) {
            $orderInfo = $refundModel->getOrderInfoForAudit($orderNum);
        }

        $trackModel      = new OrderTrack();
        $remainTicketNum = ($orderInfo['status'] == 7)
            ? $refundModel->getRemainTicketNumber($orderNum)
            : $orderInfo['tnum'];
        if ($auditResult == 1) {
            $operateTicketNum = $remainTicketNum - $targetNum;
            $remainTicketNum  = $targetNum;
        } else {
            $operateTicketNum = 0;
        }
        $person_id = is_numeric($orderInfo['person_id']) ? $orderInfo['person_id'] : 0;
        $trackModel->addTrack(
            $orderNum,
            $action, //拒绝退票审核
            $orderInfo['tid'],
            $operateTicketNum,
            $remainTicketNum,
            $source,
            $orderInfo['terminal'],
            $orderInfo['terminal'],
            $person_id ,
            $operatorID,
            $orderInfo['salerid']
        );
    }
}