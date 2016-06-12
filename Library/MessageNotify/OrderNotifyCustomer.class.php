<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/12-012
 * Time: 11:18
 */
namespace Library\MessageNotify;

use Library\Model;
use Library\Resque\Queue;
use Model\Member\Member;
use Model\Order\OrderQuery;
use Model\Product\Ticket;

class OrderNotifyCustomer extends OrderNotifyBase
{
    const SMS_CONTENT_TPL = '入住凭证:{code}，您成功购买{pname}:{tnum}间，入住日期:{begintime}，离店日期:{endtime}。此为凭证，请妥善保管。详情及二维码:{link}';
    const SMS_CONTENT_TPL_GLY = '您已成功预订{pname}{tnum}间，凭证码：{code}.您可凭购票身份证、短信凭证码、二维码至厦门鼓浪屿漳州路3号皓月休闲度假俱乐部（皓月园内）办理入住，入住日期：{begintime}，离店日期：{endtime}。为方便您的游玩，建议您至少提前3天购买前往三丘田码头的船票,限取票当日使用，取后不退。祝您旅途愉快。详情及二维码：{link}';
    const SMS_CONTENT_TPL_H = '凭证号:{code},您已成功购买{begintime}{pname}:{tnum}张,{perinfo}{getaddr}。详情及二维码:{link}。';
    //【票付通】入住凭证：123456。您成功购买郁金香高级客房：3间，入住日期3月18日，离店日期3月22日。
//此为凭证，请妥善保管。详情及二维码:http://12301.cc/3u5235
//发给供应商的短信：

    public function GetPType()
    {
        return $this->p_type;
    }

