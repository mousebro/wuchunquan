<?php

class WxNotify_Job {
    public function perform(){
        include '/var/www/html/wx/wechat/open_wechat.php';
        $wx = new \Library\MessageNotify\WxTemplateMsg();
        $wx->color  = $this->args['color'];
        $wx->url    = $this->args['url'];
        $wx->openid = $this->args['openid'];
        $wx->tplId  = $this->args['tplid'];
        $wx->data   = $this->args['data'];
        $res = $wx->Send();
        if ($res['code']!=200) {
            E('发送微信通知失败了');
            return false;
        }
        return true;
    }
}

