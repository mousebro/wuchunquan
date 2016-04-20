<?php
/**
 * User: Fang
 * Time: 18:31 2016/4/1
 * TestUrl: http://www.12301.local/route/index.php?c=OrderCallbackLog&a=getNoticeList
 * TestUrl: http://www.12301.test/route/index.php?c=OrderCallbackLog&a=getNoticeList
 * TestUrl: http://www.12301.local/route/index.php?c=OrderCallbackLog&a=getReceivers
 */

namespace Controller;

use Library\Controller;
use Library\Exception;
use Model\Order;

class OrderCallbackLog extends Controller
{
    private $msgInfo = array(200 => '操作成功', 201 => '无权限查看', 202 => '查询记录为空', 203 => '未知错误',204=>'文件缺失');

    public function __construct()
    {
        $memberID = $this->isLogin('ajax');
        //只有管理员可以查看
        if ($memberID != 1) {
            $this->apiReturn(201);
        }
    }

    /**
     *  ota日志通知列表
     */
    public function getNoticeList()
    {
        $orderNum   = I('param.ordernum');
        $noticeType = I('param.stype');
        $noticeDate = I('param.notice_date');
        $memberId   = I('param.memberId');
        $page       = I('param.page') ? I('param.page') : 1;
        $limit      = I('param.limit') ? I('param.limit') : 20;
        if ( ! class_exists('Model\\Order\\OrderCallbackLog') || ! class_exists('Model\\Order\\RefundAuditModel')) {
            $this->apiReturn(204);
        }
        try {
            $row1       = array();
            $auditModel = new \Model\Order\RefundAuditModel();
            $audits     = $auditModel->getLogList($orderNum, $noticeType, $noticeDate, $memberId, $page, $limit);
            if ( ! $audits) {
                $this->apiReturn(202);
            }
            $audit_list = $audits['list'];
            $total      = $audits['total'];

            foreach ($audit_list as $audit) {
                $row1[$audit['ordernum']] = $audit;
            }

            $row_merged = array();
            $row2       = array();
            $orders     = array_column($audit_list, 'ordernum');
            $logModel   = new \Model\Order\OrderCallbackLog();
            $callbacks  = $logModel->getLogList($orders);
            if($callbacks){
                foreach ($callbacks['list'] as $callback) {
                    $row2[$callback['ordernum']] = $callback;
                }
            }

            foreach ($row1 as $key => $value) {
                $row2["$key"] = empty($row2["$key"]) ? array(
                    'last_push_time' => '0000-00-00 00:00:00',
                    'push_state'     => 0,
                ) : $row2["$key"];
                $row_tmp      = array_merge($row1[$key], $row2[$key]);
                $row_merged[] = $row_tmp;
            }
            $data = array(
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'list'  => $row_merged,
            );
            $this->apiReturn(200, $data);
        } catch (Exception $e) {
            $this->apiReturn(203, [], $e->getMessage());
        }
    }

    /**
     * 获取对接接口列表
     */
    public function getReceivers()
    {
        $page = intval(I('param.page'));
        $page = $page ? $page : 1;
        $limit = intval(I('param.limit'));
        $limit = $limit ? $limit : 20;
        $ota_name = trim(I('param.ota_name'));
        if(!class_exists('Model\\Order\\RefundAuditModel')){
            $this->apiReturn(204);
        }
        try{
            $refundModel = new Order\RefundAuditModel();
            $data = $refundModel -> getReceiverList($ota_name,$page,$limit);
            $this->apiReturn(200, $data);
        }catch (Exception $e) {
            $this->apiReturn(203, [], $e->getMessage());
        }
    }

    public function apiReturn($code, $data = array(), $msg = '')
    {
        $msg = $msg ? $msg : (array_key_exists($code, $this->msgInfo) ? $this->msgInfo[$code] : '');
        parent::apiReturn($code, $data, $msg);
    }
}