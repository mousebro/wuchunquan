<?php
namespace Library\MessageNotify;
use LaneWeChat\Core\OpenExt;
use Library\Model;
use Library\Resque\Queue;
use Model\Member\Member;
use Model\Order\OrderQuery;
use Model\Wechat\WxMember;

/**
 * 【票付通】凭证号:{code}，您已成功购买了【{dname}】{pname}:{tnum}间，
 * 入住时间:{begintime}，离店时间:{endtime}，取票信息:{getaddr}，此为凭证，请妥善保管。
 * 详情及二维码:{link}
 */
class OrderNotify {
    private $order_tel;
    private $unit;
    private $pid;
    private $p_type;
    private $order_num;
    private $sellerId;
    private $title;
    private $buyerId;
    private $not_to_buyer;

    /**
     * @var Model
     */
    private $model;
    const SMS_FORMAT_STR  = 1;
    const SMS_FORMAT_ARR  = 2;


    const SMS_CONTENT_TPL = '入住凭证:{code}，您成功购买{pname}:{tnum}间，入住日期:{begintime}，离店日期:{endtime}。此为凭证，请妥善保管。详情及二维码:{link}';
    const SMS_CONTENT_TPL_GLY = '您已成功预订{pname}{tnum}间，凭证码：{code}.您可凭购票身份证、短信凭证码、二维码至厦门鼓浪屿漳州路3号皓月休闲度假俱乐部（皓月园内）办理入住，入住日期：{begintime}，离店日期：{endtime}。为方便您的游玩，建议您至少提前3天购买前往三丘田码头的船票,限取票当日使用，取后不退。祝您旅途愉快。详情及二维码：{link}';
    const SMS_CONTENT_TPL_H = '凭证号:{code},您已成功购买{begintime}{pname},{perinfo}{getaddr}。详情及二维码:{link}。';
    //【票付通】入住凭证：123456。您成功购买郁金香高级客房：3间，入住日期3月18日，离店日期3月22日。
//此为凭证，请妥善保管。详情及二维码:http://12301.cc/3u5235
//发给供应商的短信：

    public function __construct($ordernum, $buyerId, $aid, $mobile,$pid=0, $sellerID=0, $ptype=0, $title='', $not_to_buyer=0)
    {
        $this->model            = new Model('slave');
        $this->order_num        = $ordernum;
        $this->sellerId         = $sellerID;
        $this->buyerId          = $buyerId;
        $this->order_tel        = $mobile;
        $this->p_type           = $ptype;
        $this->unit             = $ptype=='C' ? '间' : '张';
        $this->aid              = $aid;
        $this->pid              = $pid;
        $this->title            = $title;
        $this->not_to_buyer     = $not_to_buyer;
        //pft_log('queue/vcom', 'OrderNotify:' . json_encode(func_get_args()));
    }

