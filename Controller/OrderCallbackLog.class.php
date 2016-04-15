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
     */
    public function getLogList()
    {
        $orderNum   = I('param.ordernum');
        $noticeType = I('param.stype');
        $noticeDate = I('param.notice_date');
        $receiver   = I('param.receiver');
        $page       = I('param.page') ? I('param.page') : 1;
        $limit      = I('param.limit') ? I('param.limit') : 20;
        if(!class_exists('Model\\Order\\OrderCallbackLog') || !class_exists('Model\\Order\\RefundAuditModel')){
            $this->apiReturn(204);
        }
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
                $row_tmp = array_merge($row1[$audit['ordernum']], $audit);
                uksort($row_tmp, function ($key1, $key2) {
                    $key_arr      = array('notice_id', 'ordernum', 'ltitle', 'change_type', 'apply_time', 'handle_res', 'ota_name', 'last_push_time', 'push_state',);
                    $key_arr_flip = array_flip($key_arr);
                    if(in_array($key1,$key_arr) && in_array($key2,$key_arr) && $key_arr_flip[$key1] > $key_arr_flip[$key2]){
                        return 1;
                    }else{
                        return -1;
                    }
                });
                $row[]   = $row_tmp;
            }

            print_r($row);
            //            $this->apiReturn(200, $row);
        } catch (Exception $e) {
            $this->apiReturn(203, [], $e->getMessage());
        }
    }

    /**
     * 获取对接接口列表
     */
    public function getReceiverList()
    {
        $page     = I('param.page');
        $limit    = I('param.limit');
        $dname = I('param.dname');



    }

    public function apiReturn($code, $data = array(), $msg = '')
    {
        $msg = $msg ? $msg : (array_key_exists($code, $this->msgInfo) ? $this->msgInfo[$code] : '');
        parent::apiReturn($code, $data, $msg);
    }
}