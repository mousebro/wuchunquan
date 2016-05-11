<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/9-009
 * Time: 16:37
 */

namespace Controller\Order;


use Library\Controller;
use Library\Dict\OrderDict;

class OrderQuery extends Controller
{
    const ADMINID = 1;
    private $model;
    //$show_tel  = include_once 'saleProduct_showTel.php';
    private $members;
    private $lands;
    private $tickets;
    private $orders;
    private $self;
    private $memberId;
    private $output;//最终输出的数据
    private $machine_order_mode = [10,12,15,16];
    public function __construct()
    {
        $this->model = new \Model\Order\OrderQuery();
    }
    public function __destruct()
    {
        unset($this->lands);
        unset($this->members);
        unset($this->tickets);
        unset($this->orders);
    }
    /**
     * 设置当前用户ID
     *
     * @param int $memberId
     * @return void
     */
    public function setCurrentMember($memberId = -1)
    {
        if ($memberId>0) $this->memberId = $memberId;
        else $this->memberId = $_SESSION['sid'];
    }
    public function OrderList()
    {
        print_r($_POST);
        /*Array
        (
            [current_page] => 1
            [page_size] => 15
            [time_type] => 1
            [select_type] => 6
            [gmode] => 2
            [sale_mode] => 1
            [select_text] => 阿宝从
            [btime] =>
            [etime] =>
            [orderby] =>
            [sort] =>
            [amid] => 4
        )*/
        $tid = 0;
        $page_size      = I('post.page_size',20, 'intval');
        $current_page   = I('post.current_page',1, 'intval');
        $offset         = ($current_page - 1) * $page_size;

        $time_type      = I('post.time_type', 1, 'intval');
        $sale_mode      = I('post.sale_mode',0, 'intval');
        $pmode          = I('post.pmode', -1, 'intval');
        $select_type    = I('post.select_type', 0, 'intval');
        $select_text    = I('post.select_text');
        $btime          = I('post.btime', date('Y-m-d 00:00:00'));
        $etime          = I('post.etime', date('Y-m-d 23:59:59'));
        $gmode          = I('post.gmode');
        $sort           = I('post.sort');
        $serller_id     = I('post.amid');
        $pay_status     = $order_status = $order_mode = $pay_mode   = -1;
        if ($pmode == 0 )  $pay_status = 2; //未支付
        elseif ($pmode ==5) $pay_status = 1; //已支付
        elseif ($pmode == 6) $pay_status = 0; //现场支付
        else $order_status = $pmode - 1;

        switch ($select_type) {
            case 1:
                $order_num = $select_text;
                break;
            case 2:
                $lid        = $this->model->getLidByName($select_text);
                break;
            case 4:
                $order_name = $select_text;
                break;
            case 5:
                $remote_num = $select_text;
                break;
            case 6:
                $order_tel = $select_text;
                break;
            case 7:
                $coupon_name = $select_text;
                break;
        }

        switch ($time_type) {
            case 1://下单时间
                $timeStartKey = 'ordertimeStart';
                $timeEndKey   = 'ordertimeEnd';
                break;
            case 2://使用有效期
                $timeStartKey = 'begintimeStart';
                $timeEndKey   = 'endtimeEnd';
                break;
            case 3://验证时间
                $timeStartKey = 'dtimeStart';
                $timeEndKey   = 'dtimeEnd';
                break;
            case 4://游玩时间
                $timeStartKey = 'begintimeStart';
                $timeEndKey   = 'begintimeEnd';
                break;
        }
        if (isset($timeStartKey) && !empty($btime)) {
            $time_praram[$timeStartKey]  = $btime;
        }
        if (isset($timeEndKey) && !empty($etime)) {
            $time_praram[$timeEndKey]    = $etime;
        }
        $buyer_id = $_SESSION['sid'];
        //echo $buyer_id;exit;
        $data = $this->model->OrderList($offset, $page_size, $serller_id, $buyer_id, $lid, $tid, $order_num,
            $time_praram, $order_tel, $order_name, $remote_num, $pay_status, $order_status,
            $order_mode, $pay_mode);
        $this->tickets = $data['tickets'];
        $this->members = $data['members'];
        $this->lands   = $data['lands'];
        $this->orders  = $data['orders'];
        print_r($data);
    }



