<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/22-022
 * Time: 17:47
 */
namespace Controller;
define('BASE_PAY_DIR', '/var/www/html/alipay');
include BASE_WWW_DIR . '/class/Api.class.php';
include BASE_WX_DIR  . '/pay/wepay/WxPayPubHelper/WePay.class.php';
include BASE_PAY_DIR . '/pay_common/unipay/func/common.php';
include BASE_PAY_DIR . '/pay_common/unipay/func/SDKConfig.php';
include BASE_PAY_DIR . '/pay_common/unipay/func/secureUtil.php';
include BASE_PAY_DIR . '/pay_common/unipay/func/httpClient.php';

use Library\Controller;
use Api;
use WeChat\Pay2\Refund_pub;

class OnlineRefund extends Controller
{
    const UNION_MCH_ID  = 802350173720081;

    public function __construct()
    {
        $this->req_log = BASE_LOG_DIR . '/refund/req_'.date('ymd').'.log';
        $this->err_log = BASE_LOG_DIR . '/refund/err_'.date('ymd').'.log';
        $this->ok_log  = BASE_LOG_DIR . '/refund/ok_'.date('ymd') .'.log';
        $auth = I('get.auth');
        $comp = md5(md5(I('get.ordernum').md5(strrev(I('get.ordernum')))));
        if (empty( $auth ) || $auth!=$comp) {
            LogRefund('身份验证失败',LOG_NAME);
            exit("身份验证失败");
        }
        $this->log_id = I("get.log_id");
        if (!$this->log_id) exit("退款记录ID不能为空");
        $this->model  = new \Model\TradeRecord\OnlineRefund();
        $this->data  = $this->model->GetRefundLog($this->log_id);
        if (!$this->data) exit("退款记录不存在");
    }