    public function Send( $code=0, $manualQr=false )
    {
        $infos = $this->getOrderInfo();
        if (!$this->pid) {
            $land= $this->model->table('uu_land')
                ->field('id,title,p_type,apply_did')
                ->find($infos['lid']);
            $this->p_type = $land['p_type'];
            $this->title  = $land['title'];
            $this->sellerId = $land['apply_did'];
            $this->pid    = $infos['master_pid'];
            unset($land);
        }
        if ($this->not_to_buyer!=1) {
            $this->BuyerNotify($infos, $code, $manualQr);
        }
        $this->SellerNotify($infos);
        return true;
    }
    /**
     * 获取短信里面与订单相关的信息
     *
     * @return array
     */
    private function getOrderInfo()
    {
        $order_info = $this->model->table('uu_ss_order')->where(['ordernum'=>$this->order_num])
            ->limit(1)
            ->field('lid,tid,tnum,ordername,begintime,endtime,code')
            ->find();
        $tid_list = [
            $order_info['tid']=>$order_info['tnum'],
        ];
        $time_list = [ $order_info['begintime'] ];
        $linksOrder = $this->model->table('uu_ss_order s')
            ->join('left join uu_order_fx_details f ON s.ordernum=f.orderid')
            ->where(['f.concat_id'=>$this->order_num, 's.ordernum'=>['neq',$this->order_num]])
            ->field('s.tid,s.tnum,s.begintime')
            ->select();
        if ($linksOrder) {
            foreach ($linksOrder as $item) {
                $tid_list[$item['tid']] = $item['tnum'];
                $time_list[] = $item['begintime'];
            }
        }
        else {
            $time_list[] = $order_info['begintime'];
        }
        $begin_time = $order_info['begintime'];
        $end_time   = $order_info['endtime'];
        //酒店类型比较特殊
        if ($this->p_type=='C') {
            sort($time_list);
            $begin_time = array_shift($time_list);
            $end_time   = array_pop($time_list);
            $end_time   = date('Y-m-d', strtotime('+1 days', strtotime($end_time)));
        }
        $tickets = $this->model->table('uu_jq_ticket')
            ->where( ['id'=>['in', array_keys($tid_list) ] ])
            ->getField('id, pid, title,getaddr', true);
        $pname   =  '';
        $getaddr = '';
        $pid_list = [];
        foreach ($tid_list as $tid=>$tnum) {
            if ($tid==$order_info['tid']) $master_pid = $tickets[$tid]['pid'];
            $getaddr = $tickets[$tid]['getaddr'];
            $pname  .= "\n{$tickets[$tid]['title']}{$tnum}{$this->unit},";
            $pid_list[] = $tickets[$tid]['pid'];
        }
        $map    = count($pid_list)>1 ? ['pid'=>['in', $pid_list]] : ['pid'=>$pid_list[0]];
        $model  = new Model('slave');
        $extInfo = $model->table('uu_land_f f')->join('uu_land l on l.id=f.lid')
            ->field('f.sendVoucher,f.pid,f.confirm_sms,f.confirm_wx,l.fax')
            ->where($map)
            ->limit(count($pid_list))
            ->select();

        return [
            'lid'       => $order_info['lid'],
            'buyer'     => $order_info['ordername'],
            'pname'     => rtrim($pname, ','),
            'code'      => $order_info['code'],
            'begintime' => $begin_time,
            'endtime'   => $end_time,
            'getaddr'   => $getaddr,
            'memo'      => $order_info['memo'],//订单备注信息
            'pid_list'  => $pid_list,
            'master_pid'=> $master_pid,
            'extAttrs'  => $extInfo,
        ];
    }
    /**
     * 购买者短信通知
     *
     * @param array $infos
     * @param $code
     * @return bool
     */
    public function BuyerNotify(Array $infos, $code, $manualQr)
    {
        $wx_open_id = $this->WxNotifyChk($this->buyerId);
        if ($wx_open_id!==false) {
            $this->WxNotifyCustomer($wx_open_id,
                date('Y-m-d H:i'),
                $infos['buyer'],
                $this->title,
                $infos['pname'],
                $this->order_num
            );
        }
        //是否发送凭证码（短信）到取票人手机  0 发送 1 不发送
        if ($infos['extAttrs'][0]['sendVoucher']==1) return true;
        $this->p_type = $p_type = strtoupper($this->p_type);
        $sms_tpl = $this->SmsTemplate();
        $cformat = $sms_sign = '';
        $sms_channel = 0;
        $sms_account = '';
        if ($sms_tpl) {
            $cformat        = $sms_tpl['cformat'];
            $sms_sign       = $sms_tpl['sms_sign'];
            $sms_channel    = $sms_tpl['dtype'];
            $sms_account    = $sms_tpl['sms_account'];
        }
        $code        = $code==0 ? $infos['code'] : $code;
        $sms_content = $this->SmsContent($this->title . $infos['pname'],  $infos['getaddr'],
            $infos['begintime'], $infos['endtime'], $cformat, $code, 1, $manualQr);
        if (!empty($sms_sign)) {
            $sms_content = "【{$sms_sign}】$sms_content";
        }
        $this->SaveSmsLog($this->order_tel,$sms_content, $this->order_num, $this->buyerId, $this->sellerId,$sms_account);
        $res = $this->SendSMS($this->order_tel, $sms_content, $sms_channel, $sms_account);
        return $res;
    }

    public function SaveSmsLog($ordertel, $smstxt, $ordern, $fid, $aid, $taccount, $send_now=0)
    {
        //insert sms_order set times=0,ordertel='$ordertel',smstxt='$sendmsg',ordernum='$ordern',fid=$member,aid=$aid,taccount='$Taccount',send_now=$smsSendNow
        $params = [
            'ordertel'  => $ordertel,
            'smstxt'    => $smstxt,
            'ordernum'  => $ordern,
            'fid'       => $fid,
            'aid'       => $aid,
            'taccount'  => $taccount,
            'send_now'  => $send_now,
        ];
        $model = new Model('localhost');
        $model->table('sms_order')->data($params)->add();
        unset($model);
    }

