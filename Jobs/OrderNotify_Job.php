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
 * @notify int 是否发送通知购买手机（外部接口有不发送的） 0 发送 1不发送
 */
class OrderNotify_Job {
    public function perform(){
        if (isset($this->args['action'])) {
            if (strcmp($this->args['action'], 'SendAlipayCard')==1) {
                $this->sendAlipayCard($this->args['ordernum'], $this->args['lid'],
                    $this->args['tid'], $this->args['code'], $this->args['beginTime'],
                    $this->args['endTime']);
            }
            elseif (strcmp($this->args['action'],'SendWechatCard')==1) {
                $this->sendWechatCard($this->args['ordernum'], $this->args['lid'],
                    $this->args['tid'], $this->args['code'], $this->args['beginTime'],
                    $this->args['endTime'], $this->args['totalmoney'],
                    $this->args['appid'], $this->args['openid']);
            }
        }
        else {
            include '/var/www/html/wx/wechat/open_wechat.php';
            $notify = new \Library\MessageNotify\OrderNotify(
                $this->args['ordernum'],
                $this->args['buyerId'],
                $this->args['aid'],
                $this->args['mobile'],
                $this->args['pid'],
                $this->args['sellerId'],
                $this->args['ptype'],
                $this->args['ltitle'],
                $this->args['notify']
            );
            $code = $manual = 0;//对接第三方系统的
            if (isset($this->args['code'])) $code = $this->args['code'];
            if (isset($this->args['manual'])) $manual = true;
            $res = $notify->send($code, $manual);
            if ($res!=true) E('发送短信失败了');
            return $res;
        }
    }

    private function sendAlipayCard($out_trade_no, $lid, $tid, $code, $beginTime, $endTime)
    {
        $attrs = $this->getProductInfo($tid, $lid);
        if (!$attrs) return false;
        $request = array(
            'action' => 'send_alipass',
            'auth' => md5('RFGrfgY5CjVP8LcYsend_alipass'),
            'out_trade_no' => $out_trade_no,
            'title' => $attrs['ltitle'] . '-' . $attrs['title'],
            'begin_time' => strtotime($beginTime),
            'end_time' => strtotime($endTime) + 3600 * 24,
            'code' => $code
        );
        $request_data = base64_encode(json_encode($request));
        file_get_contents("http://s.12301.cc/pft/alipay_fuwuchuang/alipass.php?data=".$request_data);
    }
    private function getProductInfo($tid, $lid)
    {
        $model = new \Library\Model('slave');
        $data  = $model->table('uu_jq_ticket t')->join('uu_land l ON l.id=t.landid')
            ->where(['t.id'=>$tid, 't.landid'=>$lid])
            ->field('t.title as ttitle,t.getattr,l.title as ltitle,l.salerid')
            ->find();
        return $data;
    }
    private function sendWechatCard($out_trade_no, $lid, $tid, $code, $beginTime, $endTime, $totalmoney, $appid, $openid)
    {
        $attrs = $this->getProductInfo($tid, $lid);
        if (!$attrs) return false;
        $url = 'http://wx.12301.cc/card/card.php?data=';
        $api_key = 'RFGrfgY5CjVP8LcY';
        $request = array(
            'action'      => 'create',
            'app_id'      => $appid,
            'open_id'     => $openid,      //发送给哪个微信用户
            'code'        => $code,//验证码
            'ltitle'      => $attrs['ltitle'],
            'ttitle'      => $attrs['ttitlee'],
            'getaddr'     => $attrs['getaddr'],
            'ordernum'    => $out_trade_no,
            'salerid'     => $attrs['salerid'],
            'begin_time'  => strtotime($beginTime .' 00:00:00'),//卡券生效时间
            'end_time'    => strtotime($endTime .' 23:59:59'), //卡券失效时间
            'cash'        => $totalmoney,//卡卷金额——单位：分
            'auth'        => md5($api_key . 'create')
        );
        $data = base64_encode(json_encode($request));
        file_get_contents($url . $data);
        usleep(1000);//避免发送通知失败
    }
}

