<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/22-022
 * Time: 17:47
 * http://open.12301.test/pft_api.php?c=OnlineRefund&a=wx
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
    private $data;
    private $model;
    public function __construct()
    {
        $this->req_log = BASE_LOG_DIR . '/refund/req_'.date('ymd').'.log';
        $this->err_log = BASE_LOG_DIR . '/refund/err_'.date('ymd').'.log';
        $this->ok_log  = BASE_LOG_DIR . '/refund/ok_'.date('ymd') .'.log';
        Api::Log(json_encode($_POST), $this->req_log);
        $auth = I('post.auth');
        $ordern = I('post.ordernum');
        //$comp = md5(md5(.md5(strrev(I('post.ordernum')))));
        $comp = md5(md5($ordern).md5(strrev($ordern)));
        //echo $auth;exit;
        if (empty( $auth ) || $auth!=$comp) {
            Api::Log('身份验证失败' . $auth . ':'.$comp,$this->err_log);
            exit("身份验证失败". $auth . ':'.$comp);
        }
        $this->log_id = I("post.log_id");
        if (!$this->log_id) exit("退款记录ID不能为空");
        $this->model  = new \Model\TradeRecord\OnlineRefund();
        $data  = $this->model->GetRefundLog($this->log_id);
        if (!$data) exit("退款记录不存在");
        $this->data  = (object)$data;

        $pay_mode = I("post.pay_mode");
        //if (ENV!='PRODUCTION') $res = ['code'=>200];
        //else {
            if ($pay_mode==5) $res = $this->wx();
            elseif($pay_mode==7) $res = $this->union();
            elseif ($pay_mode==1) $res = $this->alipay();
        //}
        if ($res['code']==200) {
            $this->model->UpdateRefundLogOk($this->log_id);
            //print_r( $this->data);
            //$res = $this->model->AddMemberLog(
            //    $this->data->fid,
            //    $this->data->ordernum,
            //    $this->data->refund_money,
            //    0,
            //    1
            //    );
            //var_dump($res);
        }
    }

    public function wx()
    {
        $appid      = $this->data->appid ? $this->data->appid : PFT_WECHAT_APPID;
        $WePayConf = include BASE_WX_DIR . '/pay/wepay/WxPayPubHelper/WePay.conf.php';
        define('SSLCERT_PATH',$WePayConf[$appid]['sslcert_path']);
        define('SSLKEY_PATH', $WePayConf[$appid]['sslkey_path']);

        if (!$this->data->ordernum) {
            Api::Log('订单号为空', $this->err_log);
            Api::Response('订单号为空', Api::badRequestCode);
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
        $out_refund_no = I('post.ordernum').'_'.time();//商户退款单号，商户自定义，此处仅作举例
        //总金额需与订单号out_trade_no对应，demo中的所有订单的总金额为1分
        //获取总金额
        $trade_info = $this->model->GetTradeLog($out_trade_no);
        $total_fee  = $trade_info['total_fee']*100;

        $trade_no   = $this->data->trade_no;
        $refund = new Refund_pub($uappid, $mchid, $key, $app_secret);

        //设置必填参数
        if (!empty($trade_no)) {
            $refund->setParameter("transaction_id",$trade_no);//微信订单号
        }
        else {
            $refund->setParameter("out_trade_no",$out_trade_no);//商户订单号
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
        //var_dump($refundResult);
        Api::Log(json_encode($refundResult), $this->req_log);
        //商户根据实际情况设置相应的处理流程,此处仅作举例
        if ($refundResult["return_code"] == "FAIL") {
            Api::Log("通信出错：{$refundResult['return_msg']}", $this->ok_log);
            return ['code'=>400, 'msg'=>"通信出错,原因:{$refundResult['return_msg']}"];
        }
        elseif($refundResult["return_code"] == 'SUCCESS' && $refundResult['result_code']!='FAIL') {
            Api::Log("退款成功:退款记录ID[{$this->log_id}],订单号[{$out_trade_no}],总金额[{$total_fee}],退款金额[{$refund_fee}]", $this->ok_log);
            return ['code'=>200, 'msg'=>'退款成功'];

        }
        else{
            Api::Log(json_encode($refundResult), $this->err_log);
            return ['code'=>400, 'msg'=>"退款失败,原因:{$refundResult['err_code_des']}"];
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
            'certId'        => getSignCertId($SDK_SIGN_CERT_PATH),    //证书ID
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
            'txnAmt'        => $this->data->refund_money,    //交易金额，退货总金额需要小于等于原消费
            'backUrl'       => 'http://pay.12301.cc/union/BackReceive_Refund.php',//后台通知地址
            'reqReserved'   =>' 透传信息', //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现
        );
        sign($params, $SDK_SIGN_CERT_PATH);
        Api::Log(json_encode($params), $union_log_req);
        $result = sendHttpRequest ($params, SDK_BACK_TRANS_URL);
        //返回结果展示
        $refundResult = coverStringToArray($result);
        if(!verify($refundResult)){
            Api::Log("退款失败,原因:返回验证失败", $union_log_err);
            return ['code'=>400, 'msg'=>'退款失败,原因:验证失败'];
        }
        if($refundResult['respMsg']=='成功[0000000]') {
            Api::Log("退款成功:退款记录ID[{$this->log_id}],订单号[{$this->data->ordernum}],总金额[{$this->data->total_fee}],退款金额[{$this->data->refund_fee}]", $union_log_ok);
            return ['code'=>200, 'msg'=>'退款成功'];
        }
       else{
           Api::Log("退款失败,原因:".json_encode($refundResult), $union_log_err);
           return ['code'=>400, 'msg'=>'退款失败,原因:'.$refundResult['respMsg']];
       }
    }

    /**
     * 支付宝刷卡支付退款
     */
    public function alipay()
    {
        include '/var/www/html/alipay/Library/alipay_fuwuchuang/f2fpay/F2fpay.php';
        $f2fpay = new \F2fpay();
        $refund_fee   = number_format($this->data->refund_money / 100, 2);//元为单位
        $out_request_no = date('YmdHis') . $this->data->ordernum . mt_rand(1000,9999); // 标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传。
        Api::Log("before:appid={$this->data->appid},trade_no={$this->data->trade_no}, refund_fee=$refund_fee, ordernum={$this->data->ordernum}", $this->req_log);
        //多次退款需要提交原支付订单的商户订单号和设置不同的退款单号
        $refundResult = $f2fpay->refund($this->data->trade_no, $refund_fee, $out_request_no, $this->data->appid);
        Api::Log("result:".json_encode($refundResult), $this->req_log);
        if ($refundResult->alipay_trade_refund_response->code==10000) {
            return ['code'=>200, 'msg'=>'退款成功'];
        }
        Api::Log(json_encode($refundResult), $this->err_log);
        return ['code'=>400, 'msg'=>"退款失败,原因:{$refundResult['err_code_des']}"];

    }
}
/*错误码	              错误描述	     解决方案
ACQ.SYSTEM_ERROR	系统错误	       请使用相同的参数再次调用
ACQ.INVALID_PARAMETER	参数无效	   请求参数有错，重新检查请求后，再调用退款
ACQ.SELLER_BALANCE_NOT_ENOUGH	    卖家余额不足	商户支付宝账户充值后重新发起退款即可
ACQ.REFUND_AMT_NOT_EQUAL_TOTAL	    退款金额超限	检查退款金额是否正确，重新修改请求后，重新发起退款
ACQ.REASON_TRADE_BEEN_FREEZEN	    请求退款的交易被冻结	联系支付宝小二，确认该笔交易的具体情况
ACQ.TRADE_NOT_EXIST	交易不存在	   检查请求中的交易号和商户订单号是否正确，确认后重新发起
ACQ.TRADE_HAS_FINISHED	交易已完结	该交易已完结，不允许进行退款，确认请求的退款的交易信息是否正确
ACQ.TRADE_STATUS_ERROR	交易状态非法	查询交易，确认交易是否已经付款
ACQ.DISCORDANT_REPEAT_REQUEST	    不一致的请求	检查该退款号是否已退过款或更换退款号重新发起请求
ACQ.REASON_TRADE_REFUND_FEE_ERR	    退款金额无效	检查退款请求的金额是否正确*/