    /**
     * 向供应商发送通知信息
     *
     * @param array $infos 参数
     *              pid_list 预定的产品id
     *              ordername 下单人姓名
     *              pname 购买的产品
     *              note 备注信息
     *              play_time 消费日期
     * @return bool
     */
    public function SellerNotify(Array $infos)
    {
        //查看哪些产品需要发送短信给供应商
        $sms_notify_num = null;
        $confirm_wx     = 0;//接收微信通知标记
        foreach ($infos['extAttrs'] as $row) {
            if ($row['confirm_wx']) {
                $confirm_wx = 1;
            }
            if (($row['confirm_sms'] == 1 || $row['confirm_sms']==3 ) && $row['fax']) {
                $sms_notify_num = $row['fax'];
                break;
            }
        }
        switch ($this->p_type) {
            case 'A':
                $_time_name = "消费日期:";
                break;
            case 'B':
                $_time_name = "游玩时间:";
                break;
            case 'C':
                $_time_name = "入住日期:";
                break;
            default:
                $_time_name = '';
                break;
        }
        $sms_tpl = '预订通知：客人{dname}预订了{product_content}，{play_time},订单号：{ordernum}。联系电话：{tel}。';

        if ($infos['memo']) {
            $sms_tpl .= "客人备注信息：{$infos['memo']}。";
        }
        //微信通知
        if ($confirm_wx && ($wx_open_id = $this->WxNotifyChk($this->sellerId)) ) {
            //如果绑定了多个微信号
            if (is_array($wx_open_id)) {
                foreach ($wx_open_id as $openid) {
                    $this->WxNotifyProvider(
                        $openid,
                        $this->order_num,
                        $this->title ,
                        $infos['pname'],
                        $this->pid
                    );
                }
            }
            else {
                $this->WxNotifyProvider(
                    $wx_open_id,
                    $this->order_num,
                    $this->title ,
                    $infos['pname'],
                    $this->pid
                );
            }
        }
        //短信通知
        if (!is_null($sms_notify_num) && ismobile($sms_notify_num)) {
            if ($this->p_type=='C') {
                $sms_tpl = '预订通知：客人{dname}预订了{product_content}，{begintime}入住，{endtime}离店，订单号：{ordernum}。联系电话：{tel}。客人备注信息：{note}。';
                $search  = array('{dname}', '{product_content}', '{begintime}', '{endtime}', '{ordernum}', '{tel}', '{note}',);
                $replace = array($infos['buyer'], $this->title . $infos['pname'],
                    $infos['begintime'], $infos['endtime'], $this->order_num,
                    $this->order_tel, $infos['memo'],);
            }
            else {
                $search  = array('{dname}', '{product_content}', '{ordernum}', '{tel}', '{play_time}');
                $replace =  array($infos['buyer'], $this->title . $infos['pname'], $this->order_num, $this->order_tel, $_time_name.$infos['begintime']);
            }
            $sms_word = str_replace($search, $replace, $sms_tpl);
            return $this->SendSMS($sms_notify_num, $sms_word);
        }
        return true;
    }

