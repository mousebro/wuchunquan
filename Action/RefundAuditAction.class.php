<?php
/**
 * User: Fang
 * Time: 15:03 2016/3/11
 */

namespace Action;

use Library\Controller;
use Model\Order\OrderTools;
use Model\Order\RefundAudit;

class RefundAuditAction extends Controller
{
    const MODIFY_CODE = 2;
    const CANCEL_CODE = 3;

    /**
     * 判断订单是否需要退票审核
     *
     * @param int $orderNum
     * @param int $targetTnum
     * @param int $operatorID
     *
     * @return int
     */
    public function checkRefundAudit($orderNum, $targetTnum, $operatorID)
    {

        //检测传入参数
        $orderNum = intval(trim($orderNum));
        if ( ! $orderNum) {
            $this->apiReturn(202); //订单号缺失或格式错误
        }

        $operatorID = intval(trim($operatorID));
        if ( ! $operatorID) {
            $this->apiReturn(203);//操作人ID缺失或格式错误
        }

        $targetTnum = intval($targetTnum);
        $modifyType = ($targetTnum == 0) ? self::CANCEL_CODE
            : self::MODIFY_CODE;


        //获取订单信息
        $orderModel = new OrderTools();
        $orderInfo  = $orderModel->getOrderInfo($orderNum);
        if ( ! $orderInfo) {
            $this->apiReturn(204); //订单号不存在
        }
        //获取订单支付信息
        $orderDetail = $orderModel->getOrderDetail($orderNum);
        if(!$orderDetail || !is_array($orderDetail)){
            $this->apiReturn(205);//订单信息不全
        }
        //获取票类信息
        $tid = $orderInfo['tid'];
        $ticketModel = new \Model\Product\Ticket();
        $ticketInfo = $ticketModel->getTicketInfoById($tid);
        if ( ! $ticketInfo) {
            $this->apiReturn(206); //对应门票不存在
        }


        //判断对应票类是否需要退票审核
        if ($ticketInfo['refund_audit'] == 0) {
            $this->apiReturn(100);//无需退票审核
        }

        //检查订单使用状态
        $this->checkUseStatus($orderInfo['status'], $modifyType,
            $ticketInfo['apply_did'], $orderInfo['aid']);
        //检查订单支付状态
        $this->checkPayStatus($orderInfo['paymode'],$orderDetail['pay_status']);
        //检查订单票数
        //todo：需要确定分批验证的剩余票数是哪个字段
        $ticketRemain = $orderInfo['tnum'];
        if ($ticketRemain < $targetTnum) {
            $this->apiReturn(221, "余票不足:当前剩余{$ticketRemain}张门票"); //剩余票数不足
        } elseif ($ticketRemain == $targetTnum) {
            $this->apiReturn(222, "当前剩余{$ticketRemain}张门票,无需退票"); //剩余票数与目标票数相等
        }
        //todo:检查取消的发起者是否是末级分销商
        if($orderDetail['aids']){
            $aids = explode(',',$orderDetail['aids']);
            if($modifyType==self::CANCEL_CODE){
                if($operatorID!=current($aids) && $operatorID!=end($aids)){
                    $this->apiReturn(230);//中间分销商不允许取消订单
                }
            }else{
                if($operatorID!=end($aids)){
                    $this->apiReturn(231);//只有末级分销商可以修改订单
                }
            }
        }
        return $modifyType;
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
        $modifyType,
        $requestTime
    ) {
        $refundModel = new RefundAudit();
        if($modifyType==null){
            $modifyType = $targetTnum==0 ? self::CANCEL_CODE : self::MODIFY_CODE;
        }
        $underAudit  = $refundModel->underAudit($orderNum, $modifyType);
        if ($underAudit) {
            $this->apiReturn(240);//订单正在审核
        }
        $addSuccess = $refundModel->addRefundAudit($orderNum, $targetTnum,
            $modifyType, $requestTime);
        if ( ! $addSuccess) {
            $this->apiReturn(241);//数据添加失败
        } else {
            $this->apiReturn(200);//操作成功
        }
    }


    /**
     * 检查订单使用状态
     *
     * @param $useStatus 0未使用|1已使用|2已过期|3被取消|4凭证码被替代|5被终端修改|6被终端撤销|7部分使用
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
                $this->apiReturn(210);//订单已使用:不可取消或修改
                break;
            case 2:
                if ($modifyType == self::MODIFY_CODE) {
                    $this->apiReturn(211);//订单已过期:不允许修改
                } else {
                    if ($operatorId != $ticketAid) {
                        $this->apiReturn(212);//订单已过期：只有供应商可以取消
                    } else {
                        $this->apiReturn(100, '供应商取消已过期订单：无需退票审核');
                    }
                }
                break;
            case 3:
                $this->apiReturn(213);
                break;
            case 5:
                $this->apiReturn(214);//订单已被终端撤改:不可取消或修改
                break;
            case 6:
                $this->apiReturn(215);//订单已被终端撤销:不可取消或修改
                break;
            default://(0-未使用 7-部分使用 [4-不处理])
                continue;
        }
        return;
    }

    /**
     * 检查支付方式和支付状态
     *
     * @param int $payMode 支付方式：1在线支付|2授信支付|3自供自销|4到付|5微信支付|7银联支付|8环迅支付
     * @param int $payStatus 0景区到付|1已成功|2未支付
     */
    public function checkPayStatus($payMode,$payStatus){
        $payMode = intval(trim($payMode));
        $payStatus = intval(trim($payStatus));
        //检查支付方式
        $onlinePay = array(1,5,7,8);
        if(in_array($payMode,$onlinePay)){
            $this->apiReturn(100,'在线支付订单:无需退票审核');
        }elseif($payMode==3){
            $this->apiReturn(100,'自供自销订单:无需退票审核');
        }elseif($payMode==4){
            $this->apiReturn(100,'到付订单:无需退票审核');
        }
        //检查支付状态
        if($payStatus==2){
            $this->apiReturn(100,"未支付订单:无需退票审核");
        }
        return;
    }

//返回接口数据
    public function apiReturn($code, $msg = '')
    {
        $msgList = array(
            100 => '无需退票审核',
            200 => '成功发起退款审核',
            201 => '缺少传入参数',
            202 => '订单号缺失或格式错误',
            203 => '操作人ID缺失或格式错误',
            204 => '订单号不存在',
            205 => '订单信息不全',
            206 => '对应门票不存在',
            210 => '订单已使用:不可取消或修改',
            211 => '订单已过期:不可修改',
            212 => '订单已过期:非供应商不可取消',
            213 => '订单已取消:不可再取消或修改',
            214 => '订单已被终端撤改:不可取消或修改',
            215 => '订单已被终端撤销:不可取消或修改',
            221 => '余票不足',
            230 => '中间分销商不允许取消订单',
            240 => '订单已在审核中，请您耐心等待',
            241 => '数据添加失败,请联系管网站管理员',
        );
        if ( !$msg && array_key_exists($code, $msgList)) {
            $msg = $msgList[$code];
        }
        if ($msg) {
            exit(json_encode(array("code"=>$code,"msg"=>$msg)));
        } else {
            exit(json_encode(array("code"=>$code)));
        }
    }
}