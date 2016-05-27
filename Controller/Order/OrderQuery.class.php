<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/9-009
 * Time: 16:37
 */

namespace Controller\Order;


use Library\Cache\Cache;
use Library\Controller;
use Library\Dict\OrderDict;

class OrderQuery extends Controller
{
    private $model;
    //$show_tel  = include_once 'saleProduct_showTel.php';
    const ADMIN_ID = 1;
    const MAX_EXCEL_ROWS = 30000;//允许导出的最大Excel条数
    private $members;
    private $lands;
    private $tickets;
    private $orders;
    private $memberId;
    private $output;//最终输出的数据
    private $machine_order_mode = [10,12,15,16];

    public function __construct()
    {
        if (!$_SESSION['memberID']) {
            self::apiReturn(self::CODE_AUTH_ERROR,[], '登录超时或未登陆');
        }
        $this->model = new \Model\Order\OrderQuery();
        $this->setCurrentMember();
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

    /**
     * 检测是否允许导出Excel：不能超过一个月的时间
     *
     * @return bool
     */
    private function _check_excel($time1, $time2)
    {
        if (I('get.export_excel',0, 'intval')==1) {
            if ((strtotime($time2) - strtotime($time1)) / 86400 > 31)
                return false;
            return true;
        }
        return false;
    }
    public function OrderList()
    {
        //print_r($_POST);

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
        $btime          = I('post.btime');
        $etime          = I('post.etime');
        $gmode          = I('post.gmode');
        $sort           = I('post.sort');
        $serller_id     = I('post.amid');
        $btime          = $btime ? $btime : date('Y-m-d 00:00:00');
        $etime          = $etime ? $etime : date('Y-m-d 23:59:59');
        $pay_status     = $order_status = $order_mode = $pay_mode   = -1;
        if ($pmode == 0 )  $pay_status = 2; //未支付
        elseif ($pmode ==5) $pay_status = 1; //已支付
        elseif ($pmode == 6) $pay_status = 0; //现场支付
        else $order_status = $pmode - 1;

        $export_excel = $this->_check_excel($btime, $etime);//是否导出EXCEL
        if ($export_excel) {
            $offset     = 0;
            $page_size  = self::MAX_EXCEL_ROWS;
        }
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
            default:
                $timeStartKey = 'ordertimeStart';
                $timeEndKey   = 'ordertimeEnd';
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

        $total = $this->model->OrderList($offset, $page_size, $serller_id, $buyer_id, $lid, $tid, $order_num,
            $time_praram, $order_tel, $order_name, $remote_num, $pay_status, $order_status,
            $order_mode, $pay_mode, \Model\Order\OrderQuery::GET_TOTAL_ROWS);
        $this->tickets = $data['tickets'];
        $this->members = $data['members'];
        $this->lands   = $data['lands'];
        $this->orders  = $data['orders'];
        //print_r($data);
        $this->format_order_data($export_excel);
        //print_r($this->output);
        if (!empty($this->output)) {
            self::apiReturn(200, ['list'=>array_values($this->output), 'total'=>$total]);
            //parent::ajaxReturn(array_values($this->output), 'JSON', JSON_UNESCAPED_UNICODE);
        }
        self::apiReturn(self::CODE_NO_CONTENT);
        //parent::ajaxReturn([], 'JSON', JSON_UNESCAPED_UNICODE);
    }
    /**
     * 订单分销关系处理
     *
     * @param array $orderInfo 订单数据
     * @return array
     */
    private function _orderTicketInfo($orderInfo, $is_main=false)
    {
        $_key = $is_main ?  $orderInfo['ordernum'] : $orderInfo['concat_id'];
        $this->output[$_key]['tickets'][$orderInfo['tid']]  = [
            'main'      => $is_main,
            'id'        => $orderInfo['tid'],
            'tnum'      => $orderInfo['tnum'],
            'ordernum'  => $orderInfo['ordernum'],
            'title'     => $this->tickets[$orderInfo['tid']]['title'],
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
        if ($_SESSION['sid'] == self::ADMIN_ID) {

        }
        else {
            //处理价钱 转分销上下级分别是谁
            $key = array_search($this->memberId, $aids);
            $this->output[$_key]['seller_id']      = isset($aids[$key + 1]) ? $aids[$key + 1] : $orderInfo['member'];//卖给谁
            $this->output[$_key]['buyer_id']       = isset($aids[$key - 1]) ? $aids[$key - 1] : $this->memberId;//向谁买
            $this->output[$_key]['seller_name']    = $this->members[$this->output[$_key]['seller_id']];
            $this->output[$_key]['buyer_name']     = $this->members[$this->output[$_key]['buyer_id']];
            //再次购买的权限
            if ($this->memberId == $orderInfo['member']) {
                $this->output[$_key]['can_buy_again'] = 'pid=' . $this->tickets[$orderInfo['tid']]['pid'] . '&aid=' . $orderInfo['aid'];
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
            $this->output[$_key]['tickets'][$orderInfo['tid']]['buy_price'] = $buy_price;
            $this->output[$_key]['tickets'][$orderInfo['tid']]['sell_price'] = $sell_price;
            $this->output[$_key]['buy_money']  += $buy_price * $orderInfo['tnum'];
            $this->output[$_key]['sell_price'] += $sell_price * $orderInfo['tnum'];
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
     * @param int $pay_status
     * @param int $memberId
     * @return bool
     */
    protected function _pay($pay_status, $memberId)
    {
        return ($this->memberId == $memberId && $pay_status==2);
    }

    /**
     * 验证权限:第一级供应商/管理员,已支付,未使用/过期/部分验证
     *
     * @param $pay_status
     * @param $aid
     * @param $status
     * @return bool
     */
    protected function _check( $pay_status, $aid, $status)
    {
        if ($pay_status!=1) return false;
        if ($status==0 || $status==2 || $status==7 ) {
            if ($this->memberId!=self::ADMIN_ID || $this->memberId!=$aid) return false;
        }
        return true;
    }

    /**
     * 取消订单: 未使用/已过期，硬件（云票务）订单
     *
     * @param int $order_mode
     * @param int $status
     * @param array $memberIdList
     * @return bool
     */
    protected function _cancel($order_mode, $status, $memberIdList)
    {
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
    protected function _modify($status, $memberIdList)
    {
        if ($status==0) {
            return in_array($this->memberId, $memberIdList);
        }
        return false;
    }

    /**
     * 重发短信通知：未使用的订单
     *
     * @param int $status 订单状态
     * @param array $memberIdList 会员ID列表
     * @return bool
     */
    protected function _sms($status, $memberIdList)
    {
        if ($status!=0) return false;
        if (!in_array($this->memberId, $memberIdList)) return false;
        return true;
    }
    /**
     * @param $order
     */
    protected function orderHandlerPermission($order)
    {
        $permission = array();
        if ($order['aids'] != 0) {
            $apply_did = array_shift(explode(',', $order['aids']));
        } else {
            $apply_did = $order['aid'];
        }
        $permission['pay']      = $this->_pay($order['pay_status'], $order['member']);
        $permission['check']    = $this->_check($order['pay_status'], $apply_did, $order['status']);
        $permission['cancel']   = $this->_cancel($order['ordermode'], $order['status'],[self::ADMIN_ID, $apply_did, $order['member']]);
        $permission['modify']   = $this->_modify( $order['status'],[self::ADMIN_ID, $apply_did, $order['member']]);
        $permission['sms']      = $this->_sms($order['status'],[self::ADMIN_ID, $apply_did, $order['aid'], $order['member']]);

        $this->output[$order['ordernum']]['permissions'] = $permission;
    }
    /**
     * 处理订单数据
     *
     * @param bool|false $excel 如果是导出到EXCEL 那么要多取一些值
     * @return array
     */
    protected function format_order_data( $excel = false )
    {
        foreach ($this->orders as $_ordernum=>$orders) {
            //main
            $this->output[$_ordernum] = [];
            $this->output[$_ordernum]['ordernum'] = $_ordernum;
            //判断订单取消\修改等权限 是否处理过一次 否则初始化权限变量 全部为没有
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
            $this->output[$_ordernum]['dtime']    = $orders['main']['dtime'];
            $this->output[$_ordernum]['ltitle']    = $this->lands[$orders['main']['lid']]['title'];
            $this->output[$_ordernum]['pay_status'] = $orders['main']['pay_status'];
            $this->output[$_ordernum]['ordermode'] = $orders['main']['ordermode'];
            //TODO::退票中状态如何处理？
            //$this->output[$_ordernum]['is_audit'] += ($item['audit_id'] && in_array($item['status'], [0, 7])); //2016-3-29 14:41:40 未使用和部分使用的订单显示为退票中
            //显示完整手机号
            if (!in_array($this->memberId, [ self::ADMIN_ID, $orders['main']['member'] ])) {
                /** @var $file \Library\Cache\CacheFile*/
                $file           = Cache::getInstance('file');
                $order_tel_conf = $file->get('order_tel_display');
                if (is_array($order_tel_conf) && !in_array($this->memberId, $order_tel_conf)) {
                    $orders['main']['ordertel'] = substr_replace($orders['main']['ordertel'], "****", 3, 4);
                }
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