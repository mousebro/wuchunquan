<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 2/24-024
 * Time: 11:27
 *
 * 在线交易记录模型
 */
namespace Model\TradeRecord;
use Library\Model;

class OnlineTrade extends Model
{
    protected $tableName = 'pft_alipay_rec';

    const CHANNEL_ALIPAY    = 0;
    const CHANNEL_WEPAY     = 1;
    const CHANNEL_UNIONPAY  = 2;//银联支付
    const CHANNEL_XUNPAY    = 3;//环迅支付

    const PAY_TYPE_MONEY    = 0;//账户余额支付
    const PAY_TYPE_ALIPAY   = 1;//支付宝支付
    const PAY_TYPE_CREDIT   = 2;//授信支付
    const PAY_TYPE_SELFBUY  = 3;//自供自销
    const PAY_TYPE_SPOT     = 4;//景区到付
    const PAY_TYPE_WEPAY    = 5;//微信支付
    const PAY_TYPE_UNION    = 7;//银联支付
    const PAY_TYPE_XUNPAY   = 8;//环迅支付


    /**
     * 添加支付交易日志
     *
     * @param $out_trade_no string 票付通订单号
     * @param $total_fee int 支付金额,单位:元
     * @param $body string 支付标题
     * @param $description string 支付描述
     * @param $sourceT int 支付渠道
     *
     * @return mixed
     */
    public function addLog($out_trade_no, $total_fee, $body,$description, $sourceT)
    {
        $total_fee_fen = $total_fee * 100;
        if (!$this->VerifyMoneyPay($out_trade_no, $total_fee_fen)) {
            return false;
        }
        if ($this->getLog($out_trade_no, $sourceT)) {
            return true;
        }
        $data = [
            'out_trade_no'  => $out_trade_no,
            'total_fee'     => $total_fee,
            'subject'       => $body,
            'description'   => $description,
            'sourceT'       => $sourceT,
        ];
        $id = $this->data($data)->add();
        if ($id>0) return $id;
        return $this->getDbError();
    }

    /**
     * 校验支付的金额是否大于等于订单金额
     *
     * @param string $out_trade_no 订单号
     * @param int $total_fee 金额：分
     * @return bool
     */
    public function VerifyMoneyPay($out_trade_no, $total_fee)
    {
        $total_money = $this->table('uu_ss_order')
            ->where(['ordernum'=>$out_trade_no])
            ->getField('totalmoney');
        return strval($total_fee) >= $total_money;
    }
    /**
     * 获取支付交易日志
     *
     * @param $ordern string 订单号
     * @param $sourceT int 支付渠道
     * @return mixed
     */
    public function getLog($ordern, $sourceT)
    {
        $where = [
            'out_trade_no'=> ':out_trade_no',
            'sourceT'     => ':sourceT',
        ];
        return $this->where($where)
            ->bind([':out_trade_no'=>$ordern, ':sourceT'=>$sourceT])
            ->field('status,royalty_parameters,total_fee')
            ->limit(1)
            ->find();
    }

    /**
     * 支付成功更新交易日志
     *
     * @param $ordern string 订单号
     * @param $trade_no string 第三方支付平台交易号
     * @param $sourceT int 支付渠道
     * @param $seller_email string 卖家信息
     * @param $buyer_email string 买家信息
     * @return bool
     */
    public function updateLog($ordern,$trade_no,$sourceT,$seller_email='', $buyer_email='' )
    {
        $where = [
            'out_trade_no'=> ':out_trade_no',
            'sourceT'     => ':sourceT',
        ];
        $data = [
            'seller_email'=> $seller_email,
            'buyer_email' => $buyer_email,
            'trade_no'    => $trade_no,
            'dtime'       => date('Y-m-d H:i:s'),
            'status'      => 1,
        ];
        return $this->where($where)
            ->bind([':out_trade_no'=>$ordern, ':sourceT'=>$sourceT])
            ->limit(1)
            ->save($data);
        //UPDATE pft_alipay_rec SET seller_email='$json_sell', buyer_email='$json_buy',status=1,dtime=now(),`trade_no`='$trade_no'
//        WHERE out_trade_no='$ordern' AND `sourceT`=$sourceT limit 1
    }

    /**
     * 支付后交互
     *
     * @param $tid int 门票ID
     * @return mixed
     */
    public function getTicketMpath($tid)
    {
        $tid = intval($tid);
        return $this->table('uu_jq_ticket')->where("id=$tid")->field("Mpath,Mdetails")->find();
    }

    public function secondRequest($tid, $ordern, $mid)
    {
        $tid = intval($tid);
        $mData = $this->table('uu_jq_ticket')->where("id=$tid")->field("Mpath,Mdetails")->find();
        if ($mData['Mpath']) {
            $relation_info_req = http_build_query(array(
                'Action'  => 'Relation_after_pay',
                'Ordern'  => $ordern,
                'Fid'     => (int)$mid,
            ));
            $send_time = 0;
            //设置超时为10s
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 10 //超时时间，单位为秒
                )
            ));
            do{
                $return = file_get_contents($mData['Mpath'].'?'.$relation_info_req, 0, $context);
                ++$send_time;
            }while(!$return && $send_time<3);
        }
    }

    private function change_db()
    {
        $dbConf = C('db');
        $this->db(1, $dbConf['summary'], true);
    }
    /**
     * 订单汇总
     *
     * @param string $bt
     * @param string $et
     * @return mixed
     */
    public function Summary($bt='', $et='')
    {
        $seller_email = [
            'pft12301@12301.cc',
            'pft12301@126.com',
            'pft_12301@12301.cc',
            '{"appid":"wx6ebc34778c9326f6","sub_appid":"wxd72be21f7455640d"}',
        ];

        $bt = empty($bt) ? date('Y-m-d 00:00:00', strtotime('- 1 days')) : $bt;
        $et = empty($et) ? date('Y-m-d 23:59:59', strtotime('- 1 days')) : $et;
        $where = [
            'status'=>1,
            'dtime'=>[array('gt',$bt),array('lt',$et)],
        ];
        $date = substr($bt, 0, 10);
        $data = $this->db(0)->table('pft_alipay_rec')
            ->where($where)
            ->field("sourceT as pay_channel,seller_email,SUM(total_fee * 100) AS total_money , '$date' as created_date")
            ->group('sourceT,seller_email')
            ->select();
        $details = $this->db(0)->table('pft_alipay_rec')
            ->where($where)
            ->field("sourceT as pay_channel,seller_email,total_fee * 100 AS pay_money, dtime as created_time, trade_no,out_trade_no AS ordernum")
            ->order('dtime desc')
            ->select();
        echo $this->getDbError();
        $this->change_db();
        $this->db(1)->table('pft_online_trade_summary')->addAll($data);
        $this->db(1)->table('pft_online_trade')->addAll($details);
        echo $this->getDbError();
        return $data;
    }
}