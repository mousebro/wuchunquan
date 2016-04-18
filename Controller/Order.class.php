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
use Model\Order\OrderTools;

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
        if (PHP_SAPI=='cli') return true;

        $data   = I('post.data');
        $token  = I('post.token');
        if ($token != md5(self::TOKEN_SALT . $data))
        {
            parent::apiReturn(401,[], '认证失败');
        }
        $this->data = json_decode(base64_decode($data));
        if (!$this->data) {
            parent::apiReturn(403,[],'数据格式错误,请检查是否为标准的JSON');
        }
    }

    /**
     * 闸机快速下单——根据门票，支付码
     *
     */
    public function QuickOrder()
    {

        $tid        = intval($this->data->tid);
        $auth_code  = trim($this->data->auth_code);
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

        $salerId  = $this->data->salerid;
        $orderNum = $this->data->ordernum;
        $personId = $this->data->personid;
        $ordertel = $this->data->ordertel;
        $vcode    = $this->data->code;
        $ordertime_begin    = $this->data->ordertime_begin;
        $ordertime_end      = $this->data->ordertime_end;
        $orderStatus        = isset( $this->data->order_status ) ? $this->data->order_status : '0|1|2|3';
        $payStatus          = isset( $this->data->pay_status ) ? $this->data->pay_status : '0|1|2';
        $tid = 0;
        $member             = $this->data->member;
        $aid                = $this->data->aid;
        //if (!$member && !$aid) {
        //    parent::apiReturn('403',[],'供应商ID与购买者ID不能全为空');
        //}
        //print_r($this->data);exit;
        //echo $salerId;
        //exit;
        /**
         * 票付通订单统一查询搜索接口
         *
         * @param int $sid 商户ID
         * @param int $mid 分销ID，批量：|分隔
         * @param int $lid 景区ID
         * @param int $tid 门票ID
         * @param string $ltitle 景区模糊标题
         * @param string $ttitle 门票模糊标题
         * @param string $btime1 下单时间1
         * @param string $etime1 下单时间2
         * @param string $btime2 预计游玩时间1
         * @param string $etime2 预计游玩时间2
         * @param string $btime3 订单完成时间1
         * @param string $etime3 订单完成时间2
         * @param string $ordernum 订单号
         * @param string $oname 取票人姓名
         * @param string $otel 取票人手机
         * @param string $status 状态（0未使用|1已使用|2已过期|3被取消|4凭证码被替代|5被终端修改|6被终端撤销）
         * @param string $pays 支付状态（0景区到付|1成功|2未支付）
         * @param int $fromt 所属（只查询分销时传空）
         * @param int $orderby 排序(1下单时间|2游玩时间|3实际使用时间|4商户ID|5景区标题|6取消时间)
         * @param int $sort 升序或降序（0升序|1降序)
         * @param int $rstart 21记录起始指针
         * @param int $n 22返回条数
         * @param int $c 23返回类型（0详细1返回总数2逗号隔开字符串#订单数,票数,总数）
         * @param int $ordermode 24下单方式（0正常分销商下单1普通用户下单2手机下单 注：1、2下支付方式只能是账户余额或支付宝,取票人手机当作登录号）
         * @param string $payinfo 25支付（为空不做筛选0帐号余额支付1支付宝2使用供应商可用金额支付)
         * @param int $pmode 26单位结算（0或空不做筛选1单结订单）
         * @param string $remotenum 27/远端订单号,
         * @param int $origin  28/客源地
         * @param string $p_type 产品类型（A景区B线路）
         * @param string $order_confirm 30/确认订单状态(0无需确认 1待确认 2已确认未验证 3已确认已验证 4确认后取消 5确认后修改)
         * @param string $aid 31/供应商ID（批量：|分隔）
         * @param int $concat 32/关联订单(0显示所有订单 1显示关联订单【订单号不能为空】）
         * @param int $ifpack 33/套票（默认0正常 1套票 2子票）
         * @param int $flags 34/直销0转分销1
         * @param int $areacode 35地区编码,
         * @param null $contacttel  36联系人手机
         * @param string $personId 37 身份证
         * @return string
         */
        $xml = $this->soap->Order_Globle_Search($salerId, $member, 0, 0, $tid, '', '',
            $ordertime_begin, $ordertime_end,'','','', '',//13订单完成时间
            $orderNum, '', $ordertel, $orderStatus, $payStatus, '',/*19排序*/ 1,/*20降序*/ 0, 100,
             0,/*23详细*/ '', '',0,'',0,'','',/*30确认订单状态*/$aid,0,'',0,0,'', $personId, $vcode
            );
        echo $xml;
    }

    /**
     * 现金支付/会员卡支付
     */
    public function QuickPayOffline()
    {
        $ordernum       = $this->data->ordernum;
        $pay_total_fee  = (int)$this->data->total_fee;
        $pay_channel    = 4;
        $sourceT        = (int)$this->data->sorceT;//4=>现金 5 =>会员卡
        $pay_to_pft     = false;
        //$soap = new \ServerInside();
        $res = $this->soap->Change_Order_Pay($ordernum,-1, $sourceT, $pay_total_fee, 1,'','',1,
            $pay_to_pft, $pay_channel);
        if ($res==100) {
            parent::apiReturn(200, [], '支付成功');
        }
        parent::apiReturn(201,[], '支付失败');
    }

    /**
     * 订单销售记录
     */
    public function OrderSaleLog()
    {
        
    }
    /**
     * 订单汇总
     */
    public function Summary()
    {


    }
    public function PackageOrderCheck($args)
    {
        if (PHP_SAPI != 'cli') parent::apiReturn(0, [], 'Invalid Access');
        $time_begin = $args[3];
        if (!$time_begin) {
            $time_begin = date('Y-m-d H:00:00', strtotime('-1 hours'));
        }
        $time_end = date('Y-m-d H:i:00', strtotime("+30 mins", strtotime($time_begin)));
        echo $time_begin, '---', $time_end;
        $model = new OrderTools();
        $model->syncPackageOrderStatus($time_begin, $time_end);
    }
}