<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/27-2016
 * Time: 11:16
 * 即时通短信通知
 */
namespace Library\MessageNotify;
defined('PFT_INIT') OR exit('No direct script access allowed');

class VComSms
{
    const ACCOUNT  = 'fzpft';
    const PASSWORD = 'VTjL9vsp';

    private static $errCode = [
        '00'    => '成功',
        '01'    => '账号或密码错误',
        '02'    => '账号欠费',
        '09'    => '无效的接收方号码',
        '10'    => '网络或系统内部错误',
    ];

    public static function doSendSMS($tel, $msg, $search_id=null, $account=null, $pwd=null)
    {
        if (ENV!='PRODUCTION') {
            pft_log('queue/vcom', "发送短信|$tel:$msg");
            return ['code'=>200];
        }
        $account    = empty($account) ? self::ACCOUNT :  $account;
        $pwd        = is_null($pwd) ? self::PASSWORD : $pwd ;
        $pwd        = strtoupper(md5($pwd));
        $search_id  = is_null($search_id) ?  -1 : $search_id;

        $url_post   = 'http://userinterface.vcomcn.com/Opration.aspx';
        $ctime      = '';//发送时间，如为空则表示立即发送，日期格式：YYYY-MM-DD Hi24:MM:SS
        $content    = iconv("UTF-8","GBK",$msg);
        //要提交的内容
        $data=<<<XML
<Group Login_Name='$account' Login_Pwd='$pwd' OpKind='0' InterFaceID='0'>
<E_Time>$ctime</E_Time>
<Item>
     <Task>
        <Recive_Phone_Number>$tel</Recive_Phone_Number>
        <Content><![CDATA[$content]]></Content>
        <Search_ID>$search_id</Search_ID>
     </Task>
</Item>
</Group>
XML;
        $res = curl_post($url_post, $data);
        $code = 200;
        if ($res!='00') {
            $code = 401;
            pft_log('queue/vcom', "发送短信失败|$data");
        }
        return [
            'code'=>$code,
            'msg'=>"$res:" . self::$errCode[$res],
        ];
    }
}