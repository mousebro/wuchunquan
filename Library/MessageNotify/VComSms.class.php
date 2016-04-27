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
    private $infoConf = [
        'account'   => 'fzpft',
        'password'  => 'ab8888',
    ];

    public function Send($tel, $msg, $search_id=null, $account=null, $pwd=null) {
        $account    = is_null($account) ? $this->infoConf['account'] :  $account;
        $pwd        = is_null($pwd) ? $this->infoConf['password'] : $pwd ;
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
        return $res;
    }
}