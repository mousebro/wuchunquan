<?php
namespace Controller\pay;
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/16-016
 * Time: 15:43
 *
 * 微信支付
 */
use WeChat;
class OrderWxPay
{
    /**
     * 订单支付
     */
    public function order()
    {
        if (!is_numeric($_POST['total_fee']) || !$_POST['total_fee']) {
            echo '{"status":"fail","msg":"金额不对"}';
            exit;
        }
        if (!is_numeric($_POST['out_trade_no']) || !$_POST['out_trade_no']) {
            echo '{"status":"fail","msg":"订单号不对:'.$_POST['out_trade_no'].'"}';
            exit;
        }
        $appid      = base64_decode($_POST['appid']);
        if (!$_POST['openid']){
            exit('{"status":"fail","msg":"OPENID为空: openid='.$_POST['openid'].'--'.implode('|',array_keys($_POST)).'"}');
        }
        $money = floatval($_POST['total_fee']);
        $total_fee = $money * 100;
        $WePayConf = include '/var/www/html/wx/pay/wepay/WxPayPubHelper/WePay.conf.php';
        include("/var/www/html/wx/pay/wepay/WxPayPubHelper/WePay.class.php");
        $body       = mb_substr(trim($_POST['subject']), 0, 20, 'utf-8');   //太长微信支付会报错
        $uappid     = $WePayConf[$appid]['appid'];
        $mchid      = $WePayConf[$appid]['mchid'];
        $key        = $WePayConf[$appid]['key'];
        $app_secret = $WePayConf[$appid]['app_secret'];
        $sub_appid  = $WePayConf[$appid]['sub_appid'];
        $sub_mchid  = $WePayConf[$appid]['sub_mchid'];
        $notify_url = isset($WePayConf[$appid]['notify_url']) ? $WePayConf[$appid]['notify_url']
            : 'http://pay.12301.cc/order/mobile_wepay_notify.php';
        define('SSLCERT_PATH',$WePayConf[$appid]['sslcert_path']);
        define('SSLKEY_PATH', $WePayConf[$appid]['sslkey_path']);
        //$pub_obj = new WeChat\Pay2\Common_util_pub($uappid, $mchid, $key, $app_secret);
        $out_trade_no = $_POST['out_trade_no'];
        $openid       = $_POST['openid'];

        //小惠测试
        if ($openid=='oNbmEuIbDzjp5Vf7boB-5OK9vkv0' || $openid=='oNbmEuDdAEWDS_a02HYFlzNYFUTg') {
            //$notify_url = 'http://wx.12301.cc/pay/wepay/notify_url_new.php';
            $notify_url = 'http://pay.12301.cc/order/mobile_wepay_notify.php';
        }
        $sourceT = 1;
        $model = new \Model\TradeRecord\OnlineTrade();
        $ret = $model->addLog($out_trade_no, $total_fee, $body, $body, $sourceT);
        if ($ret===false) {
            echo '{"status":"fail","msg":"记录时错误"}';
            exit;
        }
        //微信充值：使用jsapi接口
        $jsApi = new WeChat\Pay2\JsApi_pub($uappid, $mchid, $key, $app_secret);
        //=========步骤2：使用统一支付接口，获取prepay_id============
        //使用统一支付接口
        $unifiedOrder = new WeChat\Pay2\UnifiedOrder_pub($uappid, $mchid, $key, $app_secret, $sub_appid, $sub_mchid);
        //设置统一支付接口参数
        if (!empty($sub_appid)) {
            $unifiedOrder->setParameter("sub_openid", $openid);//
        } else {
            $unifiedOrder->setParameter("openid", $openid);//
        }
        $unifiedOrder->setParameter("body", $body);//商品描述
        $unifiedOrder->setParameter("out_trade_no","$out_trade_no");//商户订单号
        $unifiedOrder->setParameter("total_fee", $total_fee);//总金额，单位分
        $unifiedOrder->setParameter("notify_url", $notify_url);//通知地址
        $unifiedOrder->setParameter("trade_type","JSAPI");//交易类型
        $unifiedOrder->setParameter("time_start", date('YmdHis'));//交易起始时间
        $expire_time_min = (isset($_POST['expire_time']) && is_numeric($_POST['expire_time'])) ? $_POST['expire_time'] : 21600;//默认15天，21600分钟
        $expire_time =  date('YmdHis', strtotime("+$expire_time_min mins"));
        $unifiedOrder->setParameter("time_expire", $expire_time);//交易结束时间
        /*
         * time_expire 否 String(14) 订 单 失 效 时 间 ， 格 式 为
        yyyyMMddHHmmss，如 2009 年
        12 月 27 日 9 点 10 分 10 秒表
        示为 20091227091010。时区
        为 GMT+8 beijing。该时间取
        自商户服务器
         */
        $prepay_id = $unifiedOrder->myGetPrepayId();
        //=========步骤3：使用jsapi调起支付============
        $jsApi->setPrepayId($prepay_id);
        $jsApiParameters = $jsApi->getParameters();
        echo '{"status":"ok","msg":"","data":'.$jsApiParameters.'}';
    }
}