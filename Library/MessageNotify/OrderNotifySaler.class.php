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

class OrderNotifySaler extends OrderNotifyBase
{
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
            'first' => array('value' => '您好！您有新订单：', 'color' => '#173177'),
            'OrderId' => array('value' => $ordernum, 'color' => '#173177'),
            'ProductId' => array('value' => $pid, 'color' => '#ff9900'),
            'ProductName' => array('value' => $p_name, 'color' => '#173177'),
            'remark' => array('value' => $remark, 'color' => ''),
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
     * 酒店订单通知供应商
     *
     * @param string $dname 顾客姓名
     * @param string $pname 产品名称
     * @param int $num 预定间数
     * @param string $begintime 入住时间
     * @param string $endtime 离店时间
     * @param string $order_num 订单号
     * @param string $memo 备注信息
     * @return bool
     */
    public function SmsToApplyErHotel($dname, $pname, $num, $begintime,
                                      $endtime, $order_num, $memo)
    {
        if (!$memo ) $memo = '无';
        if (!is_null($this->begin_time)) {
            $begintime = $this->begin_time;
        }

        if (!is_null($this->end_time)) {
            $endtime = $this->end_time;
        }

        $begintime  = date('m月d日', strtotime($begintime));
        $endtime    = date('m月d日', strtotime('+1 days', strtotime($endtime)));

        $wx_remark = "预定人：{$dname}\n房间数：{$num}\n"
            . "入住时间：$begintime\n离店时间：$endtime\n"
            . "订单备注：{$memo}\n\n为了客人出行，请尽快确认，谢谢！";
        //先用微信通知
        if ($wx_open_id = $this->WxNotifyChk($this->source_apply_did)) {
            if (is_array($wx_open_id)) {
                foreach ($wx_open_id as $openid) {
                    $this->WxNotifyProvider($openid, $order_num, $pname, $wx_remark);
                }
            }
            else {
                $this->WxNotifyProvider($wx_open_id, $order_num, $pname, $wx_remark);
            }
        }
        //查看哪些产品需要发送短信给供应商
        $sql = <<<SQL
SELECT f.pid,f.confirm_sms,l.fax,l.apply_did FROM uu_land_f f
LEFT JOIN uu_land l ON l.id=f.lid
WHERE f.pid=$this->pid LIMIT 1
SQL;
//        echo $sql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (($row['confirm_sms']!=1 && $row['confirm_sms']!=3) || !$row['fax']) {
            return false;
        }

