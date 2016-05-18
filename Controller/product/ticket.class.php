<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/5-005
 * Time: 16:16
 */

namespace Controller\product;

use Library\PftProduct\TicketLib;
use Model\Member\Member;
use Model\Product\Land;
use Model\Product\PackTicket;
use Model\Product\Round;

class ticket extends ProductBasic
{
    private $memberID;
    //private $ticketObj = ;
    public function __construct()
    {
        if (!$_SESSION['memberID']) parent::apiReturn(self::CODE_AUTH_ERROR,[],'未登录');
        //$this->ticketObj = parent::model('\Product\Ticket');
        $this->ticketObj = new \Model\Product\Ticket();
        $this->memberID = $_SESSION['sid'];
    }

    public function ticket_attribute()
    {
        $lid = I('post.lid', 0, 'intval');
        $tid = I('post.tid', 0, 'intval');
        if($lid==0 && $tid==0) parent::apiReturn(self::CODE_INVALID_REQUEST, [], '参数错误');
        $data = $landData = array();
        if($tid>0)
        {
            $fileds = ' t.tprice,t.reb,t.reb_type,t.rebp,t.landid as lid,t.title as ttitle,'
                .'t.id as tid,t.delaydays,t.delaytype,t.pay,t.notes,t.ddays,t.getaddr,'
                .'t.buy_limit_up,t.buy_limit_low,t.cancel_auto_onMin,t.re_integral,t.cancel_cost,t.max_order_days,'
                .'t.order_limit,f.sendVoucher,f.confirm_sms,f.confirm_wx,f.dhour,f.startplace,f.endplace,f.v_time_limit,f.tourist_info,'
                .'f.ass_station,f.series_model,f.rdays,p.id as pid,p.apply_did,t.mpath,f.zone_id,t.order_end,t.order_start,t.uuid,'
                .'t.overdue_auto_check,t.overdue_auto_cancel,t.batch_check,t.batch_day_check,t.batch_diff_identities,p.apply_limit,'
                .'t.refund_audit,t.refund_rule,t.refund_early_time,t.delaytime,t.cancel_notify_supplier ';
            $join = 'left join uu_products p on t.pid=p.id left join uu_land_f f on t.id=f.tid';
            $data = $this->ticketObj->QueryTicketInfo(['t.id'=>$tid], $fileds, $join);

            if(!$data) parent::apiReturn(self::CODE_INVALID_REQUEST, [],'产品不存在');
            $data = array_shift($data);
            if($_SESSION['sid']!=1 && $data['apply_did']!=$_SESSION['sid']) {
                parent::apiReturn(self::CODE_INVALID_REQUEST, [],"非自身供应产品，无权限查看");
            }
            $lid = $data['lid'];// 景区ID

            // 数据二次处理
            if($data['reb_type']==0) $data['reb'] = $data['reb'] * 100;
            $data['cancel_cost'] = json_decode($data['cancel_cost'], true);

            // 0 都不发  1 预定通知供应商 不通知取消  2 通知取消 预定不通知 3 都通知
            $data['confirm_sms'] = decbin($data['confirm_sms']);// 可能的值是 0 1 2 3 先转成2进制
            $data['confirm_sms'] = strlen($data['confirm_sms'])==2 ? $data['confirm_sms']:'0'.$data['confirm_sms'];//不足两位补0
            $data['cancel_sms']  = $data['confirm_sms'][0];// 取消订单通知游客取前一位
            $data['confirm_sms'] = $data['confirm_sms'][1];// 通知供应商取后一位
            $data['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $data['order_limit'])));

            // 延迟验证
            $delaytime = explode('|', $data['delaytime']);
            $data['vtimehour'] = (isset($delaytime[0]) && $delaytime[0]) ? $delaytime[0]:0;
            $data['vtimeminu'] = (isset($delaytime[1]) && $delaytime[1]) ? $delaytime[1]:0;


            // json 转义成 null 做的处理
            //$_t = strtotime('2015-01-01 00:00:00');
            if(strtotime($data['order_end']) < 1420041600) $data['order_end'] = '';
            if(strtotime($data['order_start']) < 1420041600) $data['order_start'] = '';

            // 获取价格时间段
            $today  = date('Y-m-d');
            //$inSide = soapInstance();
            $data['price_section'] = $this->ticketObj->getPriceSection($data['pid']);

            // 验证时间补 0 操作 1:00|2:00 => 01:00|02:00
            if($data['v_time_limit']!=0)
            {
                $arr1 = explode('|', $data['v_time_limit']);
                $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
                $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
                $data['v_time_limit'] = implode('|', $arr1);
            }
            // 延迟验证
            $delaytime = explode('|', $data['delaytime']);
            $data['vtimehour'] = (isset($delaytime[0]) && $delaytime[0]) ? $delaytime[0]:0;
            $data['vtimeminu'] = (isset($delaytime[1]) && $delaytime[1]) ? $delaytime[1]:0;
        }// $tid>0  End

        if(!isset($lid) || $lid==0) {
            parent::apiReturn(self::CODE_INVALID_REQUEST, [],"景区不存在");
        }
        $landObj = new Land();
        $landData = $landObj->getLandInfo($lid, false, 'title,status,p_type,apply_did,runtime,fax,venus_id');
        $data['fax']    = $landData['fax'];
        $data['ltitle'] = $landData['title'];
        $data['p_type'] = $landData['p_type'];

        if($_SESSION['sid']!=1 && $landData['apply_did']!=$_SESSION['sid']) {
            parent::apiReturn(self::CODE_INVALID_REQUEST, [],"非自身供应产品，无权限查看");
        }
        // 线路属性
        if($landData['p_type']=='B' && $tid)
        {
            if($data['ass_station']) $data['ass_station'] = @unserialize($data['ass_station']);
            if($data['ass_station']) $data['ass_station'] = implode(';', $data['ass_station']);// 集合地点
            $array1 = explode('{fck_date}', $data['series_model']);
            $data['g_number'] = isset($array1[0]) ? $array1[0]:'';// 团号
            $data['s_number'] = isset($array1[1]) ? $array1[1]:'';// 编号

            if($data['s_number']) $data['s_number'] = substr($data['s_number'], 1);
        }

        // 演出属性
        if($landData['p_type']=='H')
        {
            $roundObj = new Round();
            $data['venus_areas'] = $roundObj->GetRoundZoneInfo($landData['venus_id']);
            $data['mpath']       = 'http://'.IP_INSIDE.'/new/d/api/Product_check_h.php';
            $landData['mpath']       = $data['mpath'];
            $landData['venus_areas'] = $data['venus_areas'];
        }
        // 酒店属性
        if($landData['p_type']=='C')
        {

        }
        $memberObj = new Member();

        // 套票属性
        if($landData['p_type']=='F')
        {
            $pack = new \Model\Product\PackTicket($tid);

            if (!$tid) {
                $data['childTicket'] = $pack->childTempTicketsInfo();
            }
            else {
                $child_ticket_data = $pack->getChildTickets();
                //print_r($child_ticket_data);
                foreach($child_ticket_data as $child) {
                    $data['childTicket'][] = array(
                        'ltitle' => $child['ltitle'],
                        'ttitle' => $child['ttitle'],
                        'pid'   => $child['pid'],
                        'lid'   => $child['lid'],
                        'tid'   => $child['tid'],
                        'num'   => $child['num'],
                    );
                }
            }
            //print_r($child_ticket_data);exit;
            if(!$pack->checkEffectivePack()) {
                //$data['message'] = $pack->message;
                parent::apiReturn(self::CODE_INVALID_REQUEST,[], implode(',', $pack->message));
            }
            $advance = $pack->advance;// 提前天数
            $paymode = $pack->paymode;// 支付方式
            $useDate = $pack->usedate;// 套票使用时间
            if($useDate['section']==0) $data['minActive'] = floor((strtotime($useDate['eDate']) - strtotime($useDate['sDate'])) / 86400);
            //exit;
            $group_id = $memberObj->getMemberCacheById($landData['apply_did'], 'group_id');
            // 只有云顶允许打包现场支付
            if($paymode==0 && ($group_id!=4)) {
                parent::apiReturn(self::CODE_INVALID_REQUEST,[], '现场支付不支持打包');
            }
            $data['ddays'] = $advance;
        }
        $landData = $data;
        // 闸机绑定
        $apply_did = $_SESSION['sid'];
        $jiutian_auth = $memberObj->getMemberExtInfo($apply_did, 'jiutian_auth');
        $landData['needBindGate'] = $data['needBindGate'] = false;
        // 绑定闸机票类数据
        if($jiutian_auth == 1 && $tid)
        {
            $md5 = ($data['uuid']!='') ? $data['uuid']:md5($data['ttitle'].'-'.$data['tid']);
            $data['jiutian'][]  = array('uuid'=>$md5,'name'=>$data['ttitle']);
            $data['needBindGate'] = true;
        }

        // 鼓浪屿绑定闸机票
        if($apply_did == 7517)
        {
            $jiuTians = file_get_contents('http://117.29.178.154:8000/et/ebusiness/ticketInfo.do');
            $jiuTians = json_decode($jiuTians, true);
            $data['jiutian'] = $jiuTians['content'];
            $data['needBindGate'] = true;
        }

        if(isset($data['jiutian']))
            $landData['jiutian'] = $data['jiutian'];
        $landData['needBindGate'] = $data['needBindGate'];
        $other_tickets = [];
        // 获取该景区底下其他门票，套票除外
        if ($landData['p_type']!='F') {
            $other_ticket_ret = $this->ticketObj->GetLandTickets($lid);
            if ($other_ticket_ret['code']==200) $other_tickets = $other_ticket_ret['data'];
        } elseif ($tid>0) {
            $other_tickets[] = [
                "title"=>$data['ttitle'],
                'tid'=>$tid,
            ];
        }
        parent::apiReturn(200,
            [
                //'attribute'     => $data,
                'attribute'     => $landData,
                'otherTicket'   => $other_tickets,
            ],
            'success');
    }
    public function UpdateTicket()
    {
        //echo 'hi';
        //print_r($_POST);
        //$ticketData  = $_POST;
        //print_r($_POST);exit();
        $res = array();
        $landModel   = new Land();
        if (count($_POST)>1) {
            foreach ($_POST as $tid=>$ticketData) {
                $ret =  $this->SaveTicket($this->memberID, $ticketData, $this->ticketObj, $landModel);
                $ret['data']['price'] = $this->SavePrice($ret['pid'], $ticketData['price_section']);
                $res[] = $ret;
            }
        }
        else {
            $ticketData = array_shift($_POST);
            //print_r($ticketData);exit;
            $ret = $this->SaveTicket($this->memberID, $ticketData, $this->ticketObj, $landModel);
            if (count($ticketData['price_section'])) {
                $ret['data']['price'] = $this->SavePrice($ret['pid'], $ticketData['price_section']);
            }
            $res[] = $ret;
        }
        self::apiReturn(self::CODE_SUCCESS, $res, 'ok');
    }


}