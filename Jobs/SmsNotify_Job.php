<?php

class SmsNotify_Job {
    public function perform(){
        $sms = new \Library\MessageNotify\VComSms();
        $res        = $sms->Send($this->args['mobile'], $this->args['msg']);
        $memberId   = $this->args['mid'];
        $ordern     = $this->args['ordernum'];
        $msglen     = utf8Length($this->args['msg']);
        if ($res['code']==200) {
            //扣费
            $m      = ceil($msglen/67);
            $memOjb = new \Model\Member\Member();
            $res = $memOjb->ChargeSms($memberId, $m, 0, $ordern);
            return $res==200;
        }
        E('发送短信失败了');
        return false;
    }
}

