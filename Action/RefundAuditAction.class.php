<?php
/**
 * User: Fang
 * Time: 15:03 2016/3/11
 */

namespace Action;

use Library\Controller;
use Model\Order\OrderTools;
use Model\Order\RefundAudit;

class RefundAuditAction extends BaseAction
{
    const MODIFY_CODE = 2;
    const CANCEL_CODE = 3;

    /**
     * 判断订单是否需要退票审核
     *
     * @param int $orderNum
     * @param int $targetTnum
     * @param int $operatorID
     * @param int $loop         联票订单循环调用次数
     *
     * @return int
     */
    public function checkRefundAudit(
        $orderNum,
        $targetTnum,
        $operatorID,
        $loop = 0
    ) {

        //检测传入参数
        $orderNum = intval(trim($orderNum));
        if ( ! $orderNum) {
            return (202); //订单号缺失或格式错误
        }

        $operatorID = intval(trim($operatorID));
        if ( ! $operatorID) {
            return (203);//操作人ID缺失或格式错误
        }
        //判断修改类型
        $targetTnum = intval($targetTnum);
        $modifyType = ($targetTnum == 0) ? self::CANCEL_CODE
            : self::MODIFY_CODE;

        //获取订单扩展信息
        $orderModel  = new OrderTools();
        $orderExtend = $orderModel->getOrderAddonInfo($orderNum);
        if ( ! $orderExtend || ! is_array($orderExtend)) {
            return (205);//订单信息不全
        }

        //判断套票是否需要退票审核
        if ($orderExtend['ifpack'] == 1) {//套票主票
            $subOrders = $orderModel->getPackageSubOrder($orderNum);
            if ( ! $subOrders || ! is_array($subOrders)) {
                return (207);
            }//套票信息出错
            foreach ($subOrders as $subOrder) {
                $subOrderNum = $subOrder['orderid'];
                $result      = $this->checkRefundAudit($subOrderNum,
                    $targetTnum, $operatorID);
//                print_r($result);
                if ($result == 200) {//套票中有任一子票是需要退票审核的，则该套票都是需要审核的
                    return (200);
                }
            }
        }

        //获取订单信息
        $orderInfo = $orderModel->getOrderInfo($orderNum);
        if ( ! $orderInfo) {
            return (204); //订单号不存在
        }

        //获取订单支付信息
        $orderDetail = $orderModel->getOrderDetail($orderNum, 1);
        if ( ! $orderDetail || ! is_array($orderDetail)) {
            return (205);//订单信息不全
        }
        //判断联票是否需要退票审核
        if ($loop === 0 && $orderDetail['concat_id']) {
            $subOrders = $orderModel->getLinkSubOrder($orderNum);
            foreach ($subOrders as $subOrder) {
                if ($subOrder != $orderNum) {
                    $result = $this->checkRefundAudit($subOrder['orderid'],
                        $targetTnum, $operatorID, 1);
                    if ($result == 200) {
                        return $result;
                    }
                }
            }

        }

        //获取票类信息
        $tid         = $orderInfo['tid'];
        $ticketModel = new \Model\Product\Ticket();
        $ticketInfo  = $ticketModel->getTicketInfoById($tid);
        if ( ! $ticketInfo) {
            return (206); //对应门票不存在
        }

        //判断对应票类是否需要退票审核
        if ($ticketInfo['refund_audit'] == 0) {
            return (100);//无需退票审核
        }

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

        //检查订单票数
        //todo：需要确定分批验证的剩余票数是哪个字段
        $ticketRemain = $orderInfo['tnum'];
        if ($ticketRemain < $targetTnum) {
            return (221); //剩余票数不足:余票不足:当前剩余{$ticketRemain}张门票
        } elseif ($ticketRemain == $targetTnum) {
            return (222); //剩余票数与目标票数相等
        }

        if ($orderExtend['ifpack'] == 2) { //套票子票不判断发起人，以主票为准
            return 200;
        }
        //todo:检查取消的发起者是否是末级分销商
        if ($orderDetail['aids']) {
            $aids = explode(',', $orderDetail['aids']);
            if ($modifyType == self::CANCEL_CODE) {
                if ($operatorID != current($aids)
                    && $operatorID != end($aids)
                ) {
                    return (230);//中间分销商不允许取消订单
                }
            } else {
                if ($operatorID != end($aids)) {
                    return (231);//只有末级分销商可以修改订单
                }
            }
        }

        return 200;
    }

