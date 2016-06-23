<?php

/**
 * 订单短信Job
 *
 * Class SmsNotify_Job
 * 参数列表：
 * @memberId : 收取短信费的那个人的会员ID
 * @mobile: 手机号
 * @ordernum: 订单号
 * @ptype: 产品类型
 * @ltitle: 产品名称
 * @aid: 上级供应商ID
 * @pid: 产品ID
 */
class OrderNotify_Job {
    public function perform(){
        switch ($this->args['dtype']) {
            case 1:
                $res = \Library\MessageNotify\HongQunSms::doSendSMS($this->args['mobile'], $this->args['msg']);
                break;
            default:
                $res = \Library\MessageNotify\VComSms::doSendSMS($this->args['mobile'],
                    $this->args['msg'], $this->args['ordernum'],
                    $this->args['sms_account']);
                break;
        }
        $memberId   = $this->args['mid'];//供应商ID（收费人）
        $ordern     = $this->args['ordernum'];
        $msglen     = utf8Length($this->args['msg']);
        if ($res['code']==200) {
            //扣费
            $m      = ceil($msglen/67);
            $memOjb = new \Model\Member\Member();
            $res    = $memOjb->ChargeSms($memberId, $m, 0, $ordern);
            return $res==200;
        }
        E('发送短信失败了');
        return false;
    }
}

