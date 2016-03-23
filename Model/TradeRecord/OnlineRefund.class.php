<?php
/**
 * 在线支付交易原路退回
 *
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/22-022
 * Time: 16:34
 */
namespace Model\TradeRecord;
use Library\Model;

class OnlineRefund extends Model
{
    /**
     * 获取退交易号和交易渠道
     *
     * @param $ordernum
     * @return mixed
     */
    public function GetTradeLog($ordernum)
    {
        //TODO::获取交易号和支付渠道
        //0支付宝1微信支付
        $param = ["out_trade_no"=>$ordernum, "status"=>1,"trade_no"=>["neq",""]];
        return $this->Table('pft_alipay_rec')
            ->where($param)
            ->field('seller_email,trade_no,sourceT')
            ->limit(1)
            ->find();
    }

    /**
     * 退款记录
     *
     * @param int $aid
     * @param string $ordernum
     * @param int $TotalFee
     * @param int $daction 0增加 1减少
     * @param int $dtype 0下单 1取消
     * @return mixed
     */
    public function AddMemberLog($fid, $ordernum, $TotalFee, $daction=1, $dtype=1 )
    {
        $data = [
            'fid'       => $fid,
            'orderid'   => $ordernum,
            'total_fee' => $TotalFee,
            'money'     => $TotalFee,
            'rectime'   => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            'daction'   => $daction,
            'dtype'     => $dtype,
        ];
        return $this->Table('pft_member_alipay')->data($data)->add();
    }

    public function AddRefundLog($aid, $ordernum, $tnum, $TotalFee, $FeeCost,$subject,
                                 $refund_status=0,$handler_time='' )

    {
        $trade_log = $this->GetTradeLog($ordernum);
        if (!$trade_log) return false;
        //TODO::需要记录微信支付的appid
        if (!$trade_log['seller_email']) {
            $appid = PFT_WECHAT_APPID;
        } else {
            $obj  = json_decode($trade_log['seller_email']);
            $appid = $obj->sub_appid;
        }
        $data = [
            'aid'           => $aid,
            'ordernum'      => $ordernum,
            'refund_num'    => $tnum,
            'refund_money'  => $TotalFee,
            'refund_fee'    => $FeeCost,
            'refund_time'   => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            'subject'       => $subject,
            'trade_no'      => $trade_log['trade_no'],
            'sourceT'       => $trade_log['sourceT'],
            'refund_status' => $refund_status,
            'handler_time'  => $handler_time,
            'appid'         => $appid,
        ];
        return $this->Table('pft_order_refund')->data($data)->add();
    }

    /**
     * 获取退款记录数据
     *
     * @param int $log_id 退款记录ID
     * @return mixed
     */
    public function GetRefundLog($log_id)
    {
        return $this->Table('pft_order_refund')->where("id=$log_id")->limit(1)->find();
    }

    /**
     * 更新退款记录状态
     *
     * @param int $log_id 退款记录ID
     * @return bool
     */
    public function UpdateRefundLogOk($log_id)
    {
        return $this->Table('pft_order_refund')->where("id=$log_id")->save([
            'refund_status'=>1,
            'handler_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
        ]);
    }
}