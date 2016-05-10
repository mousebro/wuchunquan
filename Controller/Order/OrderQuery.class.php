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

            //links
            if (isset($orders['links'])) {
                foreach ($orders['links'] as $link) {
                    $_tmp['ordernum'][] = $link['ordernum'];
                    $_tmp['tnum'][]   = $link['tnum'];
                    $_tmp['ttitle'][]  =  $data['tickets'][$link['tid']];
                    $_tmp['data_ticket'] = '【' . $data['tickets'][$link['tid']] . '】|'
                        . $link['tnum'] . '|' . $link['ordernum'] . '|';
                }
            }
            //如果是联票 取出主票订单号 然后一起查
            if ($item['concat_id'] != 0 && ! $excel) {
                $param['ordernum']    = $item['concat_id'];
                $console              = $data[$item['concat_id']];
                $row['ordernum'][]    = $item['concat_id'];
                $GLOBALS['lian_mark'] = 1;
                if ($item['p_type'] == 'C') {
                    $row['begintime']    = str_replace('-', '/', (string)reset(reset($console))['begintime']);
                    $row['endtime']      = date('Y/m/d', strtotime((string)end(end($console))['endtime']) + 86400);
                    $row['begin_to_end'] = $row['begintime'] . '至' . $row['endtime'];
                    //酒店类的有效期是联票的最后一张再多加一天
                } else {
                    $row['begintime']    = str_replace('-', '/',(string)reset(reset($console))['begintime']);
                    $row['endtime']      = date('Y/m/d',strtotime((string)reset(reset($console))['endtime']));
                    $row['begin_to_end'] = $row['begintime'] . '至' . $row['endtime'];//不是酒店的取主票
                }
            }
            else {
                $row['ordernum'][] = $item['ordernum'];
                if ($item['p_type'] == 'C')//酒店类的有效期是联票的最后一张再多加一天
                {
                    $row['begintime']    = str_replace('-', '/',$item['begintime']);
                    $row['endtime']      = date('Y/m/d', strtotime($item['endtime']) + 86400);
                    $row['begin_to_end'] = $row['begintime'] . '至' . $row['endtime'];//有效期
                } else {
                    $row['begintime']    = str_replace('-', '/', $item['begintime']);
                    $row['endtime']      = date('Y/m/d', strtotime($item['endtime']));
                    $row['begin_to_end'] = $row['begintime'] . '至'  . $row['endtime'];//有效期
                }
            }
        }


        foreach ($console as $item) {
            if ((int)$item['ordernum'] == (int)$item['concat_id']|| (int)$item['concat_id'] == 0|| $excel)
            {
                $row['ltitle'] = (string)$item['ltitle'];//景区标题
                $row['lid']    = (int)$item['lid'];//景区ID
                if ((string)$item['p_type'] == 'F'  && (int)$item['status'] == 2 )
                {
                    //如果套票的主票过期了 要去找子票看看是不是还有没过期的
                    $param              = [];
                    $param['etime1']    = $param['btime1'] = $item['ordertime'];
                    $param['sort']      = $param['orderby'] = '';
                    $param['pack']      = 2;
                    $param['c']         = 0;
                    $param['offset']    = 0;
                    $param['page_size'] = 15;
                    $param['ordernum'] = $item['ordernum'];
                    $pack              = order_search($param);
                    foreach ($pack as $pa) {
                        $row['status'][] = (int)$pa['Ustatus'] == 2
                            ? $GLOBALS['status'][2] : $GLOBALS['status'][0];
                    }
                }
                $row['ordername'] = (string)$item['ordername'];//游客姓名
                $row['_dtime']    = (string)$item['dtime'];//原始完成时间 截取至分


                $row['ordertime'] = str_replace('-', '/', substr($item['ordertime'], 0, 19));//下单时间 截取至分
                $row['playtime']  = str_replace('-', '/', $item['playtime']);//预计游玩时间

                $row['ordermode'] = $GLOBALS['ordermode'][$item['ordermode']];
                $row['dtime']     = substr($item['dtime'], 0, 4) == '0000' ? '' : str_replace('-', '/', substr($item['dtime'], 0, 19));//使用(游玩)时间
                $row['ctime']     = substr($item['ctime'], 0, 4) == '0000' ? '' : str_replace('-', '/', substr($item['ctime'], 0, 19));//使用(游玩)时间
                $row['paystatus'] = $GLOBALS['paystatus'][$item['paystatus']];//支付状态
                $row['ordermode'] = $GLOBALS['ordermode'][$item['ordermode']];

                if ($excel) {
                    $row['code']      = $item['code'];//验证码
                    $row['memo']      = $item['memo'] ? $item['memo']  : '';//备注
                    $row['remotenum'] = $item['remotenum'];//远端订单号
                    $row['p_type']    = $GLOBALS['p_type'][$item['p_type']];//产品类型
                    if ((string)$item['personid'] != '0') {
                        $tourists = array();
                        $sql = "select idcard,tourist from uu_order_tourist_info where orderid='{$item['ordernum']}' LIMIT 1";
                        $GLOBALS['le']->query($sql);
                        while ($ro = $GLOBALS['le']->fetch_assoc()) {
                            $tourists[$ro['idcard']] = $ro['tourist'];
                        }
                    }
                    if (count($tourists)) {
                        foreach ($tourists as $idcard => $tourist) {
                            $row['personid'] .= $idcard . ':' . $tourist . '||';
                        }
                        $row['personid'] = rtrim($row['personid'], '||');
                    } else {
                        $row['personid'] = $item['personid'] != 0 ? $item['personid'] . ':' . $row['ordername'] : '';
                    }
                    $UUseries          = unserialize($item['series']);
                    $row['UUseries_1'] = $row['UUseries_2'] = '';
                    if (is_array($UUseries)) {
                        $row['UUseries_1'] = explode('，', $UUseries[6]);
                        $row['UUseries_1'] = explode('：', $row['UUseries_1'][1]);
                        $row['UUseries_1'] = $row['UUseries_1'][1];
                        $row['UUseries_2'] = explode('：', $UUseries[6]);
                        $row['UUseries_2'] = $row['UUseries_2'][3];
                        $row['UUseries_3'] = $UUseries[4];
                    }
                }
            }

            if ((int)$item['concat_id'] != $item['ordernum'] && $item['concat_id'] != 0 )
            {
                $row['ordernum'][] = $item['ordernum'];
            }


            $row['data_ticket'] .= '【' . $item['ttitle'] . '】|'  . $item['tnum'] . '|' . $item['ordernum'] . '|';//修改订单时需要用到的值
            $row['tid'][]    = (int)$item['tid'];
            $row['ttitle'][] = (string)$item['ttitle'];
            $row['tnum'][]   = (int)$item['tnum'];
            $row['status'][] = $GLOBALS['status'][$item['status']];
            $row['is_audit'] += (bool)($item['audit_id'] && in_array($item['status'], [0, 7])); //2016-3-29 14:41:40 未使用和部分使用的订单显示为退票中
            if ( in_array($item['status'],[0,7])) {
                $sql = "select tnum, left_num,action from pft_order_track where ordernum='{$item['ordernum']}' order by id desc";
                $GLOBALS['le']->query($sql);
                $TerminalNum = 0;
                $left_num = null;
                //订单追踪表中有记录
                while($data = $GLOBALS['le']->fetch_assoc()){
                    if($left_num === null){
                        $left_num = $data['left_num']; //取最后一次操作的余票数
                    }
                    if($data['action']==5){
                        $TerminalNum += $data['tnum'];
                    }
                }
                $row['left_num'] = $left_num;
                if($TerminalNum){
                    $hasTerminalNum = '<span class="hasTerminal"><span class="text">已验证</span><em class="hasTerminalNum">';
                    $hasTerminalNum .= $TerminalNum;
                    $hasTerminalNum .= '</em><span class="zhang">张</span></span>';
                    $row['has_terminal_num'][] = $hasTerminalNum;
                }else{
                    $row['has_terminal_num'][] = $GLOBALS['status'][0];
                }
                //订单追踪表中没有记录
                if($row['left_num']  === null){
                    $row['left_num'] = (int)$item['tnum'];
                    $row['has_terminal_num'][] = $GLOBALS['status'][0];
                }
            } else {
                $TerminalNum  = (int)$item['tnum'];//已经被验证了几张
                $row['has_terminal_num'][] = $GLOBALS['status'][$item['status']];
                $row['left_num'] = 0;
            }

            if ($excel) {
                $row['TerminalNum'][] = $TerminalNum;
            }
            $row['data_ticket'] .= $TerminalNum . '&';
            //整合分销链
            if ($item['aids'] != 0) {
                $UUaids = $item['aids'] . ',' .$item['mid'];
            } else if ($item['aid'] !== $item['mid']) {
                $UUaids = $item['aid'] . ',' . $item['mid'];
            } else {
                $UUaids = $item['aid'];
            }
            //整合分销价格链
            if ($item['aids_price'] != 0) {
                $UUaids_price = $item['aids_price'] . ',' .$item['tprice'];
            } else {
                $UUaids_price = $item['tprice'];
            }
            $UUaids_price         = explode(',', $UUaids_price);
            $UUaids               = explode(',', $UUaids);
            $aid_money            = unserialize($item['aids_money']);
            $GLOBALS['tmp_price'] = array();
            array_walk($UUaids_price, 'walk_divide_100');
            $UUaids_price = $GLOBALS['tmp_price'];
            //如果是admin 那么就不是取当前用户的买入买出价了(是admin的时候本来就没有'当前用户的概念了')
            //而是把整条分销链都取出来
            if ($_SESSION['sid'] == 1) {
                if ( ! $row['admin']['aids']) {
                    $row['admin']['aids'] = $UUaids;
                    foreach ($UUaids as $itemal) {
                        $GLOBALS['dname'][$itemal] = $itemal;
                    }
                    $count_aids = count($UUaids);
                    if ($count_aids > 2) {
                        foreach ($aid_money as $k => $itemal) {
                            if ($k >= $count_aids - 2) {
                                break;
                            }
                            $row['admin']['pmode'][] = $itemal[1] == 1 ? $GLOBALS['pmode'][0] : $GLOBALS['pmode'][2];
                        }
                    }

                    $row['admin']['pmode'][] = $GLOBALS['pmode'][(int)$item['pmode']];
                }
                $row['admin']['price'][] = $UUaids_price;
                $row                     = format_0($item, $row);
            } else {
                if ($item['aprice'] < 0) {
                    $item['aprice'] = $item['n_price'];
                }
                if ($item['lprice'] < 0) {
                    $item['lprice'] = $item['l_price'];
                }

                $UUaids_price['lp'] = (int)$item['lprice'] / 100;
                $UUaids_price['ap'] = (int)$item['aprice'] / 100;
                //不是admin
                //处理价钱 转分销上下级分别是谁
                $key                               = array_search($self, $UUaids);
                $row['sell_id']                    = $UUaids[$key + 1]
                    ? $UUaids[$key + 1] : (string)$item['mid'];//卖给谁
                $row['buy_id']                     = $UUaids[$key - 1]
                    ? $UUaids[$key - 1] : $self;//向谁买
                $GLOBALS['dname'][$row['sell_id']] = $row['sell_id'];
                $GLOBALS['dname'][$row['buy_id']]  = $row['buy_id'];
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

                if (count($UUaids) == 1) {
                    $sell_price = $UUaids_price[$key];
                    $buy_price  = $UUaids_price['ap'];
                } else {
                    $sell_price = $UUaids_price[$key] ? $UUaids_price[$key]
                        : $UUaids_price['lp'];
                    $buy_price  = $UUaids_price[$key - 1] ? $UUaids_price[$key - 1]
                        : $UUaids_price['ap'];
                }
                $row['buy_price'][] = $buy_price;
                $row['buy_money'] += $buy_price * $item['tnum'];
                $row['sell_price'][] = $sell_price;
                $row['sell_money'] += $sell_price *  $item['tnum'];
                if ((int)$item['aid'] == $self) {//是最后级供应商 直接取pmode
                    $row['sell_pmode'] = $GLOBALS['pmode'][$item['pmode']];
                } elseif ($UUaids[0] == $self) {//最初级供应商
                    $row['sell_pmode'] = $aid_money[0][1] == 1 ?
                        $GLOBALS['pmode'][0] : $GLOBALS['pmode'][2];
                } else {
                    if (is_array($aid_money)) {
                        foreach ($aid_money as $k => $itemal) {
                            if ($itemal[0] == $self) {
                                $row['sell_pmode'] = $aid_money[$k + 1][1] == 1 ? $GLOBALS['pmode'][0] : $GLOBALS['pmode'][2];
                                break;
                            }
                        }
                    }
                }
                if ((int)$item['mid'] == $self) {//是最后的购买者 直接取pmode
                    $row['buy_pmode'] = $GLOBALS['pmode'][(int)$item['pmode']];
                } else {
                    if(is_array($aid_money)){
                        foreach ($aid_money as $itemal) {
                            if ($itemal[0] == $self) {
                                $row['buy_pmode'] = $itemal[1] == 1 ?
                                    $GLOBALS['pmode'][0] : $GLOBALS['pmode'][2];
                                break;
                            }
                        }
                    }
                }
                $row['sell_pmode'] = $row['sell_pmode'] ? $row['sell_pmode'] : '';
                $row['buy_pmode']  = $row['buy_pmode'] ? $row['buy_pmode'] : '';
                $row               = format_0($item, $row);
            }
        }
        if ($row['orderAlter'] == 1) {
            $row['data_ticket'] = rtrim($row['data_ticket'], '&');
        }
        if ($row['is_audit']) {
            $row['orderCancel'] = 2;
            $row['orderAlter']  = 2;
        }
        return $row;
    }
}