    /**
     * 发送短信统一接口
     *
     * @param string $mobile 手机号
     * @param string $content 短信内容
     * @param int $sms_channel 发送渠道
     * @param string $sms_account 短信账号
     * @return bool
     */
    public function SendSMS($mobile, $content, $sms_channel=0, $sms_account='')
    {
        $content = str_replace(["\n", " ", "　",],'', $content);//过滤换行符，空格
        switch ( $sms_channel ) {
            case 1:
                $res = \Library\MessageNotify\HongQunSms::doSendSMS($mobile, $content);
                break;
            default:
                $res = \Library\MessageNotify\VComSms::doSendSMS($mobile,
                    $content,  $this->order_num,
                    $sms_account);
                break;
        }
        $msglen     = utf8Length($content);
        if ($res['code']==200) {
            //扣费
            $m      = ceil($msglen/67);
            $memOjb = new \Model\Member\Member();
            $res    = $memOjb->ChargeSms($this->sellerId, $m, $this->order_num);
            return $res['code']==200;
        }
        return false;
    }
    private function get_default_tpl($ptype)
    {
        $list = array(
            'DEFAULT' => '凭证号：{code}，您已成功购买了{pname}{tnum},消费日期：{begintime},{getaddr}，此为入园凭证,请妥善保管。详情及二维码:{link}',
            'C'       => self::SMS_CONTENT_TPL,
            'GLY'     => self::SMS_CONTENT_TPL_GLY,
            'H'       => self::SMS_CONTENT_TPL_H,
        );
        return isset($list[$ptype]) ? $list[$ptype] : $list['DEFAULT'];
    }
    /**
     * 短信内容
     *
     * @param string $p_name 产品名称
     * @param int $num 数量
     * @param string $begin_time 开始时间
     * @param string $end_time 结束时间
     * @param string $ptype 产品类型
     * @param string $cformat 短信模板
     * @param int $code 凭证号
     * @param int $ret_format 返回格式：1:string , 2:array
     * @param bool|false $manualQr 是否显示二维码（小彭对接时调用）
     * @return array|mixed|string
     */
    private function SmsContent($p_name, $getaddr, $begin_time, $end_time,$cformat='', $code=0,
                                $ret_format=1, $manualQr=false)
    {
        $search_replace = array();
        $search_replace['{code}'] = (string)$code;
        $memberObj  = new Member();
        $dname      = $memberObj->getMemberCacheById($this->aid, 'dname');
        $search_replace['{dname}']      = $dname;
        $search_replace['{pname}']      = $p_name;
        $search_replace['{tnum}']       = '';
        $search_replace['{begintime}']  = date('m月d日', strtotime($begin_time));
        $search_replace['{endtime}']    = date('m月d日', strtotime($end_time));
        $search_replace['{getaddr}']    = $getaddr;
        //演出类产品
        if ($this->p_type=='H') {
            $PerInfo='';
            $orderObj   = new OrderQuery();
            $orderInfo  = $orderObj->GetOrderInfo(OrderQuery::__ORDER_DETAIL_TABLE__,
                $this->order_num, 'series');
            if ($orderInfo[0]['series']){
                $PerInfo=unserialize($orderInfo[0]['series'])[6];
            }
            $search_replace['{perinfo}']=$PerInfo;
        }

        //返回数组格式的数据
        if ($ret_format == self::SMS_FORMAT_ARR) {
            return $search_replace;
        }

        $sms_tpl = empty($cformat) ? $this->get_default_tpl($this->p_type) : $cformat;
        pft_log('queue/vcom', 'SMSTPL:ptype=' .$this->p_type .';sms_tpl=' . $sms_tpl . ';cformat='.$cformat);
//        write_logs($this->p_type . ':sms_tpl=' . $sms_tpl);
        if (strpos($cformat, '{link}')!==false || strpos($sms_tpl, '{link}')!==false) {
            if ($manualQr) {
                $search_replace['{link}'] = "http://www.12301.cc/m_qr.html?$code";
            } else {
                $search_replace['{link}'] = "http://12301.cc/".self::url_sms($this->order_num);
            }
        }
        //鼓浪屿休闲屋特殊处理
        if ($this->aid==24863) {
            $send_msg_word = str_replace(array_keys($search_replace),
                array_values($search_replace), self::SMS_CONTENT_TPL_GLY);
        }
        else {
            $send_msg_word = str_replace(array_keys($search_replace),
                array_values($search_replace), $sms_tpl);
        }
        return $send_msg_word;
    }


    /**
     * 获取短信模板配置
     *
     * @return mixed
     */
    private function SmsTemplate()
    {
        $Model = new Model('slave');
        $row   = $Model->table('uu_sms_format') ->field('cformat,dtype,sms_account,sms_sign')
            ->where(['pid'=>$this->pid])
            ->find();

        if (!$row) {
            $row   = $Model->table('uu_sms_format') ->field('cformat,dtype,sms_account,sms_sign')
                ->where(['aid'=>$this->sellerId, 'pid'=>0])
                ->find();
        }
        return $row;
    }

    /**
     * 检查是否可以通过微信发送通知
     *
     * @param int $fid 会员id
     * @return bool/string false/微信openid
     */
    public function WxNotifyChk($fid, $useOtherAppid=false)
    {
        $wx = new WxMember();
        if (is_bool($useOtherAppid)) {
            $appid = $useOtherAppid ? WECHAT_APPID : OpenExt::PFT_APP_ID;
        } else {
            $appid = $useOtherAppid;
        }
        $data = $wx->getWxInfo($fid, $appid);

        $tmp    = array();
        $openid = array();
        foreach ($data as $row) {
            if ($row['verifycode']==1) {
                $openid[] = $row['fromusername'];
            }
            $tmp[] = $row['fromusername'];
        }
        $cnt = count($openid);
        if (($cnt==1||$cnt==0) && count($tmp)) {
            //如果都没有设置的话，那么随机选择一个
            return array_shift($tmp);
        }
        elseif(count($openid)) {
            return $openid;
        }
        return false;

    }

