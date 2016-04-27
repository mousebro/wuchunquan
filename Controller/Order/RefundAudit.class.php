<?php
/**
 * User: Fang
 * Time: 15:03 2016/3/11
 */

namespace Controller\Order;

use Library\Controller;
use Library\Exception;
use Model\Order\OrderTools;
use Model\Order\OrderTrack;
use Model\Order\RefundAuditModel;

class RefundAudit extends Controller
{
    const MODIFY_CODE = 2;      //退款审核表中修改申请的stype值
    const CANCEL_CODE = 3;      //退款审核表中取消申请的stype值
    const APPLY_AUDIT_CODE = 11;      //订单追踪表中表示发起退款审核
    const OPERATE_AUDIT_CODE = 10;     //订单追踪表中表示退款审核已处理
    private $noticeURL = 'http://localhost/new/d/module/api/RefundNotice.php';


    /**
     * 检查平台上的门票是否需要退票审核（针对联票优化独立出来)
     */
    public function checkRefundAuditFromWeb()
    {
        $operatorID  = I('session.sid');
        $orderNum    = I('param.ordernum'); //订单号
        $modifyType  = I('param.stype');    //修改类型
        $targetTnums = [];
        if ( ! $operatorID) {
            $this->apiReturn(203);
        }

        if ($modifyType != 2 && $modifyType != 3) {
            $this->apiReturn(208);
        }

        //格式化订单号与修改后票数
        if ($modifyType == self::MODIFY_CODE) {
            $targetTnums = I('param.tids');     //修改后票数  [订单号]=变更后票数
        } elseif ($modifyType == self::CANCEL_CODE) {
            $orderModel = new OrderTools();
            if ($subOrders = $orderModel->getLinkSubOrder($orderNum)) {
                foreach ($subOrders as $subOrder) {
                    $targetTnums[$subOrder['orderid']] = 0;
                }
            } else {
                $targetTnums[$orderNum] = 0;
            }
        }
        if ( ! is_array($targetTnums) || count($targetTnums) == 0) {
            $this->apiReturn(208);
        }
        //检验所有门票的退票审核属性，需要退票的要查出票类名称
        $checkCode = 100;
        foreach ($targetTnums as $subOrder => $subOrderTnum) {
            $subCheckCode = $this->checkRefundAudit($subOrder,
                $subOrderTnum, $operatorID, $modifyType);
            if ($subCheckCode == 100) {
                continue;
            } elseif ($subCheckCode == 200) {
                $ticketTitle    = $this->getTicketTitle($subOrder);
                $ticketTitles[] = $ticketTitle['title'];
                $checkCode      = $subCheckCode;
                continue;
            } else {
                $checkCode = $subCheckCode;
                break;
            }
        }
        $msg = ($checkCode == 200) ? (implode('、', $ticketTitles) . '需退票审核')
            : '';
        $this->apiReturn($checkCode, [], $msg);
    }

    /**
     * 判断订单是否需要退票审核
     *
     * @param int $orderNum
     * @param int $targetTnum
     * @param int $operatorID
     *
     * @return int
     */
    public function checkRefundAudit(
        $orderNum,
        $targetTnum = 0,
        $operatorID = 1,
        $modifyType = null,
        &$orderInfo = []
    ) {
        $auditNeeded = 100; //100-默认不需要退票审核
        $modifyType  = ($modifyType === null) ? ($targetTnum == 0 ? 3 : 2)
            : $modifyType;
        //检测传入参数
        $orderNum = intval(trim($orderNum));
        if ( ! $orderNum) {
            return (202); //订单号缺失或格式错误
        }
        $operatorID = intval(trim($operatorID));

        //获取订单信息
        $orderModel = new OrderTools();
        $auditModel = new RefundAuditModel();
        $orderInfo  = $auditModel->getInfoForAuditCheck($orderNum);
        if ( ! $orderInfo || ! is_array($orderInfo)) {
            return (204); //订单号不存在
        }
        //未修改门票数量无需审核 2016-4-2 解决联票的判断bug
        if ( ! $orderInfo['ifpack'] && $orderInfo['tnum'] == $targetTnum) {
            return (100);
        }
        //对需要审核的票类，需判断是否满足订单变更条件（对套票主票设置审核是无效的）
        if ($orderInfo['refund_audit'] != 0 && $orderInfo['ifpack']!=1) {
            $auditNeeded = 200;//需要退票审核
            //检查订单使用状态
            $result = $this->checkUseStatus($orderInfo['status'], $modifyType,
                $orderInfo['apply_did'], $operatorID);
            if ($result != 200) {
                return $result;
            }
            //检查订单支付状态
            $result = $this->checkPayStatus($orderInfo['paymode'],
                $orderInfo['pay_status']);
            if ($result != 200) {
                return $result;
            }
            //自供自销的订单可自行取消
            if ($orderInfo['apply_did'] == $operatorID
                && $modifyType == self::CANCEL_CODE
            ) {
                return 100;
            }
        } else {
            //判断套票是否需要退票审核
//            $orderModel = new OrderTools();
            if ($orderInfo['ifpack'] == 1) {
                //套票主票
                $subOrders = $orderModel->getPackSubOrder($orderNum);
//                echo 111;
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
            } elseif ($orderInfo['ifpack'] == 2) {
                $mainOrderIsUnderAudit
                    = $auditModel->isUnderAudit($orderInfo['pack_order']);
                if ($mainOrderIsUnderAudit) {
                    $auditNeeded = 200;
                }
            }
        }

        return $auditNeeded;
    }

