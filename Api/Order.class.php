<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/11-011
 * Time: 11:17
 */

namespace Api;


use Library\Controller;
use Model\Order\OrderCommon;
use Model\Order\OrderQuery;
use Model\Order\OrderTools;

class Order extends Controller
{
    private $soap;

    public function soap()
    {
        $this->soap  = parent::getSoap();
        //$this->verify();
        //$this->soap  = new \SoapClient(null,array(
        //    "location" => "http://localhost/open/openService/pft_insideMX.php",
        //    "uri" => "www.16u.com?ac_16u=16ucom|pw_16u=c33367701511b4f6020ec61ded352059|auth_16u=true"));
    }

    /**
     * 闸机快速下单——根据门票，支付码
     *
     */
    public function QuickOrder()
    {
        $tid        = I('post.tid');
        $auth_code  = I('post.auth_code');

        if (!$tid>0  || empty($auth_code)) {
            parent::apiReturn(401, [],'参数错误');
        }
        //echo $tid;exit;
        $this->soap();
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
        $orderStatus        = I('post.order_status') ? I('post.order_status') : '0|1|2|3';
        $payStatus          = I('post.pay_status') ? I('post.pay_status') : '0|1|2';
        $tid = 0;
        $member             = I('post.member');
        $aid                = I('post.aid');
        $oname              = '';//取票人姓名
        $sort               = 1;
        $offset             = I('post.offset', 0, 'intval');
        $top                = I('post.top', 100, 'intval');
        $this->soap();
        $xml = $this->soap->Order_Globle_Search($salerId, $member, 0, $tid, '', '',
            $ordertime_begin, $ordertime_end,'','','', '',//12订单完成时间
            $orderNum, $oname, $ordertel, $orderStatus, $payStatus, '', $sort,/*19排序*/ 1,/*20降序*/ $offset, $top,
             0,/*23详细*/ '', '',0,'',0,'','',/*30确认订单状态*/$aid,0,'',0,0,'', $personId, $vcode
            );
        echo $xml;
    }

    public function QuickVerify()
    {
        include '/var/www/html/new/d/class/Terminal_Check_Socket.class.php';
        $tc = new \Terminal_Check_Socket();
        $salerid       = I('post.salerid');
        $terminal_id   = I('post.terminal_id');
        $code          = I('post.code');

        $chkIns        = '499';
        $actiontime    = '2016-04-19 00:00:00';
        $terminal = $tc->Terminal_Check_In_Voucher($terminal_id,
            $salerid,$code,array(
                "vMode"=>7,
                "vCmd"=>$chkIns,
                'vCheckDate'=>$actiontime));
    }

    /**
     * 现金支付/会员卡支付/拉卡拉支付
     */
    public function QuickPayOffline()
    {
        $ordernum       = I('post.ordernum');
        $pay_total_fee  = I('post.total_fee') + 0;
        $pay_channel    = 4;
        $sourceT        = I('post.sorceT');//4=>现金 5 =>会员卡 6=>拉卡拉支付  11--拉卡拉（商户），12--拉卡拉（平台）
        $pay_to_pft     = false;
        $tradeno        = I('post.tradeno');//流水号
        $pay_conf       = include '/var/www/html/Service/Conf/pay.conf.php';
        $pay_account    = I('post.pay_account');
        $app_id         = I('post.app_id', 0);
        if ($pay_account!='' && in_array($pay_account, $pay_conf['pft']['lakala'])) {
            $pay_to_pft = true;
            $sourceT    = 7;//平台拉卡拉
        }
        if ($sourceT!=4 && $sourceT!=5 && $sourceT !=6 && $sourceT !=7) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '支付失败，支付方式不对');
        }
        if (empty($ordernum)) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '支付失败，订单号不能为空');
        }
        if (empty($pay_total_fee) || !$pay_total_fee) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '支付失败，订单金额格式不对');
        }
        $this->soap();
        if ($app_id=='android_terminal') {
            $pay_channel = 20;
        }
        $res = $this->soap->Change_Order_Pay($ordernum,$tradeno, $sourceT, $pay_total_fee, 1,'','',1,
            $pay_to_pft, $pay_channel);
        if ($res==100) {
            parent::apiReturn(parent::CODE_SUCCESS, [], '支付成功');
        }
        parent::apiReturn(parent::CODE_INVALID_REQUEST,[], '支付失败，订单号:' . $ordernum);
    }

    /**
     * 订单销售记录
     */
    public function OrderSaleLog()
    {
        $oc = new OrderCommon();
        $sale_op    = I('post.sale_op');
        $op_id      = I('post.op_id');
        $ad_flag    = I('post.ad_flag');
        $sale_type  = I('post.sale_type');
        $orders     = (array)$_POST['orders'];
        //if (!is_numeric($ordernum) || !$ordernum) {
        //    parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '订单号格式错误');
        //}
        //if (!is_numeric($sale_price) || !$sale_price) {
        //    parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '销售价格式错误');
        //}
        if (!is_numeric($sale_op) || !$sale_op) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '销售员ID格式错误');
        }
        $ad_flag    = $ad_flag ? $ad_flag+0 : 0;
        $sale_type  = $sale_type ? $sale_type+0 : 0;
        $res = $oc->OrderSaleLog($orders, $sale_op, $op_id, $ad_flag, $sale_type);
        if ($res) {
            parent::apiReturn(parent::CODE_SUCCESS,[],'操作成功');
        }
        parent::apiReturn(parent::CODE_SUCCESS,[],'操作成功');
    }
    /**
     * 订单汇总
     */
    public function Summary()
    {
        $start_date = strtotime(I('post.start_date'));
        $end_date   = strtotime(I('post.end_date'));
        $op_id      = I('post.op_id');//操作员ID
        $lid      = I('post.lid');//景点ID

        $query = new OrderQuery();
        $data  = $query->CTS_SaleSummary($start_date, $end_date,$op_id, $lid);
        if (is_array($data)) {
            parent::apiReturn(parent::CODE_SUCCESS,$data,'success');
        }
        parent::apiReturn(parent::CODE_NO_CONTENT, 'fail');

    }
    public function PackageOrderCheck($args)
    {
        if (PHP_SAPI != 'cli') parent::apiReturn(0, [], 'Invalid Access');
        $time_begin = $args[3];
        if (!$time_begin) {
            $time_begin = date('Y-m-d H:00:00', strtotime('-1 hours'));
        }
        $time_end = date('Y-m-d H:i:00', strtotime("+30 mins", strtotime($time_begin)));
        $model = new OrderTools();
        $model->syncPackageOrderStatus($time_begin, $time_end);
    }
}