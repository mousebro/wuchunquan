<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/5-005
 * Time: 16:16
 */

namespace Controller\product;


use Library\Controller;
use Model\Member\Member;
use Model\Product\Land;
use Model\Product\Round;

class ticket extends Controller
{
    //private $ticketObj = ;
    public function __construct()
    {
        if (!$_SESSION['memberID']) parent::apiReturn(self::CODE_AUTH_ERROR,[],'未登录');
        //$this->ticketObj = parent::model('\Product\Ticket');
        $this->ticketObj = new \Model\Product\Ticket();
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
            if($data['order_end']=='') $data['order_end'] = '';
            if($data['order_start']=='') $data['order_start'] = '';

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
                $child_ticket_data = $pack->childTempTicketsInfo();
            } else {
                $child_ticket_data = $pack->getChildTickets();
            }
            //var_dump($child_ticket_data);exit;
            if(!$pack->checkEffectivePack()) $data['message'] = $pack->message;
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
            $child_info = $pack->getCache();
            $child_info = json_decode($child_info, true);
            foreach($child_ticket_data as $child)
            {
                foreach($child_info as $row)
                {
                    if($child['id']==$row['pid'])
                        $data['childTicket'][] = array(
                            'ltitle' => $child['ltitle'],
                            'ttitle' => $child['ttitle'],
                            'pid' => $child['pid'],
                            'lid' => $child['lid'],
                            'tid' => $child['tid'],
                            'num' => $row['num'],
                        );
                }
            }
            $data['ddays'] = $advance;
            $landData = $data;
        }

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
        }
        parent::apiReturn(200,
            [
                'attribute'     => $data,
                'otherTicket'   => $other_tickets,
                'land'          =>$landData,
            ],
            'success');
    }
    public function Update($oneTicket)
    {

        $isSectionTicket = false;// 是否是期票
        if($oneTicket['order_start'] && $oneTicket['order_end']) $isSectionTicket = true;

        // 价格判断
        if(isset($oneTicket['price_section']) && count($oneTicket['price_section']) ){

            $compareSec = array();
            $changeNote = array();
            $original_price = $this->ticketObj->getPriceSection($oneTicket['pid']);
            foreach($oneTicket['price_section'] as $row)
            {
                // 期票模式（有效期是时间段）只能全部有价格
                if($isSectionTicket && ($row['weekdays']!='0,1,2,3,4,5,6')) return array('status'=>'fail', 'msg'=>'期票模式必须每天都有价格');
                if(($tableId = ($row['id']+0))==0) continue; // 已存在表ID
                $section = $row['sdate'].' 至 '.$row['edate'];
                $diff_js = $original_price[$tableId]['js'] - $row['js'];
                $diff_ls = $original_price[$tableId]['ls'] - $row['ls'];
                if($diff_js) $changeNote[] = $section.' 供货价变动，原:'.($original_price[$tableId]['js']/100).'，现:'.($row['js']/100);
                if($diff_ls) $changeNote[] = $section.' 零售价变动，原:'.($original_price[$tableId]['ls']/100).'，现:'.($row['ls']/100);
            }
        }

        // 整合数据
        $jData = $fData = array();
        $jData['title']   = $oneTicket['ttitle'];
        $jData['landid']  = $oneTicket['lid']+0;
        $jData['tprice']  = $oneTicket['tprice']+0;    // 门市价
        $jData['pay']     = $oneTicket['pay']+0;       // 支付方式 0 现场 1 在线
        $jData['ddays']   = $oneTicket['ddays']+0;     // 提前下单时间
        $jData['getaddr'] = $oneTicket['getaddr'];     // 取票信息
        $jData['notes']   = $oneTicket['notes'];       // 产品说明
        // $jData['order_limit']   = $oneTicket['order_limit'];    // 验证限制
        $jData['buy_limit_up']  = $oneTicket['buy_limit_up']+0; // 购买上限
        $jData['buy_limit_low'] = $oneTicket['buy_limit_low']+0;
        $jData['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $oneTicket['order_limit'])));

        if(($jData['buy_limit_up']>0) && $jData['buy_limit_low']>$jData['buy_limit_up'])
            return array('status'=>'fail', 'msg'=>'最少购买张数不能大于最多购买张数');

        // 延迟验证
        $delaytime = array(0,0);
        if(!isset($oneTicket['vtimehour']) && $oneTicket['vtimehour']) $delaytime[0] = $oneTicket['vtimehour']+0;
        if(!isset($oneTicket['vtimeminu']) && $oneTicket['vtimeminu']) $delaytime[1] = $oneTicket['vtimeminu']+0;
        $jData['delaytime'] = implode('|', $delaytime);


        // 闸机绑定
        $jData['uuid'] = isset($oneTicket['uuid']) ? $oneTicket['uuid']:'';

        if($jData['uuid'])
        {
            $sql = "select jiutian_auth from pft_member_extinfo where fid={$_SESSION['sid']} limit 1";
            $GLOBALS['le']->query($sql);
            $GLOBALS['le']->fetch_assoc();
            if($GLOBALS['le']->f('jiutian_auth')) $jData['sourceT'] = 1;
        }

        if(isset($oneTicket['tid']) && $oneTicket['tid']>0)
        {
            $tid = $oneTicket['tid'];
            $sql = "select sourceT from uu_jq_ticket where id=$tid limit 1";
            $GLOBALS['le']->query($sql);
            if($GLOBALS['le']->fetch_assoc()) if($GLOBALS['le']->f('sourceT')==2) $jData['sourceT'] = 2;
        }



        if($jData['buy_limit_low']<=0) return array('status'=>'fail', 'msg'=>'购买下限不能小于0');

        $jData['max_order_days']    = isset($oneTicket['max_order_days']) ? $oneTicket['max_order_days']+0:'-1';// 提前预售天数
        $jData['cancel_auto_onMin'] = abs($oneTicket['cancel_auto_onMin']); // 未支付多少分钟内自动取消


        // 取消费用（统一）
        $jData['reb']      = $oneTicket['reb']+0;   // 实际值以分为单位
        $jData['reb_type'] = $oneTicket['reb_type'];// 取消费用类型 0 百分比 1 实际值
        if($jData['reb_type']==0) {
            $jData['reb'] = $jData['reb'] / 100;
            if($jData['reb']>100 || $jData['reb']<0) return array('status'=>'fail', 'msg'=>'取消费用百分比值不合法');
        }

        // 阶梯取消费用设置
        if(isset($oneTicket['cancel_cost']) && $oneTicket['cancel_cost'])
        {
            $c_days = array();
            foreach($oneTicket['cancel_cost'] as $row)
            {
                if(in_array($row['c_days'], $c_days))
                    return array('status'=>'fail', 'msg'=>'退票手续费日期重叠');
                $c_days[] = $row['c_days'];
            }
        }

        $jData['cancel_cost'] = (isset($oneTicket['cancel_cost'])) ? json_encode($oneTicket['cancel_cost']):'';
        $jData['cancel_cost'] = addslashes($jData['cancel_cost']);
        // exit;

        // 订单有效期 类型 0 游玩时间 1 下单时间 2 区间
        $jData['delaytype'] = $oneTicket['validTime']+0;
        $jData['delaydays'] = $oneTicket['delaydays']+0;
        $jData['order_end'] = $jData['order_start'] = '';
        if($oneTicket['validTime']==2){
            if($oneTicket['order_end']=='' || $oneTicket['order_start']=='')
                return array('status'=>'fail', 'msg'=>'有效期时间不能为空');
            $jData['order_end']   = date('Y-m-d 23:59:59', strtotime($oneTicket['order_end']));// 订单截止有效日期
            $jData['order_start'] = date('Y-m-d 00:00:00', strtotime($oneTicket['order_start']));
        }

        // 退票规则 0 有效期内、过期可退 1 有效期内可退 2  不可退
        $jData['refund_rule'] = $jData['refund_early_time'] = 0;
        if(!isset($oneTicket['refund_rule'])) $jData['refund_rule'] = $oneTicket['refund_rule']+0;
        if(!isset($oncTicket['refund_early_time'])) $jData['refund_early_time'] = $oncTicket['refund_early_time']+0;

        // 过期退票规则
        // $jData['overdue_refund'] = 0;// 不可退
        // if(isset($oneTicket['overdue_refund'])) $jData['overdue_refund'] = $oneTicket['overdue_refund']+0;
        // $jData['overdue_auto_check']  = isset($oneTicket['overdue_auto_check']) ? $oneTicket['overdue_auto_check']+0:0;
        // $jData['overdue_auto_cancel'] = isset($oneTicket['overdue_auto_cancel']) ? $oneTicket['overdue_auto_cancel']+0:0;

        // 退票审核
        $jData['refund_audit'] = (isset($oneTicket['refund_audit']) && $oneTicket['refund_audit']) ? 1:0;

        $cancel_sms  = 0;// 取消是否通知游客
        $cancel_sms  = isset($oneTicket['cancel_sms']) ? $oneTicket['cancel_sms']+0:0;
        $confirm_sms = isset($oneTicket['confirm_sms']) ? $oneTicket['confirm_sms']+0:0;
        $fData['confirm_sms']  = bindec($cancel_sms.$confirm_sms);


        // 取消通知供应商 0 不通知 1 通知
        if(isset($oncTicket['cancel_notify_supplier'])) $jData['cancel_notify_supplier'] = $oncTicket['cancel_notify_supplier']+0;


        // 分批验证设置
        $jData['batch_check']     = $oneTicket['batch_check']+0;
        $jData['batch_day_check'] = $oneTicket['batch_day_check']+0;
        $jData['batch_diff_identities'] = $oneTicket['batch_diff_identities']+0;


        // 景点类别属性（二次交互）
        $jData['Mpath'] = '';
        if(isset($oneTicket['mpath']))    $jData['Mpath'] = $oneTicket['mpath'];

        $jData['Mdetails'] = ($jData['Mpath']) ? 1:0;

        if(isset($oneTicket['re_integral'])) $jData['re_integral'] = $oneTicket['re_integral'] + 0;

        $jData['apply_did'] = $oneTicket['apply_did'];// 产品供应商

        // 验证景区是否存在
        $lid = $oneTicket['lid']+0;
        $sql = "select title,id,p_type from uu_land where id=$lid limit 1";
        $GLOBALS['le']->query($sql);
        if(!$GLOBALS['le']->fetch_assoc()) return array('status'=>'fail', 'msg'=>'景区不存在');
        $ltitle = $GLOBALS['le']->f('title');
        $p_type = $GLOBALS['le']->f('p_type');



        // 扩展属性 uu_land_f
        $fData['confirm_wx']   = $oneTicket['confirm_wx']+0;
        $fData['sendVoucher']  = $oneTicket['sendVoucher']+0;
        // $fData['confirm_sms']  = $oneTicket['confirm_sms']+0;
        $fData['tourist_info'] = $oneTicket['tourist_info']+0;

        // 提前预定小时  01:00:00 - 23:59:00
        $fData['dhour'] = str_pad($oneTicket['dhour'], 5, 0, STR_PAD_LEFT).':00';
        if($p_type=='H') $fData['zone_id'] = $oneTicket['zone_id']+0;

        // 验证时间 08:00|18:00
        $fData['v_time_limit'] = 0;
        if(isset($oneTicket['v_time_limit']) && $oneTicket['v_time_limit'])
        {
            $arr1 = explode('|', $oneTicket['v_time_limit']);
            $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
            $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
            $fData['v_time_limit'] = implode('|', $arr1);
        }





        if($p_type=='B')
        {
            $fData['rdays'] = $oneTicket['rdays']+0;// 游玩天数
            $fData['series_model'] = '';
            if(isset($oneTicket['g_number']) && $oneTicket['g_number']) $fData['series_model'] = $oneTicket['g_number'].'{fck_date}';
            if(isset($oneTicket['s_number']) && $oneTicket['s_number'] && $fData['series_model']) $fData['series_model'].= '-'.$oneTicket['s_number'];
            $ass_station = $oneTicket['ass_station'];
            $ass_station = str_replace('；', ';', $ass_station);
            $fData['ass_station'] = addslashes(serialize(explode(';', $ass_station)));
        }


        if(isset($oneTicket['tid']) && $oneTicket['tid']>0)
        {   // 以下编辑操作

            $tid = $oneTicket['tid']+0;
            $sql = "select * from uu_jq_ticket t left join uu_products p on t.pid=p.id where t.id=$tid limit 1";
            $GLOBALS['le']->query($sql);// 缓存原设置
            if(($original_info = $GLOBALS['le']->fetch_assoc())){
                $original_info['memberID'] = $_SESSION['memberID'];
                $original_info['REQUESTD'] = $_REQUEST;
                // write_logs(json_encode($original_info), 'before_ticket_'.date('Ymd').'.txt');
            }else return array('status'=>'fail', 'msg'=>'票类不存在');

            $pid = $original_info['pid'];
            $sql = buildUpdateSql($jData, 'uu_jq_ticket', "where id=$tid limit 1"); // echo $sql;
            if(!$GLOBALS['le']->query($sql)) return array('status'=>'fail', 'msg'=>'其他错误,请联系客服');
            $sql = buildUpdateSql($fData, 'uu_land_f', "where tid=$tid limit 1");
            if(!$GLOBALS['le']->query($sql)) return array('status'=>'fail', 'msg'=>'其他错误,请联系客服');

            $sql = "UPDATE uu_products SET verify_time=now() WHERE id=$pid LIMIT 1";
            $GLOBALS['le']->query($sql);
            $daction = "对 $ltitle".$jData['title']." 进行编辑";

            // 产品有效期监控
            if(count($original_info))
            {
                $oneTicket['pid']    = $pid;
                $oneTicket['action'] = 'CreateNewTicket';
                $oneTicket['add_ticket']  = ($tid==0) ? 1:0;
                $oneTicket['validHtml_2'] = htmlValid($jData);
                $oneTicket['validHtml_1'] = htmlValid($original_info);
                fsockNoWaitPost("http://".IP_INSIDE."/new/d/call/detect_prod.php", $oneTicket);
            }
        }else
        {

            // 以下新增操作
            $sql = buildInsertSql($jData, 'uu_jq_ticket');
            if(!$GLOBALS['le']->query($sql)) FinishExit('{"status":"fail", "msg":"操作失败,请重试"}');
            // echo $sql;

            $sql = "select last_insert_id() as lastid";
            $GLOBALS['le']->query($sql); $GLOBALS['le']->fetch_assoc();
            $tid = $GLOBALS['le']->f('lastid');

            $sql = "SELECT pid FROM uu_jq_ticket WHERE id=$tid LIMIT 1";
            $GLOBALS['le']->query($sql);
            $GLOBALS['le']->fetch_assoc();
            $pid = $GLOBALS['le']->f('pid');

            // $tid = 0; $pid = 0;
            $fData['lid'] = $lid;
            $fData['pid'] = $pid;
            $fData['tid'] = $tid;

            $sql = buildInsertSql($fData, 'uu_land_f'); // echo $sql;
            if(!$GLOBALS['le']->query($sql)) return array('status'=>'fail', 'msg'=>'其他错误,请联系客服');
            $daction = '添加门票.'.$ltitle.$jData['title'];
        }

        $apply_limit = $oneTicket['apply_limit']+0;
        $sql ="update uu_products set apply_limit=$apply_limit,p_status=0 where id=$pid limit 1";
        $GLOBALS['le']->query($sql);

        include_once BASE_WWW_DIR.'/class/MemberAccount.class.php';
        if($_SESSION['dtype']==6) pft\Member\MemberAccount::StuffOptLog($_SESSION['memberID'], $_SESSION['sid'], $daction);

        // 保存或修改价格判断
        // print_r($original_price);
        if(isset($oneTicket['price_section']) && count($oneTicket['price_section']) && $pid){

            foreach($oneTicket['price_section'] as $row)
            {

                if(($tableId = ($row['id']+0))>0)
                {

                    $intersect = array();
                    $intersect = array_diff_assoc($row, $original_price[$tableId]);
                    if(count($intersect)==0) continue;
                }
                $action = ($tableId>0) ? 1:0;// 0 插入 1 修改
                $sdate  = date('Y-m-d', strtotime($row['sdate']));
                $edate  = date('Y-m-d', strtotime($row['edate']));
                $apiret = $soap->In_Dynamic_Price_Merge($pid, $sdate, $edate, $row['js'], $row['ls'], 0, $action, $tableId, '', $row['weekdays'], ($row['storage']+0));
                // print_r(array($pid, $sdate, $edate, $row['js'], $row['ls'], 0, $action, $tableId, '', $row['weekdays'], ($row['storage']+0)));
                if($apiret!=100) return array('status'=>'fail', 'msg'=>$apiret);
            }
        }
        return array('status'=>'success','data'=>array('lid'=>$lid, 'tid'=>$tid, 'pid'=>$pid, 'ttitle'=>$jData['title']));
    }
}