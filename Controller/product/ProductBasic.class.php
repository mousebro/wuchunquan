<?php namespace Controller\product;
defined('PFT_INIT') or exit('Permission Denied');
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/12-012
 * Time: 15:20
 *
 * 保存门票/景区数据公共类
 */
use Library\Controller;
use Model\Member\Member;
use Model\Product\Land;
use Model\Product\Ticket;

class ProductBasic extends Controller
{
    public function SaveTicket($memberId,  $ticketData, Ticket $ticketObj, Land $landObj)
    {
        $isSectionTicket = false;// 是否是期票
        if($ticketData['order_start'] && $ticketData['order_end']) $isSectionTicket = true;

        // 价格判断
        if(isset($ticketData['price_section']) && count($ticketData['price_section']) )
        {
            $compareSec = array();
            $changeNote = array();
            $original_price = $ticketObj->getPriceSection($ticketData['pid']);
            foreach($ticketData['price_section'] as $row)
            {
                // 期票模式（有效期是时间段）只能全部有价格
                if($isSectionTicket && ($row['weekdays']!='0,1,2,3,4,5,6')) return ['code'=>0, 'msg'=>'期票模式必须每天都有价格'];
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
        $jData['title']   = $ticketData['ttitle'];
        $jData['landid']  = $ticketData['lid']+0;
        $jData['tprice']  = $ticketData['tprice']+0;    // 门市价
        $jData['pay']     = $ticketData['pay']+0;       // 支付方式 0 现场 1 在线
        $jData['ddays']   = $ticketData['ddays']+0;     // 提前下单时间
        $jData['getaddr'] = $ticketData['getaddr'];     // 取票信息
        $jData['notes']   = $ticketData['notes'];       // 产品说明
        // $jData['order_limit']   = $oneTicket['order_limit'];    // 验证限制
        $jData['buy_limit_up']  = $ticketData['buy_limit_up']+0; // 购买上限
        $jData['buy_limit_low'] = $ticketData['buy_limit_low']+0;
        $jData['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $ticketData['order_limit'])));

        if(($jData['buy_limit_up']>0) && $jData['buy_limit_low']>$jData['buy_limit_up'])
            return ['code'=>0, 'msg'=>'最少购买张数不能大于最多购买张数'];

        // 延迟验证
        $delaytime = array(0,0);
        if(!isset($ticketData['vtimehour']) && $ticketData['vtimehour']) $delaytime[0] = $ticketData['vtimehour']+0;
        if(!isset($ticketData['vtimeminu']) && $ticketData['vtimeminu']) $delaytime[1] = $ticketData['vtimeminu']+0;
        $jData['delaytime'] = implode('|', $delaytime);


        // 闸机绑定
        $jData['uuid'] = isset($ticketData['uuid']) ? $ticketData['uuid']:'';
        if($jData['uuid'])
        {
            $memberObj = new Member();
            $jiutian_auth = $memberObj->getMemberExtInfo($memberId, 'jiutian_auth');
            if($jiutian_auth) $jData['sourceT'] = 1;
        }
        //TODO::不知道做什么的代码
        /*if(isset($ticketData['tid']) && $ticketData['tid']>0)
        {
            $tid = $ticketData['tid'];
            $sql = "select sourceT from uu_jq_ticket where id=$tid limit 1";
            $GLOBALS['le']->query($sql);
            if($GLOBALS['le']->fetch_assoc()) if($GLOBALS['le']->f('sourceT')==2) $jData['sourceT'] = 2;
        }*/



        if($jData['buy_limit_low']<=0) return array('status'=>'fail', 'msg'=>'购买下限不能小于0');

        $jData['max_order_days']    = isset($ticketData['max_order_days']) ? $ticketData['max_order_days']+0:'-1';// 提前预售天数
        $jData['cancel_auto_onMin'] = abs($ticketData['cancel_auto_onMin']); // 未支付多少分钟内自动取消

        // 取消费用（统一）
        $jData['reb']      = $ticketData['reb']+0;   // 实际值以分为单位
        $jData['reb_type'] = $ticketData['reb_type'];// 取消费用类型 0 百分比 1 实际值
        if($jData['reb_type']==0) {
            if($jData['reb']>100 || $jData['reb']<0) return ['code'=>0, 'msg'=>'取消费用百分比值不合法'];
            $jData['reb'] = $jData['reb'] / 100;
        }

        // 阶梯取消费用设置
        if(isset($ticketData['cancel_cost']) && $ticketData['cancel_cost'])
        {
            $c_days = array();
            foreach($ticketData['cancel_cost'] as $row)
            {
                if(in_array($row['c_days'], $c_days)) return ['code'=>0, 'msg'=>'退票手续费日期重叠'];
                $c_days[] = $row['c_days'];
            }
        }

        $jData['cancel_cost'] = (isset($ticketData['cancel_cost'])) ? json_encode($ticketData['cancel_cost']):'';
        $jData['cancel_cost'] = addslashes($jData['cancel_cost']);
        // exit;

        // 订单有效期 类型 0 游玩时间 1 下单时间 2 区间
        $jData['delaytype'] = $ticketData['validTime']+0;
        $jData['delaydays'] = $ticketData['delaydays']+0;
        $jData['order_end'] = $jData['order_start'] = '';
        if($ticketData['validTime']==2){
            if($ticketData['order_end']=='' || $ticketData['order_start']=='')
                return ['code'=>0, 'msg'=>'有效期时间不能为空'];
            $jData['order_end']   = date('Y-m-d 23:59:59', strtotime($ticketData['order_end']));// 订单截止有效日期
            $jData['order_start'] = date('Y-m-d 00:00:00', strtotime($ticketData['order_start']));
        }

        // 退票规则 0 有效期内、过期可退 1 有效期内可退 2  不可退
        $jData['refund_rule'] = $jData['refund_early_time'] = 0;
        if(!isset($ticketData['refund_rule'])) $jData['refund_rule'] = $ticketData['refund_rule']+0;
        if(!isset($ticketData['refund_early_time'])) $jData['refund_early_time'] = $ticketData['refund_early_time']+0;

        // 过期退票规则
        // $jData['overdue_refund'] = 0;// 不可退
        // if(isset($oneTicket['overdue_refund'])) $jData['overdue_refund'] = $oneTicket['overdue_refund']+0;
        // $jData['overdue_auto_check']  = isset($oneTicket['overdue_auto_check']) ? $oneTicket['overdue_auto_check']+0:0;
        // $jData['overdue_auto_cancel'] = isset($oneTicket['overdue_auto_cancel']) ? $oneTicket['overdue_auto_cancel']+0:0;

        // 退票审核
        $jData['refund_audit'] = (isset($ticketData['refund_audit']) && $ticketData['refund_audit']) ? 1:0;

        $cancel_sms  = 0;// 取消是否通知游客
        $cancel_sms  = isset($ticketData['cancel_sms']) ? $ticketData['cancel_sms']+0:0;
        $confirm_sms = isset($ticketData['confirm_sms']) ? $ticketData['confirm_sms']+0:0;
        $fData['confirm_sms']  = bindec($cancel_sms.$confirm_sms);


        // 取消通知供应商 0 不通知 1 通知
        if(isset($ticketData['cancel_notify_supplier']))
            $jData['cancel_notify_supplier'] = $ticketData['cancel_notify_supplier']+0;


        // 分批验证设置
        $jData['batch_check']       = $ticketData['batch_check']+0;
        $jData['batch_day_check']   = $ticketData['batch_day_check']+0;
        $jData['batch_diff_identities'] = $ticketData['batch_diff_identities']+0;


        // 景点类别属性（二次交互）
        $jData['Mpath'] = '';
        if(isset($ticketData['mpath']) && $ticketData['mpath']!='') {
            $jData['Mpath']     = $ticketData['mpath'];
            $jData['Mdetails']  = 1;
        }

        if(isset($ticketData['re_integral'])) $jData['re_integral'] = $ticketData['re_integral'] + 0;

        $jData['apply_did'] = $memberId;// 产品供应商

        // 验证景区是否存在
        $lid = $ticketData['lid']+0;
        $landInfo = $landObj->getLandInfo($lid,false, 'title,p_type,apply_did');
        if (!$landInfo || ($landInfo['apply_did']!=$memberId && $memberId!=0)) {
            return ['code'=>0, 'msg'=>'景区不存在'];
        }
        $ltitle = $landInfo['title'];
        $p_type = $landInfo['p_type'];


        // 扩展属性 uu_land_f
        $fData['confirm_wx']   = $ticketData['confirm_wx']+0;
        $fData['sendVoucher']  = $ticketData['sendVoucher']+0;
        // $fData['confirm_sms']  = $oneTicket['confirm_sms']+0;
        $fData['tourist_info'] = $ticketData['tourist_info']+0;

        // 提前预定小时  01:00:00 - 23:59:00
        $fData['dhour'] = str_pad($ticketData['dhour'], 5, 0, STR_PAD_LEFT).':00';
        if($p_type=='H') $fData['zone_id'] = $ticketData['zone_id']+0;

        // 验证时间 08:00|18:00
        $fData['v_time_limit'] = 0;
        if(isset($ticketData['v_time_limit']) && $ticketData['v_time_limit'])
        {
            $arr1 = explode('|', $ticketData['v_time_limit']);
            $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
            $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
            $fData['v_time_limit'] = implode('|', $arr1);
        }
        //线路产品属性
        if($p_type=='B')
        {
            $fData['rdays'] = $ticketData['rdays']+0;// 游玩天数
            $fData['series_model'] = '';
            if(isset($ticketData['g_number']) && $ticketData['g_number']) $fData['series_model'] = $ticketData['g_number'].'{fck_date}';
            if(isset($ticketData['s_number']) && $ticketData['s_number'] && $fData['series_model']) $fData['series_model'].= '-'.$ticketData['s_number'];
            $ass_station = $ticketData['ass_station'];
            $ass_station = str_replace('；', ';', $ass_station);
            $fData['ass_station'] = addslashes(serialize(explode(';', $ass_station)));
        }

        if(isset($ticketData['tid']) && $ticketData['tid']>0)
        {   // 以下编辑操作

            $tid = $ticketData['tid']+0;
            $sql = "select * from uu_jq_ticket t left join uu_products p on t.pid=p.id where t.id=$tid limit 1";
            $GLOBALS['le']->query($sql);// 缓存原设置
            if(($original_info = $GLOBALS['le']->fetch_assoc())){
                $original_info['memberID'] = $memberId;
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
                $ticketData['pid']    = $pid;
                $ticketData['action'] = 'CreateNewTicket';
                $ticketData['add_ticket']  = ($tid==0) ? 1:0;
                $ticketData['validHtml_2'] = htmlValid($jData);
                $ticketData['validHtml_1'] = htmlValid($original_info);
                fsockNoWaitPost("http://".IP_INSIDE."/new/d/call/detect_prod.php", $ticketData);
            }
        }
        else
        {
            // 以下新增操作
            $create_ret = $ticketObj->CreateTicket($jData);
            if($create_ret['code']!=200) return $create_ret;
            $tid =$create_ret['data']['lastid'];
            $ret = $ticketObj->QueryTicketInfo("id=$tid",'pid');
            $pid = $ret[0]['pid'];

            $fData['lid'] = $lid;
            $fData['pid'] = $pid;
            $fData['tid'] = $tid;
            $extRet = $ticketObj->CreateTicketExtendInfo($fData);

            if($extRet['code']!=200) return $extRet;
            $daction = '添加门票.'.$ltitle.$jData['title'];
        }
        $apply_limit = $ticketData['apply_limit']+0;
        $ticketObj->UpdateProducts(['id'=>$pid], ['apply_limit'=>$apply_limit, 'p_status'=>0]);



        // 保存或修改价格判断
        // print_r($original_price);
        if(isset($ticketData['price_section']) && count($ticketData['price_section']) && $pid){

            foreach($ticketData['price_section'] as $row)
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