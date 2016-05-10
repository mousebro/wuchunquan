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
    private $model;
    //$show_tel  = include_once 'saleProduct_showTel.php';

    public function __construct()
    {
        $this->model = new \Model\Order\OrderQuery();
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
        print_r($data);
    }

    private function priceHandler($orders, &$output)
    {
        //整合分销链
        if ($orders['main']['aids'] != 0) {
            $aids = $orders['main']['aids'] . ',' . $orders['main']['member'];
        }
        elseif ($orders['main']['aid'] !== $orders['main']['member']) {
            $aids = $orders['main']['aid'] . ',' . $orders['main']['member'];
        }
        else {
            $aids = $orders['main']['aid'];
        }
        //整合分销价格链
        if ($orders['main']['aids_price'] != 0) {
            $aids_price = $orders['main']['aids_price'] . ',' . $orders['main']['tprice'];
        } else {
            $aids_price = $orders['main']['tprice'];
        }
        $aids_price = explode(',', $aids_price);
        $aids = explode(',', $aids);
        $aid_money = unserialize($orders['main']['aids_money']);
        //array_walk($aids_price, 'walk_divide_100');
        //$aids_price = $GLOBALS['tmp_price'];
        //如果是admin 那么就不是取当前用户的买入买出价了(是admin的时候本来就没有'当前用户的概念了')
        //而是把整条分销链都取出来
        if ($_SESSION['sid'] == 1) {

        }
        else {
            if ($item['aprice'] < 0) {
                $item['aprice'] = $item['n_price'];
            }
            if ($item['lprice'] < 0) {
                $item['lprice'] = $item['l_price'];
            }
            $aids_price['lp'] = (int)$item['lprice'] / 100;
            $aids_price['ap'] = (int)$item['aprice'] / 100;
            //不是admin
            //处理价钱 转分销上下级分别是谁
            $key = array_search($self, $aids);
            $row['sell_id'] = $aids[$key + 1] ? $aids[$key + 1] : (string)$item['member'];//卖给谁
            $row['buy_id'] = $aids[$key - 1] ? $aids[$key - 1] : $self;//向谁买
            $_tmp['seller'] = $data['members'][$row['sell_id']];//= $row['sell_id'];
            $_tmp['buyer'] = $data['members'][$row['buy_id']];//  = $row['buy_id'];
            //再次购买的权限
            if ($_SESSION['sid'] == $item['mid']) {
                $row['can_buy_again'] = 'pid=' . $item['pid'] . '&aid=' . $item['aid'];
            }

            $tmp_time = explode('至', $row['begin_to_end']);

            if ($tmp_time[0] === $tmp_time[1]) {
                if (date('Y/m/d') == $tmp_time[0]) {
                    if ($row['lid'] == 10820) {//刘三姐
                        $today_avalid = 1;
                    } else {
                        $today_avalid = 0;
                    }

                } else {
                    $today_avalid = 0;
                }
            } else {
                $today_avalid = 0;
            }
            $row['today_avalid'] = $today_avalid;

            if (count($aids) == 1) {
                $sell_price = $aids_price[$key];
                $buy_price = $aids_price['ap'];
            }
            else {
                $sell_price = $aids_price[$key] ? $aids_price[$key]
                    : $aids_price['lp'];
                $buy_price = $aids_price[$key - 1] ? $aids_price[$key - 1]
                    : $aids_price['ap'];
            }
            $row['buy_price'][] = $buy_price;
            $row['buy_money'] += $buy_price * $item['tnum'];
            $row['sell_price'][] = $sell_price;
            $row['sell_money'] += $sell_price * $item['tnum'];
        }
    }

    /**
     * @param array $item 订单数组
     * @param string $self 当查询人不是admin的时候的 param['self']
     * @param array $row 因为订单要以购买者和出售者嵌套循环 所有如果之前$row里有值了 就再传到这个函数里继续添加值
     * @param bool|false $excel 如果是导出到EXCEL 那么要多取一些值
     * @return array
     */
    public function row($data, $item, $self,  $excel = false)
    {
        $output = [];
        foreach ($data['orders'] as $_ordernum=>$orders) {
            //main
            //判断订单取消\修改等权限 是否处理过一次 否则初始化权限变量 全部为没有
            $_tmp['orderAlipay'] = 3;
            $_tmp['orderCancel'] = 3;
            $_tmp['orderAlter']  = 3;
            $_tmp['orderResend'] = 3;
            $_tmp['orderCheck']  = 3;

            $_tmp['ordernum'][]   = $orders['main']['ordernum'];
            $_tmp['begintime']  = $orders['main']['begintime'];
            $_tmp['endtime']    = $orders['main']['endtime'];
            $_tmp['begin_to_end'] =  $_tmp['begintime'].'-'.$orders['main']['endtime'];

            $_tmp['lid']        = $orders['main']['lid'];
            $_tmp['ordertel']   = $orders['main']['ordertel'];
            $_tmp['ordername']  = $orders['main']['ordername'];
            //下单时间 截取至分
            $_tmp['ordertime'] = str_replace('-', '/', substr( $orders['main']['ordertime'], 0, 19));
            //预计游玩时间
            $_tmp['playtime']  = str_replace('-', '/', $orders['main']['playtime']);
            $_tmp['_dtime']     = $orders['main']['dtime'];
            $_tmp['ltitle']     = $data['lands'][$orders['main']['lid']];
            $_tmp['ttitle'][]   = $data['tickets'][$orders['main']['tid']];
            $_tmp['tid'][]      = $orders['main']['tid'];
            $_tmp['paystatus']  = $orders['main']['paystatus'];
            $_tmp['ordermode']  = $orders['main']['ordermode'];
            $_tmp['tnum'][]     = $orders['main']['tnum'];
            $_tmp['status'][] = OrderDict::DictOrderStatus()[$item['status']];
            $_tmp['is_audit'] += (bool)($item['audit_id'] && in_array($item['status'], [0, 7])); //2016-3-29 14:41:40 未使用和部分使用的订单显示为退票中
            if ( $_SESSION['sid'] != 1 && $_tmp['_dtime'] == '0000-00-00 00:00:00'
                && $_SESSION['sid'] != (int)$orders['main']['member']
                && ! in_array($_SESSION['saccount'], $GLOBALS['show_tel']) )
            {
                $_tmp['ordertel'] = substr_replace($orders['main']['ordermode']['ordertel'], "****", 3, 4);
            }
            $row['ordertel']  = $_tmp['ordertel'] == '0' ? '空' : $_tmp['ordertel'];//游客电话

            //修改订单时需要用到的值
            $_tmp['data_ticket'] = '【' . $data['tickets'][$orders['main']['tid']] . '】|'
                . $orders['main']['tnum'] . '|' . $orders['main']['ordernum'] . '|';

            //links-处理联票订单
            if (isset($orders['links'])) {
                foreach ($orders['links'] as $link) {
                    $_tmp['ordernum'][] = $link['ordernum'];
                    $_tmp['tnum'][]   = $link['tnum'];
                    $_tmp['ttitle'][]  =  $data['tickets'][$link['tid']];
                    $_tmp['data_ticket'] = '【' . $data['tickets'][$link['tid']] . '】|'
                        . $link['tnum'] . '|' . $link['ordernum'] . '|';
                }
            }
            //处理分销价格数据
            $this->priceHandler($orders, $output);


            //price handler end
        }//End foreach


        return $row;
    }
}