        $tpl    = '预订通知：客人{dname}预订了{pname}{num}间，{begintime}入住，{endtime}离店，订单号：{ordernum}。联系电话：{tel}。客人备注信息：{note}。';
        $search = array(
            '{dname}',
            '{pname}',
            '{num}',
            '{begintime}',
            '{endtime}',
            '{ordernum}',
            '{tel}',
            '{note}',
        );
        //UUmemo
        $replace_arr = array(
            $dname,
            $pname,
            $num,
            $begintime,
            $endtime,
            $order_num,
            $this->order_tel,
            $memo,
        );
        $smsTxt = str_replace($search, $replace_arr, $tpl);
//        echo $smsTxt;
        $num    = $this->soap->Send_SMS_V($row['fax'], $smsTxt);
        $msgLen = str_len2($smsTxt);
        $this->LogMoney($msgLen, $num);
    }

    /**
     * 非酒店订单发送短信/微信通知供应商
     *
     * @param string $data_type 数据类型:AFTER_SUBMIT标识下单后发送【$data为产品信息】，AFTER_PAY标识支付后发送【$data为订单信息】
     * @param array $data 产品数据或订单数据
     * @param string $customer_name 下单人姓名
     * @param string $order_num 订单号
     * @param string $tel 联系电话
     * @param null $note 备注信息
     * @param string $play_time 消费日期
     * @return bool
     */
    public function OrderNotify($data_type, Array $data, $customer_name = '',
                                $order_num = '', $tel = '', $note = null, $play_time='')
    {
        $order_content = $land_title = '';
        $pids = array();
        //提交订单后发生通知短信
        if ($data_type == 'AFTER_SUBMIT') {
            foreach ($data as $prod) {
                $pids[] = $prod['pid'];
                $land_title = $prod['ltitle'];
                $order_content .= "{$prod['ttitle']}{$prod['tnum']}张";
            }
        }
        elseif ($data_type == 'AFTER_PAY') {
            $this->pid = (string)$data['mainOrder']->UUpid;
            if (!$order_num) {
                $this->order_num = (string)$data['mainOrder']->UUordernum;
                $order_num = $this->order_num;
            }
            if ($data['mainOrder']->UUaids==0) {
                $this->source_apply_did = (int)$data['mainOrder']->UUaid;
            } elseif (strpos($data['mainOrder']->UUaids,',')!==false) {
                $this->source_apply_did = array_shift(
                    explode(',', (string)$data['mainOrder']->UUaids)
                );
            }

            //订单支付后发送短信
            $land_title = strval($data['mainOrder']->UUltitle);
            $pids[] = (int)$data['mainOrder']->UUpid;
            $play_time =  (string)$data['mainOrder']->UUplaytime;
            $order_content .= "{$data['mainOrder']->UUttitle}{$data['mainOrder']->UUtnum}张";
            $customer_name = strval($data['mainOrder']->UUordername);
            $note = strval($data['mainOrder']->UUmemo);
            $tel  = strval($data['mainOrder']->UUordertel);
            if (count($data['childOrder']) > 0) {
                foreach ($data['childOrder'] as $child) {
                    $pids[] = (int)$child->UUpid;
                    $order_content .= "{$child->UUttitle}{$child->UUtnum}张";
                }
            }
        }
        $play_time = substr($play_time, 0, 10);
        switch ($this->p_type) {
            case 'A':
                $_time_name = "消费日期:$play_time,";
                break;
            case 'B':
                $_time_name = "游玩时间:$play_time,";
                break;
            case 'C':
                $_time_name = "入住日期:$play_time,";
                break;
            default:
                $_time_name = '';
                break;
        }
        return $this->SmsToProvider($pids, $customer_name,
            $land_title, $order_content, $order_num, $tel, $note, $_time_name);
    }

    /**
     * 向供应商发送通知信息
     *
     * @param array $pids 预定的产品id
     * @param string $customer_name 下单人姓名
     * @param string $land_title 产品名称
     * @param string $order_content 购买的产品
     * @param string $order_num 订单号
     * @param string $tel 联系电话
     * @param string $note 备注信息
     * @param string $play_time 消费日期
     * @return bool
     */
    public function SmsToProvider($pids, $customer_name, $land_title, $order_content,
                                   $order_num, $tel, $note, $play_time)
    {
        //查看哪些产品需要发送短信给供应商
        $apply_did = $this->source_apply_did;
        $sms_account = array(7517=>'glyylq',24863=>'glyylq');
        $data = parent::GetApplerTel($pids);
        $sms_notify_num = null;
        $confirm_wx = 0;//接收微信通知标记
        foreach ($data as $row) {
            if ($row['confirm_wx']) {
                $confirm_wx = 1;
            }
            if (($row['confirm_sms'] == 1 || $row['confirm_sms']==3 ) && $row['fax']) {
                $sms_notify_num = $row['fax'];
                break;
            }
        }
        $sms_tpl = '预订通知：客人{dname}预订了{product_content}，{play_time}订单号：{ordernum}。联系电话：{tel}。';
        if ($note) {
            $sms_tpl .= "客人备注信息：{$note}。";
        }
        //微信通知
        if ($confirm_wx && ($wx_open_id = $this->WxNotifyChk($apply_did)) ) {
            //如果绑定了多个微信号
            if (is_array($wx_open_id)) {
                foreach ($wx_open_id as $openid) {
                    $this->WxNotifyProvider(
                        $openid,
                        $order_num,
                        $land_title,
                        $order_content,
                        $this->pid
                    );
                }
            }
            else {
                $this->WxNotifyProvider(
                    $wx_open_id,
                    $order_num,
                    $land_title,
                    $order_content,
                    $this->pid
                );
            }
        }
        //短信通知
        if (!is_null($sms_notify_num) && ismobile($sms_notify_num)) {
            $sms_word = str_replace(
                array('{dname}', '{product_content}', '{ordernum}', '{tel}', '{play_time}'),
                array($customer_name, $land_title . $order_content, $order_num, $tel, $play_time),
                $sms_tpl
            );

            $jobId =Queue::push('notify',
                'SmsNotify_Job', array(
                    'mobile'    => $sms_notify_num,
                    'msg'       => $sms_word,
                    'mid'       => $apply_did,)
            );
            return $jobId;
        }
        return true;
    }

}