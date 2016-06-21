<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 15-8-21
 * Time: 上午9:25
 */

namespace Library\MessageNotify;


class HongQunSms {
    const SMS_URL = 'http://222.77.181.44:8080/smsSystem/action/interface_deal/InterfaceDealAction.shtml?postMethod=';
    const KEY_MIX = 'EHwXQXg2qrdPAcbu4mTZQ==';//混合通道
    const KEY_PFT = 'qFBoctUIM2ZZc36kW3pbIA==';//票付通独立通道

    //发送短信
    public static function doSendSMS($tel,$content,$user_key='EHwXQXg2qrdPAcbu4mTZQ=='){
        $method = 'doSendSMS';
        $request = <<<XML
<?xml version='1.0' encoding='UTF-8'?>
  <reqinfo>
    <user_key>{$user_key}</user_key>
    <mobiles>{$tel}</mobiles>
	<sms_content>{$content}</sms_content>
    <send_time></send_time>
  </reqinfo>
XML;
        $response = self::post($method,$request);

        // $this->getSmsBalance(); //检查剩余条数
        if(strlen($response)>=36) {
            pft_log('api/sms',"发送成功:$response|$tel|$content|$user_key", 'month');
            return ['code'=>200];
        }
        pft_log('api/sms',"发送失败:$response|$tel|$content|$user_key", 'month');
        return ['code'=>201];
    }

    public static function getSmsBalance(){
        $method = 'getSmsBalance';
        $request=<<<XML
<?xml version='1.0' encoding='UTF-8'?>
  <reqinfo>
    <user_key>qFBoctUIM2ZZc36kW3pbIA==</user_key>
  </reqinfo>
XML;
        $response =self::post($method,$request);
        if(is_numeric($response) && $response < 1000) {
            self::doSendSMS("18060610535","鸿群短信剩余条数".$response."，请联系鸿群尽快充值，否则此短信会一直发送", self::KEY_PFT);
        }
    }


    public static function post($method,$request){
        $post_url = self::SMS_URL.$method;
        $ch = curl_init();//打开
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        $response  = curl_exec($ch);
        curl_close($ch);//关闭
        return $response;
    }
} 