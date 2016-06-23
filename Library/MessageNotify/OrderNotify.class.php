<?php
namespace Library\MessageNotify;
use Library\Model;
use Library\Resque\Queue;
use Model\Member\Member;
use Model\Order\OrderQuery;

/**
 * 【票付通】凭证号:{code}，您已成功购买了【{dname}】{pname}:{tnum}间，
 * 入住时间:{begintime}，离店时间:{endtime}，取票信息:{getaddr}，此为凭证，请妥善保管。
 * 详情及二维码:{link}
 */
class OrderNotify {
    private $order_tel;
    private $unit;
    private $p_type;
    private $order_num;
    private $source_apply_did;

    /**
     * @var Model
     */
    private $model;
    const SMS_FORMAT_STR  = 1;
    const SMS_FORMAT_ARR  = 2;


    const SMS_CONTENT_TPL = '入住凭证:{code}，您成功购买{pname}:{tnum}间，入住日期:{begintime}，离店日期:{endtime}。此为凭证，请妥善保管。详情及二维码:{link}';
    const SMS_CONTENT_TPL_GLY = '您已成功预订{pname}{tnum}间，凭证码：{code}.您可凭购票身份证、短信凭证码、二维码至厦门鼓浪屿漳州路3号皓月休闲度假俱乐部（皓月园内）办理入住，入住日期：{begintime}，离店日期：{endtime}。为方便您的游玩，建议您至少提前3天购买前往三丘田码头的船票,限取票当日使用，取后不退。祝您旅途愉快。详情及二维码：{link}';
    const SMS_CONTENT_TPL_H = '凭证号:{code},您已成功购买{begintime}{pname}:{tnum}张,{perinfo}{getaddr}。详情及二维码:{link}。';
    //【票付通】入住凭证：123456。您成功购买郁金香高级客房：3间，入住日期3月18日，离店日期3月22日。
//此为凭证，请妥善保管。详情及二维码:http://12301.cc/3u5235
//发给供应商的短信：

    public function __construct($ordernum, $apply_did, $mobile, $ptype, $aid)
    {
        $this->model            = new Model('slave');
        $this->order_num        = $ordernum;
        $this->source_apply_did = $apply_did;
        $this->order_tel        = $mobile;
        $this->p_type           = $ptype;
        $this->unit             = $ptype=='C' ? '间' : '张';
        $this->aid              = $aid;
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
     * 获取短信里面与订单相关的信息
     *
     * @return array
     */
    private function getOrderInfo()
    {
        $order_info = $this->model->table('uu_ss_order')->where(['ordernum'=>$this->order_num])
            ->limit(1)
            ->field('tid,tnum,begintime,endtime,code')
            ->find();
        $tid_list = [ $order_info['tid']=>$order_info['tnum'] ];
        $linksOrder = $this->model->table('uu_ss_order s')
            ->join('left join uu_order_fx_detail f ON s.ordernum=f.orderid')
            ->where(['f.concat_id'=>$this->order_num])
            ->field('s.tid,s.tnum')
            ->select();
        if ($linksOrder) {
            foreach ($linksOrder as $item) {
                $tid_list[$item['tid']] = $item['tnum'];
            }
        }
        $tickets = $this->model->table('uu_jq_ticket')
            ->where( ['id'=>['in', array_keys($tid_list) ] ])
            ->getField('id, title', true);
        $pname = '';
        foreach ($tid_list as $tid=>$tnum) {
            $pname .= "{$tickets[$tid]}{$tnum}" . $this->unit;
        }
        return [
            'pname'     => $pname,
            'code'      => $order_info['code'],
            'begintime' => $order_info['begintime'],
            'endtime'   => $order_info['endtime'],
        ];
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
    private function SmsContent($p_name, $begin_time, $end_time,$cformat='', $code=0,
                                $ret_format=1, $manualQr=false)
    {
        $search_replace = array();
        $orderObj   = new OrderQuery();
        $search_replace['{code}'] = (string)$code;
        $memberObj  = new Member();
        $dname      = $memberObj->getMemberCacheById($this->aid, 'dname');
        $search_replace['{dname}']      = $dname;
        $search_replace['{pname}']      = $p_name;
        $search_replace['{tnum}']       = '';
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

    public function Send( $code=0 )
    {
        $this->p_type = $p_type = strtoupper($this->p_type);
        $sms_tpl = $this->SmsTemplate();
        $cformat = $sms_sign = '';
        if ($sms_tpl) {
            $cformat = $sms_tpl['cformat'];
            $sms_sign = $sms_tpl['sms_sign'];
        }
        $infos = $this->getOrderInfo();
        $code  = $code==0 ? $infos['code'] : $code;
        //离店时间要加一天
        $end_time = date('Y-m-d', strtotime('+1 days', strtotime($infos['endtime'])));
        $sms_content = $this->SmsContent($infos['pname'], $infos['begintime'], $end_time, $cformat, $code);
        if (!empty($sms_sign)) {
            $sms_content = "【{$sms_sign}】$sms_content";
        }
        switch ( $sms_tpl['dtype']) {
            case 1:
                $res = \Library\MessageNotify\HongQunSms::doSendSMS($this->order_tel, $sms_content);
                break;
            default:
                $res = \Library\MessageNotify\VComSms::doSendSMS($this->order_tel,
                    $sms_content,  $this->order_num,
                    $sms_tpl['sms_account']);
                break;
        }
        $msglen     = utf8Length($sms_content);
        if ($res['code']==200) {
            //扣费
            $m      = ceil($msglen/67);
            $memOjb = new \Model\Member\Member();
            $res    = $memOjb->ChargeSms($this->source_apply_did, $m, $this->order_num, $buyerId);
            return $res==200;
        }
        return false;
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
}