    /**
     * @param string $wx_open_id openid
     * @param string $ordernum 订单号
     * @param string $p_name 产品名称
     * @param string $remark 文字说明
     * @param string $pid 产品编号
     * @return string
     */
    public function WxNotifyProvider($wx_open_id, $ordernum, $p_name, $remark, $pid = '')
    {
//        {{first.DATA}}
//        订单号：{{OrderId.DATA}}
//        产品编号：{{ProductId.DATA}}
//        产品名称：{{ProductName.DATA}}
//        {{remark.DATA}}
//        您好！您有新订单：
        //2015年5月30日 22:02:13,更新：通知供应商只能通过票付通公众号来通知。
        $data = array(
            'first'     => array('value' => '您好！您有新订单：', 'color' => '#173177'),
            'OrderId'   => array('value' => $ordernum, 'color' => '#173177'),
            'ProductId' => array('value' => $pid, 'color' => '#ff9900'),
            'ProductName' => array('value' => $p_name, 'color' => '#173177'),
            'remark'    => array('value' => $remark, 'color' => ''),
        );
        $jobId = Queue::push('notify',
            'WxNotify_Job', array(
                'openid'    => $wx_open_id,
                'data'      => $data,
                'tplId'     => 'HOTEL_ORDER_MSG',
                'color'     => '#FF0000',
                'url'       => "http://wx.12301.cc/html/order_detail.html?fromt=f542e9fac6e76f4b3b66422d49e5585c&ordernum=$ordernum"
            )
        );
        return $jobId;
    }

    /**
     * 微信通知购买者
     *
     * @param $wx_open_id
     * @param $time
     * @param $name
     * @param $itemName
     * @param $itemData
     * @param $ordernum
     * @param int $totalPay
     * @return string
     */
    public function WxNotifyCustomer($wx_open_id, $time, $name,
                                     $itemName, $itemData, $ordernum,$totalPay=0)
    {
        switch ($this->p_type) {
            case 'A':
                $type = '门票订单';
                break;
            case 'B':
                $type = '线路订单';
                break;
            case 'C':
                $type = '酒店订单';
                break;
            case 'F':
                $type = '套票订单';
                break;
            case 'G':
                $type = '餐饮订单';
                break;
            default:
                $type = '门票订单';
                break;
        }
        $remark = ($totalPay>0? "消费金额：$totalPay 元\n" :'') . "订单号：{$ordernum}" ;
        $data = array(
            'first' => array('value' => '订单提交成功', 'color' => '#173177'),
            'tradeDateTime' => array('value' => $time, 'color' => '#173177'),
            'orderType' => array('value' => $type, 'color' => '#ff9900'),
            'customerInfo' => array('value' => $name, 'color' => '#173177'),
            'orderItemName' => array('value' => $itemName, 'color' => '#173177'),
            'orderItemData' => array('value' => $itemData, 'color' => '#173177'),
            'remark' => array('value' => $remark, 'color' => ''),
        );
        $openid_list = [];
//        Ext::Log(json_encode($data),'APPID='.WECHAT_APPID, '/var/www/html/wx/debug.txt');
//        write_logs(json_encode($data),'APPID='.WECHAT_APPID,'/var/www/html/wx/debug.txt');
        if (is_array($wx_open_id)) {
            $openid_list = $wx_open_id;
        }
        else {
            $openid_list[] = $wx_open_id;
        }
        foreach ($openid_list as $openid) {
            $job_id = Queue::push('notify', 'WxNotify_Job',
                array(
                    'data'  => $data,
                    'openid'=> $openid,
                    'tplid' => 'NEW_ORDER',
                    'url'   => "http://wx.12301.cc/html/order_detail.html?fromt=f542e9fac6e76f4b3b66422d49e5585c&ordernum=$ordernum",
                    'color' => '#FF0000'
                )
            );
            pft_log('wx/template_msg', 'jobId:' . $job_id);
        }
        return $job_id;
    }
    /**
     * 短信凭证号加密编码
     *
     * @param $n
     * @return string
     */
    public static function url_sms($n){
        $hashids = new \Library\Hashids\SmsHashids();
        return 'M'.$hashids->encode($n);
    }

    /**
     * 短信凭证号解密编码
     * @param $n
     * @return array|string
     */
    public static function url_sms_decode($n){
        if (strpos($n, 'M')!==false) {
            $n = substr($n, 1);
            $hashids = new \Library\Hashids\SmsHashids();
            return $hashids->decode($n);
        }
        $na=array("u","l","5","4","3","2","1","y","s","t");
        $n=(string)$n;
        $s="";
        $len=strlen($n);
        for ($i=0;$i<$len;$i++){
            $s.=array_search($n[$i], $na);
        }
        return $s;
    }
}