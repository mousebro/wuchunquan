<?php

class SmsNotify_Job {
    
    public function perform(){
        $sms = new \Library\MessageNotify\VComSms();
        $res = $sms->Send($this->args['mobile'], $this->args['msg']);
        echo "++++++++++++++++++++++\n";
        echo $res;
        echo "++++++++++++++++++++++\n";
    }
}

