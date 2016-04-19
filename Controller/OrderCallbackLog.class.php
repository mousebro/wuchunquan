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
     * 获取退款通知日志里列表
     *
     */
    public function getNoticeList()
    {
        $orderNum   = I('param.ordernum');
        $noticeType = I('param.stype');
        $noticeDate = I('param.notice_date');
        $memberId   = I('param.memberId');
        $page       = I('param.page') ? I('param.page') : 1;
        $limit      = I('param.limit') ? I('param.limit') : 20;
        if(!class_exists('Model\\Order\\OrderCallbackLog') || !class_exists('Model\\Order\\RefundAuditModel')){
            $this->apiReturn(204);
        }
        try {
            $row = array();
            $logModel  = new \Model\Order\OrderCallbackLog();
            $callbacks = $logModel->getNoticeList($orderNum, $noticeType, $noticeDate, $memberId, $page, $limit);
            if ( ! $callbacks) {
                $this->apiReturn(202);
            }
            $callback_list = $callbacks['list'];
            foreach ($callback_list as $callback) {
                $row[$callback['ordernum']] = $callback;
            }
            $total = $callbacks['total'];

            $row_merged = array();
            $orders     = array_column($callback_list, 'ordernum');
            $auditModel = new \Model\Order\RefundAuditModel();
            $audits     = $auditModel->getNoticeList($orders);
            if ( ! $audits) {
                $this->apiReturn(202);
            }
            foreach ($audits['list'] as $audit) {
                $row_tmp = array_merge($row[$audit['ordernum']], $audit);
                uksort($row_tmp, function ($key1, $key2) {
                    $key_arr      = array('notice_id', 'ordernum', 'ltitle', 'change_type', 'apply_time', 'handle_res', 'ota_name', 'last_push_time', 'push_state',);
                    $key_arr_flip = array_flip($key_arr);
                    if(in_array($key1,$key_arr) && in_array($key2,$key_arr) && $key_arr_flip[$key1] > $key_arr_flip[$key2]){
                        return 1;
                    }else{
                        return -1;
                    }
                });
                $row_merged[]   = $row_tmp;
            }
            $total_page = ceil($total/$limit);
            $page_next = $page + 1;
            $data = array(
                'page'=>$page_next,
                'limit'=>$limit,
                'total_page'=>$total_page,
//                'total'=>$total,
                'list'=>$row_merged,
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