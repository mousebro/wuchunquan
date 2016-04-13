<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/11-011
 * Time: 11:17
 */

namespace Controller;


use Library\Controller;
use Library\Response;

class Order extends Controller
{
    private $soap;
    private $data;
    const TOKEN_SALT = '966*#06#';
    public function __construct()
    {
        $this->verify();
        $this->soap  = new \SoapClient(null,array(
            "location" => "http://localhost/open/openService/pft_insideMX.php",
            "uri" => "www.16u.com?ac_16u=16ucom|pw_16u=c33367701511b4f6020ec61ded352059|auth_16u=true"));
    }

    private function verify()
    {
        $data   = I('post.data');
        $token  = I('post.token');
        if ($token != md5(self::TOKEN_SALT . $data))
        {
            parent::apiReturn('401',[], '认证失败');
        }
        $this->data = json_decode(base64_decode($data));
    }

    public function QuickOrder()
    {

        $tid        = intval(I('post.tid'));
        $auth_code  = trim(I('post.auth_code'));
        if (!$tid>0  || empty($auth_code)) {
            parent::apiReturn(401, [],'参数错误');
        }
        $res        = $this->soap->QuickOrder($tid, $auth_code);
        if ($res==100) {
            parent::apiReturn(200, [],'下单成功');
        } else {
            parent::apiReturn(202, [],'其他错误:'.$res);
        }
    }
    public function QuickSearch()
    {

        $salerId  = I('post.salerid');
        $orderNum = I('post.ordernum');
        $personId = I('post.personid');
        $ordertel = I('post.ordertel');
        $vcode    = I('post.code');
        $ordertime_begin    = I('post.ordertime_begin');
        $ordertime_end      = I('post.ordertime_end');
        $orderStatus     = '0|1|2|3';
        $payStatus     = '0|1|2';
        $tid = 0;
        $xml = $this->Order_Globle_Search($salerId, 0, 0, 0, $tid, '', '',
            $ordertime_begin, $ordertime_end,'','','', '',//订单完成时间
            $orderNum, '', $ordertel, $orderStatus, $payStatus, '', 1,/*19排序*/ 1,/*20降序*/ 0,
            100, 0,/*23详细*/ '', '',0,'',0,'','','',0,'',0,0,'', $personId, $vcode
            );
        echo $xml;
    }
}