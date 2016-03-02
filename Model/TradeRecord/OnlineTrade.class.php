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
        $data = [
            'out_trade_no'  => $out_trade_no,
            'total_fee'     => $total_fee,
            'subject'       => $body,
            'description'   => $description,
            'sourceT'       => $sourceT,
        ];
        return $this->data($data)->add();
        //INSERT pft_alipay_rec SET out_trade_no='$out_trade_no',subject='$body',
//        total_fee='$money',description='$body',sourceT=$sourceT
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
        //$where = [
        //    'id'=> ':id',
        //];
        $sql = "SELECT Mpath,Mdetails FROM uu_jq_ticket WHERE id=$tid LIMIT 1";
        return $this->query($sql);
        //return $this->table('uu_jq_ticket')->where($where)
        //    ->bind([':id'=>$tid])
        //    ->field('Mpath,Mdetails')
        //    ->limit(1)
        //    ->find();
        //$sql = "select  from uu_jq_ticket where id=$tid limit 1";
        //$GLOBALS['le']->query($sql);
        //$GLOBALS['le']->fetch_assoc();
    }
}