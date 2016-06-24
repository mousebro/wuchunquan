<?php

class WxNotify_Job {
    public function perform(){
        include '/var/www/html/wx/wechat/open_wechat.php';
        include '/var/www/html/new/d/module/common/Db.class.php';
        $dbConf = include '/var/www/html/new/d/module/common/db.conf.php';// 远端服务器配置信息
        \PFT\Db::Conf($dbConf['remote_1']);
        $db = \PFT\Db::Connect();

        pft_log('queue', json_encode($this->args));
        if (ENV!='PRODUCTION') {
            pft_log('queue/vcom', "发送微信|" .  json_encode($this->args, JSON_UNESCAPED_UNICODE));
            return true;
        }
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