    public function wx()
    {
        $appid      = $this->data->appid;
        $WePayConf = include BASE_WX_DIR . '/pay/wepay/WxPayPubHelper/WePay.conf.php';
        define('SSLCERT_PATH',$WePayConf[$appid]['sslcert_path']);
        define('SSLKEY_PATH', $WePayConf[$appid]['sslkey_path']);

        if (!$this->data->ordernum) {
            Api::Log('订单号为空', $this->err_log);
            Api::Response('订单号为空', Api::badRequestCode);
        }
        if (!$this->data->total_money || !is_numeric($this->data->total_money)) {
            Api::Log('支付总金额不能为空且必须为数字', $this->err_log);
            Api::Response('支付总金额不能为空且必须为数字', Api::badRequestCode);
        }
        if (!$this->data->refund_money || !is_numeric($this->data->refund_money)) {
            Api::Log('退款金额不能为空且必须为数字', $this->err_log);
            Api::Response('退款金额不能为空且必须为数字', Api::badRequestCode);
        }
        $uappid     = $WePayConf[$appid]['appid'];
        $mchid      = $WePayConf[$appid]['mchid'];
        $key        = $WePayConf[$appid]['key'];
        $app_secret = $WePayConf[$appid]['app_secret'];
        $out_trade_no = $this->data->ordernum;
        $refund_fee   = $this->data->refund_money;
        $out_refund_no = "$out_trade_no".time();//商户退款单号，商户自定义，此处仅作举例
        //总金额需与订单号out_trade_no对应，demo中的所有订单的总金额为1分
        $total_fee  = $this->data->total_money;
        $trade_no   = $this->data->trade_no;
        $refund = new Refund_pub($uappid, $mchid, $key, $app_secret);
        //设置必填参数
        if (!empty($trade_no)) {
            $refund->setParameter("transaction_id",$trade_no);//微信订单号
        }
        else {
            $refund->setParameter("out_trade_no","$out_trade_no");//商户订单号
        }
        $refund->setParameter("out_refund_no","$out_refund_no");//商户退款单号
        $refund->setParameter("total_fee", "$total_fee");//总金额
        $refund->setParameter("refund_fee","$refund_fee");//退款金额
        $refund->setParameter("op_user_id",$mchid);//操作员
        //非必填参数，商户可根据实际情况选填
        if (isset($WePayConf[$appid]['sub_appid'])) {
            $refund->setParameter("sub_appid",$WePayConf[$appid]['sub_appid']);//微信分配的子商户公众账号ID
            $refund->setParameter("sub_mch_id",$WePayConf[$appid]['sub_mchid']);//微信支付分配的子商户号
        }
        //调用结果
        $refundResult = $refund->getResult();
        Api::Log(json_encode($refundResult), RET_LOG);
        //商户根据实际情况设置相应的处理流程,此处仅作举例
        if ($refundResult["return_code"] == "FAIL") {
            Api::Log("通信出错：{$refundResult['return_msg']}", $this->ok_log);
            Api::Response("通信出错：".$refundResult['return_msg'], Api::badRequestCode);
        }
        elseif($refundResult["return_code"] == 'SUCCESS') {
            Api::Log("退款成功:退款记录ID[{$this->log_id}],订单号[{$out_trade_no}],总金额[{$total_fee}],退款金额[{$refund_fee}]", $this->ok_log);
            $this->model->UpdateRefundLogOk($this->log_id);
            Api::Response("退款成功", Api::okCode);
        }
        else{
            Api::Log(json_encode($refundResult), $this->err_log);
            Api::Response("退款失败,原因:{$refundResult['err_code_des']}", Api::badRequestCode);
        }
    }
    public function union()
    {
        $union_log_req = BASE_LOG_DIR . '/refund/union_req_'.date('ymd').'.log';
        $union_log_err = BASE_LOG_DIR . '/refund/union_err_'.date('ymd').'.log';
        $union_log_ok  = BASE_LOG_DIR . '/refund/union_ok_'.date('ymd').'.log';
        //定义证书路径，sign函数有用到
        $SDK_SIGN_CERT_PATH = BASE_PAY_DIR . '/pay_common/unipay/cert/cfca1234.pfx';
        $params = array(
            'version'       => '5.0.0',        //版本号
            'encoding'      => 'utf-8',        //编码方式
            'certId'        => getSignCertId (),    //证书ID
            'signMethod'    => '01',        //签名方法
            'txnType'       => '04',        //交易类型
            'txnSubType'    => '00',        //交易子类
            'bizType'       => '000201',        //业务类型
            'accessType'    => '0',        //接入类型
            'channelType'   => '07',        //渠道类型
            'orderId'       => 'REF'.$this->data->ordernum.'R'.$this->log_id,//为了同个订单多次退票时订单号不重复 R后面附带随机数字
            'merId'         => self::UNION_MCH_ID,    //商户代码，请修改为自己的商户号
            'origQryId'     => $this->data->trade_no,  //原消费的queryId，可以从查询接口或者通知接口中获取
            'txnTime'       => date('YmdHis', $_SERVER['REQUEST_TIME']),    //订单发送时间，重新产生，不同于原消费
            'txnAmt'        => $this->data->total_fee,    //交易金额，退货总金额需要小于等于原消费
            'backUrl'       => 'http://pay.12301.cc/union/BackReceive_Refund.php',//后台通知地址
            'reqReserved'   =>' 透传信息', //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现
        );
        sign($params);
        Api::Log(json_encode($params), $union_log_req);
        $result = sendHttpRequest ($params, SDK_BACK_TRANS_URL);
        //返回结果展示
        $refundResult = coverStringToArray($result);
        if(!verify($refundResult)){
            Api::Log("退款失败,原因:返回验证失败", $union_log_err);
            Api::Response("退款失败,原因返回验证失败", Api::badRequestCode);
        }
        if($refundResult['respMsg']=='成功[0000000]') {
            Api::Log("退款成功:退款记录ID[{$this->log_id}],订单号[{$this->data->ordernum}],总金额[{$this->data->total_fee}],退款金额[{$this->data->refund_fee}]", $union_log_ok);
            Api::Response("退款成功", Api::okCode);
        }
       else{
           Api::Log("退款失败,原因:".json_encode($refundResult), $union_log_err);
           Api::Response("退款失败,原因:{$refundResult['respMsg']}", Api::badRequestCode);
       }
    }
}