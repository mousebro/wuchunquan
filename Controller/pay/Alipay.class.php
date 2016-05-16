<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/16-016
 * Time: 18:41
 */
namespace Controller\pay;
ini_set('display_errors', 'on');

include("/var/www/html/new/d/class/SubDomain.php");
include "/var/www/html/alipay/lib/alipay_submit_mobile.class.php";

class Alipay
{
    public function mobile_api()
    {
        define('BUYER', $_REQUEST['buy_id']);
        //include "/var/www/html/alipay/account.config.php";
        include("/var/www/html/alipay/alipay.config.world.php");
        //print_r($alipay_config);exit;
        $host = $_SERVER['HTTP_HOST'];
        if (isset($_REQUEST['domain'])) {
            $domain = "{$_REQUEST['domain']}/wx/html/";
        } elseif ($host == 'wx.12301.cc') {
            $domain = 'http://wx.12301.cc/';
        } else {
            $shop = \SubDomain::SubDomainConf();
            $domain = "http://{$shop['M_domain']}.12301.cc/wx/";
        }
        /**************************调用授权接口alipay.wap.trade.create.direct获取授权码token**************************/
        //返回格式
        $format = "xml";
        //必填，不需要修改
        //返回格式
        $v = "2.0";
        //必填，不需要修改
        //请求号//必填，须保证每次请求都是唯一
        $req_id = date('Ymdhis');
        //服务器异步通知页面路径
        //$notify_url = "http://wx.12301.cc/pay/alipay_v3.3/notify_url.php";
        //2016年4月21日17:59:51，切换
        $notify_url = "http://pay.12301.cc/order/mobile_alipay_notify.php";
        //需http://格式的完整路径，不允许加?id=123这类自定义参数
        //页面跳转同步通知页面路径
        $call_back_url = "{$domain}html/success.html";
        if ($_REQUEST['success_back_notify'] == 'mall') $call_back_url = "{$_REQUEST['domain']}/wx/mall/ordersuccess.html";
        //需http://格式的完整路径，不允许加?id=123这类自定义参数
        //操作中断返回地址
        $merchant_url = "{$domain}pay/alipay_v3.3/cancel.php";
        if ($_REQUEST['success_back_notify'] == 'mall') $merchant_url = "{$_REQUEST['domain']}/wx/pay/alipay_v3.3/cancel.php";
        //用户付款中途退出返回商户的地址。需http://格式的完整路径，不允许加?id=123这类自定义参数
        //卖家支付宝帐户
        //$seller_email = $_POST['WIDseller_email'];
        $seller_email = $alipay_config['seller_email'];
        //商户订单号
        $out_trade_no = $_REQUEST['out_trade_no'];
        //商户网站订单系统中唯一订单号，必填

        //订单名称
        $subject = $_REQUEST['subject'];
        //必填

        //付款金额
        $total_fee = $_REQUEST['total_fee'];
        //必填
        $expire_time = $_REQUEST['expire_time'] > 0 ? intval($_REQUEST['expire_time']) : 21600;//交易自动关闭时间（可空，默认值21600（即15天））
        //请求业务参数详细
        $req_data = '<direct_trade_create_req><notify_url>'
            . $notify_url . '</notify_url><call_back_url>'
            . $call_back_url . '</call_back_url><seller_account_name>'
            . $seller_email . '</seller_account_name><out_trade_no>'
            . $out_trade_no . '</out_trade_no><subject>'
            . $subject . '</subject><total_fee>'
            . $total_fee . '</total_fee>'
            . '<pay_expire>' . $expire_time . '</pay_expire>'
            . '<merchant_url>' . $merchant_url . '</merchant_url></direct_trade_create_req>';
        //必填
        /************************************************************/

        //构造要请求的参数数组，无需改动
        $para_token = array(
            "service" => "alipay.wap.trade.create.direct",
            "partner" => trim($alipay_config['partner']),
            "sec_id" => trim($alipay_config['sign_type']),
            "format" => $format,
            "v" => $v,
            "req_id" => $req_id,
            "req_data" => $req_data,
            "_input_charset" => trim(strtolower($alipay_config['input_charset']))
        );
        //记录数据
        $trade = new \Model\TradeRecord\OnlineTrade();
        $res = $trade->addLog($out_trade_no, $total_fee, $subject, $subject, 0);
        if (!$res) {
            exit('支付数据有误，请核对支付金额');
        }
        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestHttp($para_token);

        //URLDECODE返回的信息
        $html_text = urldecode($html_text);
        //解析远程模拟提交后返回的信息
        $para_html_text = $alipaySubmit->parseResponse($html_text);
        //获取request_token
        $request_token = $para_html_text['request_token'];


        /**************************根据授权码token调用交易接口alipay.wap.auth.authAndExecute**************************/

        //业务详细
        $req_data = '<auth_and_execute_req><request_token>' . $request_token . '</request_token></auth_and_execute_req>';
        //必填

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "alipay.wap.auth.authAndExecute",
            "partner" => trim($alipay_config['partner']),
            "sec_id" => trim($alipay_config['sign_type']),
            "format" => $format,
            "v" => $v,
            "req_id" => $req_id,
            "req_data" => $req_data,
            "_input_charset" => trim(strtolower($alipay_config['input_charset']))
        );
        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter, 'get', '确认');
        echo $html_text;
    }
}





?>