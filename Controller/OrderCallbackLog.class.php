<?php
/**
 * User: Fang
 * Time: 18:31 2016/4/1
 * TestUrl: http://www.12301.local/route/index.php?c=OrderCallbackLog&a=getNoticeList
 * TestUrl: http://www.12301.test/route/index.php?c=OrderCallbackLog&a=getNoticeList
 */

namespace Controller;

use Library\Controller;
use Library\Exception;

class OrderCallbackLog extends Controller
{
    private $msgInfo
        = array(200 => '操作成功', 201 => '无权限查看', 202 => '查询记录为空',203=>'未知错误');

    /**
     * 获取退款通知日志里列表
     */
    public function getLogList()
    {
        $memberID = $this->isLogin('ajax');
        if ($memberID != 1) {
            $this->apiReturn(201);
        }
        $orderNum   = I('param.ordernum');
        $noticeType = I('param.stype');
        $noticeDate = I('param.notice_date');
        $receiver   = I('param.receiver');
        $page       = I('param.page') ? I('param.page') : 1;
        $limit      = I('param.limit') ? I('param.limit') : 20;
        try {
            $logModel  = new \Model\Order\OrderCallbackLog();
            $callbacks = $logModel->getNoticeList($orderNum, $noticeType, $noticeDate, $receiver, $page, $limit);
            if ( ! $callbacks) {
                $this->apiReturn(201);
            }
            $row = array();
            foreach ($callbacks as $callback) {
                $row1[$callback['ordernum']] = $callback;
            }

            $orders     = array_column($callbacks, 'ordernum');
            $auditModel = new \Model\Order\RefundAuditModel();
            $audits     = $auditModel->getNoticeList($orders);
            if ( ! $audits) {
                $this->apiReturn(201, [], '查询记录为空');
            }
            foreach ($audits as $audit) {
                $row[] = array_merge($row1[$audit['ordernum']], $audit);
            }
            $this->apiReturn(200, $row);
        } catch (Exception $e) {
            $this->apiReturn(203,[],$e->getMessage());
        }
    }

    /**
     * 获取对接接口列表
     */
    public function getReceiverList()
    {
        $page     = I('param.page');
        $limit    = I('param.limit');
        $memberID = I('session.sid');
        if ( ! $memberID) {
            $this->apiReturn(203);
        } elseif ($memberID != 1) {
            $this->apiReturn(201);
        }
    }

    public function apiReturn($code, $data = array(), $msg = '')
    {
        $msg = $msg ? $msg : (array_key_exists($code, $this->msgInfo) ? $this->msgInfo[$code] : '');
        parent::apiReturn($code, $data, $msg);
    }
}