    /**
     * 添加退票审核记录：
     * 只支持取消和修改，不支持撤销撤改
     *
     * @param       $orderNum
     * @param       $targetTicketNum
     * @param       $operatorID
     * @param int   $source
     * @param int   $requestTime
     *
     * @param array $orderInfo
     *
     * @return int
     */
    public function addRefundAudit(
        $orderNum,
        $targetTicketNum,
        $operatorID,
        $source = 18,
        $requestTime = 0,
        $orderInfo = []
    ) {
        //查询是否存在审核记录
        $refundModel = new RefundAuditModel();
        $orderModel = new OrderTools();
        $underAudit  = $refundModel->isUnderAudit($orderNum);
        if ($underAudit) {
            return (240);//订单正在审核
        }

        //参数初始化
        $auditStatus = 0; //所有未审核记录的的dstatus都为0
        $trackAction = self::APPLY_AUDIT_CODE;
        $requestTime = ($requestTime) ? $requestTime : date('Y-m-d H:i:s');
        $modifyType  = $targetTicketNum == 0 ? self::CANCEL_CODE
            : self::MODIFY_CODE;
        if(count($orderInfo)<=0){
            $orderModel = new orderTools();
            $orderInfo  = $refundModel->getOrderInfoForAudit($orderNum);
        }
        if ( ! $orderInfo || ! is_array($orderInfo)) {
            return (205);//订单信息不全
        }
        if ($orderInfo['aids']) {
            $auditorID = reset(explode(',', $orderInfo['aids']));
        } else {
            $auditorID = $orderInfo['aid']; //第三方在平台上虽是自供应，但仍需添加退票审核
        }

        //添加订单追踪记录
        $addTrack = $this->addRefundAuditOrderTrack(
            $orderNum,
            $source,
            $operatorID,
            $trackAction,
            $auditStatus,
            $targetTicketNum,
            $orderInfo);
        if ($addTrack != 200) {
            return $addTrack;
        }

        //添加审核记录
        $addAudit = $refundModel->addRefundAudit($orderNum,
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

        if ( ! $addAudit) {
            return (241);//数据添加失败
        }

        //套票主票
        if ($orderInfo['ifpack'] == 1) {
            $subOrders = $orderModel->getPackSubOrder($orderNum);
//            echo 222;
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
                //子票添加审核记录
                $addSubOrder = $this->addRefundAudit(
                    $subOrder['orderid'], $targetSubOrderTnum, $operatorID,
                    $source, $requestTime);
                if ($addSubOrder == 240 || $addSubOrder == 200) {
                    continue;
                } else {
                    return $addSubOrder;
                }
            }
        }

        return 200;//数据添加成功
    }
public function checkAndAddAudit($ordernum,$targeTnum,$opertorID,$source){
    $orderInfo = [];
    $checkAudit = $this->checkRefundAudit($ordernum,$targeTnum,$opertorID,null,$orderInfo);
    if($checkAudit ==200){
        $this->addRefundAudit($ordernum, $targeTnum, $opertorID, $source,0,$orderInfo);
    }
    return $checkAudit;

}
    /**
     * 更新审核记录
     *
     * @param $orderNum
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
        $orderNum,
        $auditResult,
        $auditNote,
        $operatorID,
        $auditTime,
        $auditID = 0,
        $targetTnum
    ) {
        //检查传入参数
        if ( ! in_array($auditResult, [1, 2])) {
            $this->apiReturn(250);//审核结果只能是同意或拒绝
        }
//        echo 1111;
        //参数初始化
        $refundModel = new RefundAuditModel();
        $result = 0;
        $orderInfo = $refundModel->getPackOrderInfo($orderNum);
        if ( ! $orderInfo) {
            $this->apiReturn(205);//订单信息不全
        }
//        $targetTnum = $orderInfo['audit_tnum'];
        $orderModel = new OrderTools();
        //套票需特殊处理
        $ifpack = $orderInfo['ifpack'];
        if($ifpack==1){
            $this->apiReturn(255);//套票主票无人工审核权限
        }else{
            $result = $this->updateAudit(
                $refundModel, $ifpack, $orderNum, $targetTnum, $auditResult, $auditNote,
                $operatorID, $auditTime, $auditID);
//            var_dump($result);
            if ( ! $result) {
                $this->apiReturn(241);
            }
        }
//        var_dump($ifpack);
        if($ifpack==2){
            $mainOrder          = $orderInfo['pack_order'];
            $ordersAutoUpdate   = $orderModel->getPackSubOrder($mainOrder);
//            echo 333;
            $ordersAutoUpdate[] = array('orderid' => $mainOrder);
            switch ($auditResult) {
                case 1://同意退票
                    //检查是否所有子票都通过审核
                    if ($refundModel->hasUnAuditSubOrder($mainOrder)) {
                        $result = 243;
                        break;
                    }
                    //自动更新主票审核记录
                    foreach ($ordersAutoUpdate as $order) {
                        $subOrderNum = $order['orderid'];
                        if ( ! $refundModel->isUnderAudit($subOrderNum)) {
                            continue;
                        }
                        $ifpack = ($order['orderid'] == $mainOrder) ? 1 : 2;
                        $subOrderInfo = $refundModel->getAuditTargetTnum($subOrderNum);
                        $subOrderTargetNum = $subOrderInfo['audit_tnum'];
                        $operatorID = 1;
                        $result = $this->updateAudit(
                            $refundModel, $ifpack,  $subOrderNum, $subOrderTargetNum,
                            $auditResult, '系统:全部子票通过退票审核',  $operatorID);
                    }
                    break;
                case 2:
                    foreach ($ordersAutoUpdate as $order) {
                        $subOrderNum = $order['orderid'];
                        if ( ! $refundModel->isUnderAudit($subOrderNum)) {
                            continue;
                        }
                        $ifpack = ($subOrderNum == $mainOrder) ? 1 : 2;
                        $subOrderInfo = $refundModel->getAuditTargetTnum($subOrderNum);
                        $subOrderTargetNum = $subOrderInfo['audit_tnum'];
                        $operatorID = 1;
                        $result = $this->updateAudit(
                            $refundModel, $ifpack, $subOrderNum, $subOrderTargetNum,
                            $auditResult, '系统:部分子票的退票申请被拒绝',  $operatorID);
                    }
                    break;
            }
        }
        $result = $result ? $result : 252;
        $this->apiReturn($result);//操作成功
    }

    /**
     * @param     $refundModel
     * @param     $ifpack
     * @param     $orderNum
     * @param     $targetTnum
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
        $targetTnum,
        $auditResult,
        $auditNote,
        $operatorID = 1,
        $auditTime = 0,
        $auditID = 0
    ) {
        //参数初始化
        if ( ! $refundModel) {
            $refundModel = new RefundAuditModel();
        }
        $action = self::OPERATE_AUDIT_CODE;
        $source = 16;
        $return = $refundModel->updateAudit($orderNum, $auditResult, $auditNote,
            $operatorID, $auditTime, $auditID);
        if ($auditResult == 2) {
            $this->addRefundAuditOrderTrack($orderNum, $source, $operatorID,
                $action, $auditResult, $targetTnum);
            if ($ifpack != 2) {
                $this->noticeAuditResult('reject', $orderNum, $targetTnum,
                    $auditResult);
            }
        }
//        $data = array(
//            'target_tnum' => $targetTnum,
//        );
//        var_dump($return);
        $result = $return ? 200 : 241;
        return $result;
//        $this->apiReturn($result);
//        if ($result != 200) {
//            $this->apiReturn($result);
//        } else {
//            $this->apiReturn($result, $data);
//        }
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
        $limit       = $limit ? $limit : 20;
        $page        = $page ? $page : 1;
        $r           = array();
        $refundModel = new RefundAuditModel();;
        //获取记录详情
        $refundRecords = $refundModel->getAuditList($operatorID, $landTitle,
            $noticeType,
            $applyTime, $auditStatus, $auditTime, $orderNum, false, $page,
            $limit);
        if (is_array($refundRecords) && count($refundRecords) > 0) {
            foreach ($refundRecords as $row) {
                $row['action'] = 0;
                $row['repush'] = false;
                // action -0 等待处理 -1 同意|拒绝 -2 已处理
                if (($row['apply_did'] == $operatorID || $operatorID == 1)&& $row['ifpack'] != 1){
                    if($row['dstatus']==0){
                        $row['action'] = 1;
                    }else {
                        $row['action'] = 2;
                        if($row['sourceT']!=0 && $row['dcodeURL'] ){
                            $row['repush'] = true;
                        }
                    }
                }else{
                    $row['action'] = $row['dstatus']==0 ? 0: 2;
                }
                unset($row['dcodeURL']);
//                unset($row['mdetails']);
                unset($row['sourceT']);
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
//        $onlinePay = array(1, 5, 7, 8);
//        if (in_array($payMode, $onlinePay)) {
//            return (100);//在线支付订单:无需退票审核
//        } else
        if ($payMode == 4) {
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
            203 => '登陆超时，请重新登陆',
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
            216 => '联票子票不能修改到0张',
            221 => '余票不足',
            230 => '中间分销商不允许取消订单',
            240 => '订单已在审核中，请您耐心等待',
            241 => '退票申请记录更新失败',
            242 => '联票子票无法单独取消',
            243 => '套票子票审核成功',
            244 => '订单追踪记录添加失败',
            250 => '审核参数出错', //审核结果只能是同意或拒绝
            251 => '备注信息不可为空',
            252 => '审核时操作失败',
            253 => '未知错误',
            254 => '子票未全部通过审核，主票无法变更',
            255 => '套票主票不支持人工审核，请等待系统自动审核',
            //            256 => '退票审核不存在或已处理'
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
        ),JSON_UNESCAPED_UNICODE));
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
        $auditResult = null
    ) {
        $data   = array(
            'action'   => $action,
            'ordernum' => $ordernum,
            'num'      => $targetTicketNum,
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
     * @param       $orderNum
     * @param       $source
     * @param       $operatorID
     * @param       $action 10-提交退票请求 11-操作退票审核
     * @param       $auditStatus
     * @param       $targetTicketNum
     * @param array $orderInfo
     *
     * @return mixed
     */
    private function addRefundAuditOrderTrack(
        $orderNum,
        $source,
        $operatorID,
        $action,
        $auditStatus,
        $targetTicketNum,
        array $orderInfo = []
    ) {
        if ( ! in_array($auditStatus, [0, 1, 2])) {
            return 208;
        }
        $refundModel = new RefundAuditModel();
        try {
            if (count($orderInfo) <= 0) {
                $orderInfo = $refundModel->getOrderInfoForAudit($orderNum);
            }

            $tNumOperate = $orderInfo['tnum'] - $targetTicketNum;

            if ($orderInfo['status'] == 7) {
                $verify_num = $refundModel->getVerifiedTnum($orderNum);
                if($verify_num){
                    $tNumCanBeModified = $orderInfo['tnum'] - $verify_num;
                }else{
                    return 241;
                }
            } else {
                $tNumCanBeModified = $orderInfo['tnum'] ;
            }

            switch ($auditStatus) {
                case 0:
                case 1:
                    $remainTicketNum = $tNumCanBeModified - $tNumOperate;
                    break;
                case 2:
                    $remainTicketNum = $tNumCanBeModified;
                    break;
            }
            $trackModel = new OrderTrack();
            $person_id  = $orderInfo['person_id'] ? $orderInfo['person_id'] : 0;
            $addTrack   = $trackModel->addTrack(
                $orderNum,
                $action, //拒绝退票审核
                $orderInfo['tid'],
                $tNumOperate,
                $remainTicketNum,
                $source,
                $orderInfo['terminal'],
                $orderInfo['terminal'],
                $person_id,
                $operatorID,
                $orderInfo['salerid']
            );
            $result     = $addTrack ? 200 : 244;

            return $result;
        } catch (Exception $e) {
            $this->apiReturn(244);
        }

    }

    public function getTicketTitle($orderNum)
    {
        $refundModel = new RefundAuditModel();
        $result      = $refundModel->getTicketTitle($orderNum);

        return $result;
    }

    public function getRequestType()
    {
        $r = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            ? strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
        if ($r == 'xmlhttprequest') {
            $type = 'ajax';
        } else {
            $type = 'html';
        }

        return $type;
    }
}