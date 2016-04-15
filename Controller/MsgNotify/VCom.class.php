<?php
namespace Controller\MsgNotify;
use Library\Controller;

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/15-015
 * Time: 11:44
 */

class VCom extends Controller
{
    const TOKEN_SALT = '966*#06#';
    private $data;

    public  function __construct()
    {
        //if (!parent::isPost()) {
        //    exit;
        //}
    }

    public function verify()
    {
        $data   = I('post.data');
        $token  = I('post.token');
        if ($token != md5(self::TOKEN_SALT . $data))
        {
            //parent::apiReturn(401,[], '认证失败');
        }
        $this->data = json_decode(base64_decode($data));
        if (!$this->data) {
            //parent::apiReturn(403,[],'数据格式错误,请检查是否为标准的JSON');
        }
    }
    public function Send() {
        $this->verify();
        $tel        = $this->data->tel;
        $msg        = $this->data->msg;
        $account    = isset($this->data->account) ? $this->data->account : 'fzpft';
        $search_id  = isset($this->data->search_id) ? $this->data->search_id : -1;
        $url_post   = 'http://userinterface.vcomcn.com/Opration.aspx';
        $url        = 'http://fzif.chinavcom.cn/Opration.aspx';
        $pwd        = strtoupper(md5("ab8888"));
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
        var_dump($res);
        return $res;
    }

    public function Notify()
    {
        pft_log('vcome', json_encode($_GET));
        echo '0';
    }
}