    /**
     * 订单分销关系处理
     *
     * @param array $orderInfo 订单数据
     * @return array
     */
    private function _orderTicketInfo($orderInfo, $is_main=false)
    {
        $_orderkey = $is_main ?  $orderInfo['ordernum'] : $orderInfo['concat_id'];
        $this->output[$_orderkey]['tickets'][$orderInfo['tid']]  = [
            'main'      => $is_main,
            'id'        => $orderInfo['tid'],
            'tnum'      => $orderInfo['tnum'],
            'ordernum'  => $orderInfo['ordernum'],
            'title'     => $this->tickets[$orderInfo['tid']],
            'status'    => $orderInfo['status'],//使用状态
            'status_txt'=> OrderDict::DictOrderStatus()[$orderInfo['status']],//使用状态
        ];

        if ($orderInfo['aids'] != 0) {
            $aids = $orderInfo['aids'] . ',' . $orderInfo['member'];
        }
        elseif ($orderInfo['aid'] !== $orderInfo['member']) {
            $aids =$orderInfo['aid'] . ',' .$orderInfo['member'];
        }
        else {
            $aids =$orderInfo['aid'];
        }
        //整合分销价格链
        if ($orderInfo['aids_price'] != 0) {
            $aids_price =$orderInfo['aids_price'] . ',' .$orderInfo['tprice'];
        } else {
            $aids_price =$orderInfo['tprice'];
        }
        $aids_price = explode(',', $aids_price);
        $aids       = explode(',', $aids);
        //而是把整条分销链都取出来
        if ($_SESSION['sid'] == 1) {

        }
        else {
            //处理价钱 转分销上下级分别是谁
            $key = array_search($this->self, $aids);
            $this->output[$_orderkey]['seller_id'] = $aids[$key + 1] ? $aids[$key + 1] : $orderInfo['member'];//卖给谁
            $this->output[$_orderkey]['buyer_id']  = $aids[$key - 1] ? $aids[$key - 1] : $this->self;//向谁买
            $this->output[$_orderkey]['seller_name']  = $this->members[$this->output[$_orderkey]['sell_id']];
            $this->output[$_orderkey]['buyer_name']   = $this->members[$this->output[$_orderkey]['buy_id']];
            //再次购买的权限
            if ($_SESSION['sid'] == $orderInfo['member']) {
                $this->output[$_orderkey]['can_buy_again'] = 'pid=' . $orderInfo['pid'] . '&aid=' . $orderInfo['aid'];
            }
            //第一级供应商不显示买入价格,最末级分销商不显示卖出价格
            if (count($aids) == 1) {
                $sell_price = $aids_price[$key];
                $buy_price  = 0;
            }
            else {
                $sell_price = $aids_price[$key] ? $aids_price[$key] : 0;
                $buy_price  = $aids_price[$key - 1] ? $aids_price[$key - 1] : 0;
            }
            $this->output[$_orderkey]['tickets']['buy_price'] = $buy_price;
            $this->output[$_orderkey]['tickets']['sell_price'] = $sell_price;
            $this->output[$_orderkey]['buy_money']  += $buy_price * $orderInfo['tnum'];
            $this->output[$_orderkey]['sell_price'] += $sell_price * $orderInfo['tnum'];
        }
        return true;
    }


    private function orderTicketInfo($orders)
    {
        //主票
        $this->_orderTicketInfo($orders['main'], true);
        if (isset($orders['links'])) {
            foreach ($orders['links'] as $order) {
                $this->_orderTicketInfo($order);
            }
        }
    }

    /**
     * 支付权限:最末级购买者,未支付
     *
     * @param string $ordernum
     * @param int $pay_status
     * @param int $memberId
     * @return bool
     */
    private function _pay($pay_status, $memberId)
    {
        return ($this->memberId == $memberId && $pay_status==2);
    }

    /**
     * 验证权限:第一级供应商/管理员,已支付,未使用/过期/部分验证
     *
     * @param $ordernum
     * @param $pay_status
     * @param $memberId
     * @param $status
     * @return bool
     */
    private function _check( $pay_status, $aid, $status)
    {
        if ($pay_status!=1) return false;
        if ($status==0 || $status==2 || $status==7 ) {
            if ($this->memberId!=self::ADMINID || $this->memberId!=$aid) return false;
        }
        return true;
    }

    /**
     * 取消订单
     *
     * @param int $order_mode
     * @param int $status
     * @param array $memberIdList
     * @return bool
     */
    private function _cancel($order_mode, $status, $memberIdList)
    {
        if ($status!=0) return false;
        if (in_array($status, [0,2,])) {

            if (in_array($order_mode, $this->machine_order_mode)) {
                return true;
            }
            if (!in_array($this->memberId, $memberIdList))
                return false;
            return true;
        }
        return false;
    }

