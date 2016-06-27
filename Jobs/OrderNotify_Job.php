<?php

/**
 * 订单短信Job
 *
 * Class SmsNotify_Job
 * 参数列表：
 * @sellerId : 收取短信费的那个人的会员ID
 * @mobile: 手机号
 * @ordernum: 订单号
 * @ptype: 产品类型
 * @ltitle: 产品名称
 * @aid: 上级供应商ID
 * @pid: 产品ID
 * @buyerId: 购买人ID
 */
class OrderNotify_Job {
    public function perform(){
        include '/var/www/html/wx/wechat/open_wechat.php';
        $notify = new \Library\MessageNotify\OrderNotify(
            $this->args['ordernum'],
            $this->args['buyerId'],
            $this->args['aid'],
            $this->args['mmobile'],
            $this->args['pid'],
            $this->args['sellerId'],
            $this->args['ptype'],
            $this->args['ltitle']
            );
        $code = $manual = 0;//对接第三方系统的
        if (isset($this->args['code'])) $code = $this->args['code'];
        if (isset($this->args['manual'])) $manual = true;
        $res = $notify->send($code, $manual);
        if ($res!=true) E('发送短信失败了');
        return $res;
    }
}

