<?php

/**
 * 订单短信Job
 *
 * Class SmsNotify_Job
 * 参数列表：
 * @mid : 收取短信费的那个人的会员ID
 * @mobile: 手机号
 * @msg: 发送内容
 * @dtype: 发送渠道（绝大多数是即时通，值为0），
 * @ordernum: 订单号
 */
class SmsNotify_Job {
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