    /**
     * 订单修改权限:未使用的订单
     *
     * @param $status
     * @param $memberIdList
     * @return bool
     */
    private function _modify($status, $memberIdList)
    {
        if ($status==0) {
            return in_array($this->memberId, $memberIdList);
        }
        return false;
    }

    /**
     * 重发短信通知
     *
     * @param $status
     * @param $memberIdList
     * @return bool
     */
    private function _sms($status, $memberIdList)
    {
        if ($status!=0) return false;
        if (!in_array($this->memberId, $memberIdList)) return false;
        return true;
    }
    /**
     * @param $order
     */
    private function orderHandlerPermission($order)
    {
        $permission = array();
        if ($order['aids'] != 0) {
            $apply_did = array_shift(explode(',', $order['aids']));
        } else {
            $apply_did = $order['aid'];
        }
        $permission['pay']      = $this->_pay($order['pay_status'], $order['member']);
        $permission['check']    = $this->_check($order['pay_status'], $apply_did, $order['status']);
        $permission['cancel']   = $this->_cancel($order['ordermode'], $order['status'],[self::ADMINID, $apply_did, $order['member']]);
        $permission['modify']   = $this->_modify( $order['status'],[self::ADMINID, $apply_did, $order['member']]);
        $permission['sms']      = $this->_sms($order['status'],[self::ADMINID, $apply_did, $order['aid'], $order['member']]);

        $this->output[$order['ordernum']]['permissions'] = $permission;
    }
    /**
     * @param array $item 订单数组
     * @param string $self 当查询人不是admin的时候的 param['self']
     * @param array $row 因为订单要以购买者和出售者嵌套循环 所有如果之前$row里有值了 就再传到这个函数里继续添加值
     * @param bool|false $excel 如果是导出到EXCEL 那么要多取一些值
     * @return array
     */
    private function format_order_data($data, $excel = false)
    {
        foreach ($data['orders'] as $_ordernum=>$orders) {
            //main
            $this->output[$_ordernum] = [];
            //判断订单取消\修改等权限 是否处理过一次 否则初始化权限变量 全部为没有
            $this->output[$_ordernum]['orderAlipay'] = 3;
            $this->output[$_ordernum]['orderCancel'] = 3;
            $this->output[$_ordernum]['orderAlter']  = 3;
            $this->output[$_ordernum]['orderResend'] = 3;
            $this->output[$_ordernum]['orderCheck']  = 3;

            $this->output[$_ordernum]['ordernum'][]   = $orders['main']['ordernum'];
            $this->output[$_ordernum]['begintime']  = $orders['main']['begintime'];
            $this->output[$_ordernum]['endtime']    = $orders['main']['endtime'];
            $this->output[$_ordernum]['begin_to_end'] =  $this->output[$_ordernum]['begintime'].'-'.$orders['main']['endtime'];

            $this->output[$_ordernum]['lid']        = $orders['main']['lid'];
            $this->output[$_ordernum]['ordertel']   = $orders['main']['ordertel'];
            $this->output[$_ordernum]['ordername']  = $orders['main']['ordername'];
            //下单时间 截取至分
            $this->output[$_ordernum]['ordertime'] = str_replace('-', '/', substr( $orders['main']['ordertime'], 0, 19));
            //预计游玩时间
            $this->output[$_ordernum]['playtime']  = str_replace('-', '/', $orders['main']['playtime']);
            $this->output[$_ordernum]['_dtime']    = $orders['main']['dtime'];
            $this->output[$_ordernum]['ltitle']    = $this->lands[$orders['main']['lid']];
            $this->output[$_ordernum]['paystatus']  = $orders['main']['paystatus'];
            $this->output[$_ordernum]['ordermode']  = $orders['main']['ordermode'];


            //$this->output[$_ordernum]['is_audit'] += ($item['audit_id'] && in_array($item['status'], [0, 7])); //2016-3-29 14:41:40 未使用和部分使用的订单显示为退票中
            if ( $this->memberId != 1 && $orders['main']['dtime'] == '0000-00-00 00:00:00'
                && $this->memberId != $orders['main']['member']
                && ! in_array($_SESSION['saccount'], $GLOBALS['show_tel']) )
            {
                $orders['main']['ordertel'] = substr_replace($orders['main']['ordermode']['ordertel'], "****", 3, 4);
            }
            $this->output[$_ordernum]['ordertel']  = $orders['main']['ordertel'] == '0' ? '空' : $orders['main']['ordertel'];//游客电话
            //订单票类数信息/价格据处理
            $this->orderTicketInfo($orders);
            //操作权限处理
            $this->orderHandlerPermission($orders['main']);
            //price handler end
        }//End foreach
        return true;
    }
}