    /**
     * 添加退票审核记录
     *
     * @param $orderNum
     * @param $targetTnum
     * @param $modifyType
     * @param $requestTime
     */
    public function addRefundAudit(
        $orderNum,
        $targetTnum,
        $operatorID,
        $requestTime
    ) {
        $refundModel = new RefundAudit();
        $modifyType = $targetTnum == 0 ? self::CANCEL_CODE : self::MODIFY_CODE;
        $underAudit = $refundModel->underAudit($orderNum, $modifyType);
        if ($underAudit) {
            return (240);//订单正在审核
        }
        //不判断订单类型，先添加对应订单的退票审核记录
        $callTnum = 1; //返回原始门票数和变更后门票数
        $addSuccess = $refundModel->addRefundAudit($orderNum, $targetTnum,
            $modifyType, $operatorID, $requestTime,$callTnum);
        if ( ! $addSuccess || !is_array($addSuccess)) {
            return (241);//数据添加失败
        }

        //获取订单扩展信息
        $orderModel  = new OrderTools();
        $orderExtend = $orderModel->getOrderAddonInfo($orderNum);
        if ( ! $orderExtend || ! is_array($orderExtend)) {
            return (205);//订单信息不全
        }
        if ($orderExtend['ifpack'] == 1) {//套票主票
            $subOrders = $orderModel->getPackageSubOrder($orderNum);
            if ( ! $subOrders || ! is_array($subOrders)) {
                return (207);
            }//套票信息出错
            foreach ($subOrders as $subOrder) {
                $subOrderNum = $subOrder['orderid'];

                $result      = $this->addRefundAudit($subOrderNum,$targetTnum, $operatorID,1);
                if ($result == 200) {//套票中有任一子票是需要退票审核的，则该套票都是需要审核的
                    continue;
                }
            }
        }







    }

    public function operate_audit(
        $auditID,
        $auditResult,
        $auditNote,
        $orderNum,
        $operatorID,
        $auditTnum
    ) {
        if ($auditID == 0) {
            return (205);
        }
        if ($auditResult == 0) {
            return (250);
        }
        if ($auditNote == '') {
            return (251);
        }
        if ($auditTnum == 0) {
            $this->postCancelRequest($orderNum);
        } else {
            $this->postModifyRequest($orderNum, $auditTnum);
        }
        $refundModel = new RefundAudit();
        $result      = $refundModel->updateAudit($auditID, $auditResult,
            $auditNote, $orderNum, $operatorID);
        if ($result) {
            return (200);
        } else {
            return (241);
        }
    }

    //向订单取消接口请求
    public function postCancelRequest($orderNum)
    {
        $url             = 'http://localhost/new/d/call/handle.php';
        $data            = array(
            'from'     => 'order_cancel',
            'ordernum' => $orderNum,
        );
        $rawCancelResult = $this->curlPost($url, $data);
        if ($cancelResult = json_decode($rawCancelResult)) {
            if ($cancelResult['outcome'] == 1) {
                return 200;
            } else {
                return (251); //修改失败,'修改失败 '. $cancelResult['msg']
            }
        }
    }

    //向订单修改接口请求
    public function postModifyRequest($orderNum, $tnum)
    {
        $url             = 'http://localhost/new/d/call/handle.php';
        $data            = array(
            'from' => 'order_alter',
            'tids' => array(
                $orderNum => $tnum,
            ),
        );
        $rawCancelResult = $this->curlPost($url, $data);
        if ($cancelResult = json_decode($rawCancelResult)) {
            if ($cancelResult['outcome'] == 1) {
                return 200;
            } else {
                return (251); //,'取消失败 '. $cancelResult['msg']
            }
        }
    }

    /**
     * 检查订单使用状态
     *
     * @param $useStatus  0未使用|1已使用|2已过期|3被取消|4凭证码被替代|5被终端修改|6被终端撤销|7部分使用
     * @param $modifyType 0撤改|1撤销|2修改|3取消
     * @param $ticketAid
     * @param $operatorId
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
                return (213);
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
     * 检查支付方式和支付状态
     *
     * @param int $payMode   支付方式：1在线支付|2授信支付|3自供自销|4到付|5微信支付|7银联支付|8环迅支付
     * @param int $payStatus 0景区到付|1已成功|2未支付
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
    public function apiReturn($code, $msg = '')
    {
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
            252 => '退票审核时取消失败',
        );
        if ( ! $msg && array_key_exists($code, $msgList)) {
            $msg = $msgList[$code];
        }
        if ($msg) {
            exit(json_encode(array("code" => $code, "msg" => $msg)));
        } else {
            exit(json_encode(array("code" => $code)));
        }
    }
}