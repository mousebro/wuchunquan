<?php
/**
 * User: Fang
 * Time: 18:31 2016/4/1
 * TestUrl: http://www.12301.local/route/index.php?c=OrderCallbackLog&a=getNoticeList
 * TestUrl: http://www.12301.test/route/index.php?c=OrderCallbackLog&a=getNoticeList
 */

namespace Controller;
use Library\Controller;

class OrderCallbackLog extends Controller
{
    private $msgInfo = array(
        '200' => '操作成功',
        '201' => '无权限查看',
        '203' => '登陆超时，请重新登陆',
    );

    /**
     * 获取退款通知日志里列表
     */
    public function getNoticeList()
    {
        $memberID = I('session.sid');
        if(!$memberID){
            $this->apiReturn(203);
        }elseif($memberID!=1){
            $this->apiReturn(201);
        }
        parent::isLogin();
        $orderNum = I('param.ordernum');
        $noticeType = I('param.stype');
        $noticeDate = I('param.notice_date');
        $receiver = I('param.receiver');
        $page = I('param.page') ? I('param.page') : 1;
        $limit = I('param.limit') ? I('param.limit') : 20;

        $logModel  = new \Model\Order\OrderCallbackLog();
        $callbacks = $logModel->getNoticeList($orderNum,$noticeType,$noticeDate,$receiver,$page,$limit);
        $row       = array();

        foreach ($callbacks as $callback) {
            $row1[$callback['ordernum']] = $callback;
        }

        $orders     = array_column($callbacks, 'ordernum');
        $auditModel = new \Model\Order\RefundAuditModel();
        $audits     = $auditModel->getNoticeList($orders);

        foreach ($audits as $audit) {
            $row[] = array_merge($row1[$audit['ordernum']], $audit);
        }
        $this->apiReturn(200,$row);
    }

    /**
     * 获取对接接口列表
     */
    public function getReceiverList(){
        $page = I('param.page');
        $limit = I('param.limit');
        $memberID = I('session.sid');
        if(!$memberID){
            $this->apiReturn(203);
        }elseif($memberID!=1){
            $this->apiReturn(201);
        }
}

    public function apiReturn($code, $data = array(), $msg = '')
    {
        $msg = $msg
            ? $msg
            : (array_key_exists($code,
                $this->msgInfo) ? $this->msgInfo[$code] : '');
        parent::apiReturn($code,$data,$msg);
    }
}