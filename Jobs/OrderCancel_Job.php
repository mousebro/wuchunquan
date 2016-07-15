<?php

/**
 * 套票子票失败——取消订单队列
 *
 * Class OrderCancel_Job
 */
class OrderCancel_Job {
    public function perform(){
        $main_order = $this->args['ordernum'];
        $err_info   = $this->args['error_info'];
        $server = new ServerInside();
        $result = $server->Order_Change_Pro($main_order,0, 0);
        if ($result==100) {
            $model  = new \Library\Model('slave');
            $orderInfo = $model->table('uu_ss_order')
                ->where(['ordernum'=>$main_order])
                ->limit(1)->getField('ordertel,lid', true);

            $sellerID = $model->table('uu_land')->where(['id'=>$orderInfo['lid']])->getField('apply_did');
            $sellerMobile = $model->table('pft_member')->where(['id'=>$sellerID])->getField('mobile');
            $notify = new \Library\MessageNotify\OrderNotify($main_order,0, 0, $orderInfo['ordertel'], $sellerID);
            //通知分销商
            $content = "您的套票订单【{$main_order}】由于子票订单提交失败已被系统自动取消。";
            $notify->SendSMS($orderInfo['ordertel'], $content );
            //通知供应商
            $content = "套票订单【{$main_order}】子票下单失败已被系统自动取消，错误信息:{$err_info}";
            $notify->SendSMS($sellerMobile, $content );
        }
        else {
            $msg = "{$main_order}|套票取消订单失败|返回数据:$result";
            pft_log('queue/order_cancel', $msg);
            E($msg);
        }
    }
}

