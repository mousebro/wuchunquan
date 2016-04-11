<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/11-011
 * Time: 11:17
 */

namespace Controller;


use Library\Controller;

class Order extends Controller
{
    public function QuickOrder()
    {
        $soap  = new \SoapClient(null,array(
            "location" => "http://localhost/open/openService/pft_insideMX.php",
            "uri" => "www.16u.com?ac_16u=16ucom|pw_16u=c33367701511b4f6020ec61ded352059|auth_16u=true"));
        $tid        = intval($_POST['tid']);
        $auth_code  = trim($_POST['auth_code']);
        if (!$tid>0  || empty($auth_code)) {
            exit('{"code":"401"}');
        }
        $res        = $soap->QuickOrder($tid, $auth_code);
        if ($res==100) {
            echo '{"code":"200"}';
        } else {
            echo '{"code":'.$res.'}';
        }
    }
}