    private function get_default_tpl($ptype)
    {
        $list = array(
            'DEFAULT' => '凭证号：{code}，您已成功购买了{pname}{tnum},消费日期：{begintime},{getaddr}，此为入园凭证,请妥善保管。详情及二维码:{link}',
            'C'       => self::SMS_CONTENT_TPL,
            'GLY'     => self::SMS_CONTENT_TPL_GLY,
        );
        return isset($list[$ptype]) ? $list[$ptype] : $list['DEFAULT'];
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
            ->where(['pid'=>$this->ticketInfo['pid']])
            ->find();

        if (!$row) {
            $row   = $Model->table('uu_sms_format') ->field('cformat,dtype,sms_account,sms_sign')
                ->where(['aid'=>$this->source_apply_did, 'pid'=>0])
                ->find();
        }
        return $row;
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
    private function SmsContent($p_name, $num, $begin_time, $end_time,$cformat='', $code=0,
                                $ret_format=1, $manualQr=false)
    {
        $search_replace = array();
        $orderObj   = new OrderQuery();
        if ( $code>0 ) {
            $search_replace['{code}'] = (string)$code;
        } else {
            //获取凭证号
            $orderInfo  = $orderObj->GetOrderInfo(OrderQuery::__ORDER_TABLE__, $this->order_num, 'code');
            $search_replace['{code}'] = $orderInfo[0]['code'];
        }
        $memberObj  = new Member();

        $dname      = $memberObj->getMemberCacheById($this->aid, 'dname');
        $search_replace['{dname}']      = $dname;
        $search_replace['{pname}']      = $p_name;
        $search_replace['{tnum}']       = $num > 0 ? ($num . ($this->p_type=='C' ? '' : '张')) : '';
        $search_replace['{begintime}']  = date('m月d日', strtotime($begin_time));
        $search_replace['{endtime}']    = date('m月d日', strtotime($end_time));
        $search_replace['{getaddr}']    = $this->ticketInfo['getaddr'];
        //演出类产品
        if ($this->p_type=='H') {
            $PerInfo='';
            $orderInfo  = $orderObj->GetOrderInfo(OrderQuery::__ORDER_DETAIL_TABLE__, $this->order_num, 'series');
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
        $send_msg_word = trim($send_msg_word);
        $send_msg_word = str_replace(array(" ", "　"), array("", ""), $send_msg_word);
        return $send_msg_word;
    }

    public function Send($p_name, $num, $begin_time, $end_time, $code=0)
    {
        $this->p_type = $p_type  = strtoupper($this->p_type);
        $sms_tpl = $this->SmsTemplate();
        $cformat = $sms_sign = '';
        if ($sms_tpl) {
            $cformat  = $sms_tpl['cformat'];
            $sms_sign = $sms_tpl['sms_sign'];
        }
        //离店时间要加一天
        $end_time = date('Y-m-d', strtotime('+1 days', strtotime($end_time)));
        $sms_content = $this->SmsContent($p_name, $num, $begin_time, $end_time, $cformat,$code);
        if (!empty($sms_sign) ) {
            $sms_content = "【{$sms_sign}】$sms_content";
        }
        $job_id = Queue::push('notify', 'SmsNotify_Job',  array(
                'dtype'     => $sms_tpl['dtype'],
                'ordernum'  =>$this->order_num,
                'mobile'    => $this->order_tel,
                'msg'       => $sms_content,
                'mid'       => $this->aid,//供应商ID（收费人）
                'sms_account'=> $sms_tpl['sms_account']
        ));
        return $job_id;
        //pft_log('queue/sms', $sms_content, 'month');
        $ret = array();
        if ($sms_tpl['dtype']==0) {
            //使用集时通发送短信
            $Taccount='';
            if (isset($sms_tpl['sms_account']) && $sms_tpl['sms_account']) $Taccount=$sms_tpl['sms_account'];
            $ret = VComSms::doSendSMS($this->order_tel, $sms_content, $this->order_num, $Taccount);
        }
        elseif ( $sms_tpl['dtype']==1) {
            $channel = empty($channel) ? HongQunSms::KEY_MIX : HongQunSms::KEY_PFT;
            $ret = HongQunSms::doSendSMS($this->order_tel, $sms_content, $channel);
        }
        if ($ret['code']==200) {
            $msglen     = utf8Length($sms_content);
            $m      = ceil($msglen/67);
            $memOjb = new \Model\Member\Member();
            $memOjb->ChargeSms($this->aid, $m, 0, $this->order_num);
            return true;
        }
        return false;
        //非酒店订单 设置了鸿群发送渠道 || $p_type=='C'
    }

    /**
     * 自定义凭证号短信
     *
     * @param $p_name
     * @param $num
     * @param $begin_time
     * @param $end_time
     * @param string $code  凭证号
     * @param bool $qrLink 是否显示自定义凭证号的二维码链接
     * @return mixed
     */
    public function SendWithVcode($p_name, $num, $begin_time, $end_time,
                                  $code, $qrLink=false)
    {
        $p_type  = strtoupper($this->p_type);
        $sms_tpl = $this->SmsTemplate();
        $cformat = $sms_sign = '';
        if ($sms_tpl) {
            $cformat  = $sms_tpl['cformat'];
            $sms_sign = $sms_tpl['sms_sign'];
        }
        //离店时间要加一天
        $end_time = date('Y-m-d', strtotime('+1 days', strtotime($end_time)));
        $sms_content = $this->SmsContent($p_name, $num, $begin_time, $end_time,
            $cformat, $code, 'string', $qrLink);
        if (!empty($sms_sign) ) {
            $sms_content = "【{$sms_sign}】$sms_content";
        }
        return $this->soap->reSend_SMS_Global_PL($this->order_num, $this->aid, $this->fid, $sms_content);
//        return $this->SendSms($sms_content, $sms_account, $dtype, 'PFT_CHANNEL');
    }

    /**
     * 微信通知下单的人
     *
     * @param string $wx_open_id
     * @param string $time
     * @param string $name
     * @param string $itemName
     * @param string $itemData
     * @param string $ordernum
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
        $jobId = Queue::push('notify',
            'WxNotify_Job', array(
                'openid'    => $wx_open_id,
                'data'      => $data,
                'tplId'     => 'NEW_ORDER',
                'color'     => '#FF0000',
                'url'       => "http://503334.12301.cc/wx/html/order_detail.html?fromt=f542e9fac6e76f4b3b66422d49e5585c&ordernum=$ordernum"
                )
        );
        return $jobId;
    }

    /**
     * 发送通知
     *
     * @param array $order 订单数据，根据DisOrder::orer
     * @param bool $send2ApplyEr
     * @param bool $useWxNotify
     * @return int
     */
    public function SendByOrderInfo($ordernum, $send2ApplyEr = true, $useWxNotify = true)
    {
        $orderQuery = new OrderQuery();
        $order = $orderQuery->GetOrderInfo(OrderQuery::__ORDER_TABLE__, $ordernum,
            'ordertime,ordername,begintime,endtime,tnum,ordertel,member,tid,lid,remsg');
        parent::SetParam(
            $order[0]['ordertel'],
            $order[0]['member'],
            $order[0]['tid'],
            $ordernum,
            $order[0]['lid']
        );

        $unit = $this->p_type == 'C' ? '间' : '张';
        $p_name  = $this->ltitle . $this->ticketInfo['title'].$order['tnum'].$unit;
        $itemData = "{$order['mainOrder']->UUttitle}:{$order['mainOrder']->UUtnum} $unit；";
        //是否联票
        $childOrder = $orderQuery->GetLinkOrders($ordernum, 'tnum,ordertel,begintime,member,tid,lid');
        $time_list = array( $order[0]['begintime'] );
        if (count($childOrder) > 0) {
            $ticketObj      = new Ticket();
            foreach ($childOrder as $child) {
                $time_list[] = $child['begintime'];
                $ticketInfo = $ticketObj->getTicketInfoById($child['tid'], 'title,getaddr');
                $itemData .= "\n{$ticketInfo['title']}:{$child['tnum']} $unit；";
                $p_name .= "{$ticketInfo['title']}{$child['tnum']}$unit,";
            }
        } else {
            $time_list[] = $order[0]['begintime'];
        }
        if ($useWxNotify && $wx_open_id = $this->WxNotifyChk($order[0]['member'], 1)) {
            $this->WxNotifyCustomer($wx_open_id,
                $order[0]['ordertime'],
                $order[0]['ordername'],
                $this->ltitle,
                $itemData,
                $ordernum
            );
        }
        //TODO::供应商通知
        if ($this->p_type != 'C' && $send2ApplyEr) {
            $this->OrderNotify('AFTER_PAY', $order);
        }
        //判断是否需要发送凭证码
        $model = new Model('slave');
        $map = ['tid'=>$this->ticketInfo['pid']];
        $sendVoucher = $model->table('uu_land_f')->where($map)->getField('sendVoucher');
        if ($sendVoucher==1) {
            pft_log('queue/sms',"该票类设置了不发送凭证码，发送失败:".json_encode(func_get_args(), JSON_UNESCAPED_UNICODE), 'month');
            return '该票类设置了不发送凭证码，发送失败';
        }
        //短信发送次数超过3次的不发送
        if ($order[0]['remsg']>3) {
            return 116;
        }

        //非酒店类型的订单
        if ($this->p_type != 'C') {
            return $this->Send($p_name, 0, $order[0]['begintime'], $order[0]['endtime']);
        }

        sort($time_list);
        $this->begin_time = array_shift($time_list);
        $this->end_time   = array_pop($time_list);

        if ($send2ApplyEr) {
            $this->SmsToApplyErHotel(
                (string)$order['mainOrder']->UUordername,
                $p_name,
                intval($order['mainOrder']->UUtnum),
                $this->begin_time,
                $this->end_time,
                $this->order_num,
                (string)$order['mainOrder']->UUmemo
            );
        }
        return $this->Send($p_name, intval($order['mainOrder']->UUtnum),
            $this->begin_time, $this->end_time, 'C');
    }

    /**
     * 短信凭证号加密编码
     *
     * @param $n
     * @return string
     */
    public static function url_sms($n){
        include '/var/www/html/open/Hashids/SmsHashids.php';
        $hashids = new \Hashids\SmsHashids();
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
            include '/var/www/html/open/Hashids/SmsHashids.php';
            $hashids = new \Hashids\SmsHashids();
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