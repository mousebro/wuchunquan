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
use Model\Product\PackTicket;
use Model\Product\PriceWrite;
use Model\Product\Ticket;

class ProductBasic extends Controller
{
    private $packObj=null;
    private function _return($code, $msg, $title)
    {
        return ['code'=>$code, 'data'=>['title'=>$title,'msg'=>$msg]];
    }
    public function SaveTicket($memberId,  $ticketData, Ticket $ticketObj, Land $landObj)
    {
        $isSectionTicket = false;// 是否是期票
        if($ticketData['order_start'] && $ticketData['order_end']) $isSectionTicket = true;
        //价格校验
        if (!empty($ticketData['price_section'])) {
            $this->VerifyPrice($ticketData['pid'], $ticketData['price_section'], $ticketObj, $isSectionTicket);
        }
        // 整合数据
        $tkBaseAttr = array();
        $tkExtAttr = array();
        $tkBaseAttr['title']   = $ticketData['ttitle'];
        $tkBaseAttr['landid']  = $ticketData['lid']+0;
        $tkBaseAttr['tprice']  = $ticketData['tprice']+0;    // 门市价
        $tkBaseAttr['pay']     = $ticketData['pay']+0;       // 支付方式 0 现场 1 在线
        $tkBaseAttr['ddays']   = $ticketData['ddays']+0;     // 提前下单时间
        $tkBaseAttr['getaddr'] = $ticketData['getaddr'];     // 取票信息
        $tkBaseAttr['notes']   = $ticketData['notes'];       // 产品说明
        // $jData['order_limit']   = $oneTicket['order_limit'];    // 验证限制
        $tkBaseAttr['buy_limit_up']  = $ticketData['buy_limit_up']+0; // 购买上限
        $tkBaseAttr['buy_limit_low'] = $ticketData['buy_limit_low']+0;
        $tkBaseAttr['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $ticketData['order_limit'])));

        if(($tkBaseAttr['buy_limit_up']>0) && $tkBaseAttr['buy_limit_low']>$tkBaseAttr['buy_limit_up'])
           self::_return(self::CODE_INVALID_REQUEST, '最少购买张数不能大于最多购买张数', $ticketData['ttitle']);

        // 延迟验证
        $delaytime = array(0,0);
        if(!isset($ticketData['vtimehour']) && $ticketData['vtimehour']) $delaytime[0] = $ticketData['vtimehour']+0;
        if(!isset($ticketData['vtimeminu']) && $ticketData['vtimeminu']) $delaytime[1] = $ticketData['vtimeminu']+0;
        $tkBaseAttr['delaytime'] = implode('|', $delaytime);


        // 闸机绑定
        $tkBaseAttr['uuid'] = isset($ticketData['uuid']) ? $ticketData['uuid']:'';
        if($tkBaseAttr['uuid'])
        {
            $memberObj = new Member();
            $jiutian_auth = $memberObj->getMemberExtInfo($memberId, 'jiutian_auth');
            if($jiutian_auth) $tkBaseAttr['sourceT'] = 1;
        }
        //TODO::不知道做什么的代码
        /*if(isset($ticketData['tid']) && $ticketData['tid']>0)
        {
            $tid = $ticketData['tid'];
            $sql = "select sourceT from uu_jq_ticket where id=$tid limit 1";
            $GLOBALS['le']->query($sql);
            if($GLOBALS['le']->fetch_assoc()) if($GLOBALS['le']->f('sourceT')==2) $jData['sourceT'] = 2;
        }*/



        if($tkBaseAttr['buy_limit_low']<=0) self::_return(self::CODE_INVALID_REQUEST,  '购买下限不能小于0',$ticketData['ttitle']);

        $tkBaseAttr['max_order_days']    = isset($ticketData['max_order_days']) ? $ticketData['max_order_days']+0:'-1';// 提前预售天数
        $tkBaseAttr['cancel_auto_onMin'] = abs($ticketData['cancel_auto_onMin']); // 未支付多少分钟内自动取消

        // 取消费用（统一）
        $tkBaseAttr['reb']      = $ticketData['reb']+0;   // 实际值以分为单位
        $tkBaseAttr['reb_type'] = $ticketData['reb_type'];// 取消费用类型 0 百分比 1 实际值
        if($tkBaseAttr['reb_type']==0) {
            if($tkBaseAttr['reb']>100 || $tkBaseAttr['reb']<0) self::_return(self::CODE_INVALID_REQUEST,  '取消费用百分比值不合法',$ticketData['ttitle']);
            $tkBaseAttr['reb'] = $tkBaseAttr['reb'] / 100;
        }

        // 阶梯取消费用设置
        if(isset($ticketData['cancel_cost']) && $ticketData['cancel_cost'])
        {
            $c_days = array();
            foreach($ticketData['cancel_cost'] as $row)
            {
                if(in_array($row['c_days'], $c_days))
                    self::_return(self::CODE_INVALID_REQUEST,  '退票手续费日期重叠',$ticketData['ttitle']);
                $c_days[] = $row['c_days'];
            }
        }

        $tkBaseAttr['cancel_cost'] = (isset($ticketData['cancel_cost'])) ? json_encode($ticketData['cancel_cost']):'';
        $tkBaseAttr['cancel_cost'] = addslashes($tkBaseAttr['cancel_cost']);
        // exit;

        // 订单有效期 类型 0 游玩时间 1 下单时间 2 区间
        $tkBaseAttr['delaytype'] = $ticketData['validTime']+0;
        $tkBaseAttr['delaydays'] = $ticketData['delaydays']+0;
        $tkBaseAttr['order_end'] = $tkBaseAttr['order_start'] = '';
        if($ticketData['validTime']==2){
            if($ticketData['order_end']=='' || $ticketData['order_start']=='')
                self::_return(self::CODE_INVALID_REQUEST,  '有效期时间不能为空',$ticketData['ttitle']);
            $tkBaseAttr['order_end']   = date('Y-m-d 23:59:59', strtotime($ticketData['order_end']));// 订单截止有效日期
            $tkBaseAttr['order_start'] = date('Y-m-d 00:00:00', strtotime($ticketData['order_start']));
        }

        // 退票规则 0 有效期内、过期可退 1 有效期内可退 2  不可退
        $tkBaseAttr['refund_rule'] = $tkBaseAttr['refund_early_time'] = 0;
        if(!isset($ticketData['refund_rule'])) $tkBaseAttr['refund_rule'] = $ticketData['refund_rule']+0;
        if(!isset($ticketData['refund_early_time'])) $tkBaseAttr['refund_early_time'] = $ticketData['refund_early_time']+0;

        // 过期退票规则
        // $jData['overdue_refund'] = 0;// 不可退
        // if(isset($oneTicket['overdue_refund'])) $jData['overdue_refund'] = $oneTicket['overdue_refund']+0;
        // $jData['overdue_auto_check']  = isset($oneTicket['overdue_auto_check']) ? $oneTicket['overdue_auto_check']+0:0;
        // $jData['overdue_auto_cancel'] = isset($oneTicket['overdue_auto_cancel']) ? $oneTicket['overdue_auto_cancel']+0:0;

        // 退票审核
        $tkBaseAttr['refund_audit'] = (isset($ticketData['refund_audit']) && $ticketData['refund_audit']) ? 1:0;

        $cancel_sms  = 0;// 取消是否通知游客
        $cancel_sms  = isset($ticketData['cancel_sms']) ? $ticketData['cancel_sms']+0:0;
        $confirm_sms = isset($ticketData['confirm_sms']) ? $ticketData['confirm_sms']+0:0;
        $tkExtAttr['confirm_sms']  = bindec($cancel_sms.$confirm_sms);

        // 取消通知供应商 0 不通知 1 通知
        if(isset($ticketData['cancel_notify_supplier']))
            $tkBaseAttr['cancel_notify_supplier'] = $ticketData['cancel_notify_supplier']+0;


        // 分批验证设置
        $tkBaseAttr['batch_check']       = $ticketData['batch_check']+0;
        $tkBaseAttr['batch_day_check']   = $ticketData['batch_day_check']+0;
        $tkBaseAttr['batch_diff_identities'] = $ticketData['batch_diff_identities']+0;


        // 景点类别属性（二次交互）
        $tkBaseAttr['Mpath'] = '';
        if(isset($ticketData['mpath']) && $ticketData['mpath']!='') {
            $tkBaseAttr['Mpath']     = $ticketData['mpath'];
            $tkBaseAttr['Mdetails']  = 1;
        }

        if(isset($ticketData['re_integral'])) $tkBaseAttr['re_integral'] = $ticketData['re_integral'] + 0;

        $tkBaseAttr['apply_did'] = $memberId;// 产品供应商

        // 验证景区是否存在
        $lid = $ticketData['lid']+0;
        $landInfo = $landObj->getLandInfo($lid,false, 'title,p_type,apply_did');
        if (!$landInfo || ($landInfo['apply_did']!=$memberId && $memberId!=0)) {
            self::_return(self::CODE_NO_CONTENT,  '景区不存在',$ticketData['ttitle']);
        }
        $ltitle = $landInfo['title'];
        $p_type = $landInfo['p_type'];

        if ($ticketData['fax']) {
            $landObj->UpdateAttrbites(['id'=>$lid], ['fax'=>$ticketData['fax']]);
        }

        // 扩展属性 uu_land_f
        $tkExtAttr['confirm_wx']   = $ticketData['confirm_wx']+0;
        $tkExtAttr['sendVoucher']  = $ticketData['sendVoucher']+0;
        // $fData['confirm_sms']  = $oneTicket['confirm_sms']+0;
        $tkExtAttr['tourist_info'] = $ticketData['tourist_info']+0;

        // 提前预定小时  01:00:00 - 23:59:00
        $tkExtAttr['dhour'] = str_pad($ticketData['dhour'], 5, 0, STR_PAD_LEFT).':00';
        if($p_type=='H') $tkExtAttr['zone_id'] = $ticketData['zone_id']+0;

        // 验证时间 08:00|18:00
        $tkExtAttr['v_time_limit'] = '00:00|23:59';
        if(isset($ticketData['v_time_limit']) && $ticketData['v_time_limit'])
        {
            $arr1 = explode('|', $ticketData['v_time_limit']);
            $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
            $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
            $tkExtAttr['v_time_limit'] = implode('|', $arr1);
        }
        //线路产品属性
        if($p_type=='B')
        {
            $tkExtAttr['rdays'] = $ticketData['rdays']+0;// 游玩天数
            $tkExtAttr['series_model'] = '';
            if(isset($ticketData['g_number']) && $ticketData['g_number']) $tkExtAttr['series_model'] = $ticketData['g_number'].'{fck_date}';
            if(isset($ticketData['s_number']) && $ticketData['s_number'] && $tkExtAttr['series_model']) $tkExtAttr['series_model'].= '-'.$ticketData['s_number'];
            $ass_station = $ticketData['ass_station'];
            $ass_station = str_replace('；', ';', $ass_station);
            $tkExtAttr['ass_station'] = addslashes(serialize(explode(';', $ass_station)));
        }

        if(isset($ticketData['tid']) && $ticketData['tid']>0)
        {   // 以下编辑操作
            $tid = $ticketData['tid']+0;

            $ticketOriginData = $ticketObj->getTicketInfoById($tid);
            if (!$ticketOriginData) {
                self::_return(self::CODE_NO_CONTENT,  '票类不存在,保存失败',$ticketData['ttitle']);
            }
            //print_r($tkBaseAttr);
            //print_r($ticketOriginData);
            //print_r(array_diff($tkBaseAttr, $ticketOriginData));
            //exit;
            $diff_ticket_attr = array_diff_assoc($tkBaseAttr, $ticketOriginData);
             $ret2 = $ret3 = true;
            $ret1 = $ticketObj->UpdateTicketAttributes(
                ['id'=>$tid],
                $diff_ticket_attr,
                Ticket::__TICKET_TABLE__
            );

            if ($ret1!==false) {
                $extAttributes = $ticketObj->getTicketExtInfoByTid($tid);
                $diff_ticket_attr = array_diff_assoc($tkExtAttr, $extAttributes);
                //print_r($extAttributes);
                //print_r($tkExtAttr);
                //print_r(array_diff_assoc( $tkExtAttr, $extAttributes));
                //exit;

                $ret2 = $ticketObj->UpdateTicketAttributes(
                    ['tid'=>$tid],
                    $diff_ticket_attr,
                    Ticket::__TICKET_TABLE_EXT__
                );
                $ret3 = $ticketObj->UpdateTicketAttributes(
                    ['id'=>$ticketOriginData['pid']],
                    ['verify_time'=>date('Y-m-d H:i:s')],
                    Ticket::__PRODUCT_TABLE__
                );
            }
            if ($ret1===false || $ret2===false || $ret3===false) {
                self::_return(self::CODE_CREATED, '保存票类属性失败',$ticketData['ttitle']);
            }

            $daction = "对 $ltitle".$tkBaseAttr['title']." 进行编辑";
            $pid = $ticketOriginData['pid'];
            // 产品有效期监控
            if(count($ticketOriginData))
            {
                $ticketData['pid']    = $ticketOriginData['pid'];
                $ticketData['action'] = 'CreateNewTicket';
                $ticketData['add_ticket']  = ($tid==0) ? 1:0;
                //$ticketData['validHtml_2'] = htmlValid($tkBaseAttr);
                //$ticketData['validHtml_1'] = htmlValid($ticketOriginData);
                //fsockNoWaitPost("http://".IP_INSIDE."/new/d/call/detect_prod.php", $ticketData);
            }
        }
        else {
            // 以下新增操作
            $create_ret = $ticketObj->CreateTicket($tkBaseAttr);
            if($create_ret['code']!=200)
                self::_return(self::CODE_CREATED,  $create_ret['msg'],$ticketData['ttitle']);

            $tid =$create_ret['data']['lastid'];
            $ret = $ticketObj->QueryTicketInfo("id=$tid",'pid');
            $pid = $ret[0]['pid'];

            $tkExtAttr['lid'] = $lid;
            $tkExtAttr['pid'] = $pid;
            $tkExtAttr['tid'] = $tid;
            $extRet = $ticketObj->CreateTicketExtendInfo($tkExtAttr);

            if($extRet['code']!=200) {
                self::_return(self::CODE_CREATED,  $extRet['msg'],$ticketData['ttitle']);
            }
            $daction = '添加门票.'.$ltitle.$tkBaseAttr['title'];
        }
        $ticketObj->UpdateTicketAttributes(
            ['id'=>$pid],
            ['apply_limit'=>$ticketData['apply_limit']+0, 'p_status'=>0],
            Ticket::__PRODUCT_TABLE__
        );
        $output = [
            'code'=>200,
            'data'=>[
                'lid'=>$lid, 'tid'=>$tid, 'pid'=>$pid, 'ttitle'=>$tkBaseAttr['title']
            ]
        ];
        if ($ticketData['p_type']=='F') {
            $packRet = $this->savePackage($tid);
            $output['data']['savePackResult'] = $packRet;
        }
        return $output;
    }

    /**
     * 保存价格数据
     *
     * @param int $pid 产品ID
     * @param array $price_section 区间价格
     * @param array $original_price 修改前的价格,新增时可忽视
     * @return array
     */
    public function SavePrice($pid, $price_section, $original_price=array() )
    {
        $priceWrite = new PriceWrite();
        foreach($price_section as $row) {
            if(($tableId = ($row['id']+0))>0) {
                $intersect = isset($original_price[$tableId]) ?
                    array_diff_assoc($row, $original_price[$tableId]) : [];
                if(count($intersect)==0) continue;
            }
            $action = ($tableId>0) ? 1:0;// 0 插入 1 修改
            $sdate  = date('Y-m-d', strtotime($row['sdate']));
            $edate  = date('Y-m-d', strtotime($row['edate']));
            $apiret = $priceWrite->In_Dynamic_Price_Merge($pid, $sdate,
                $edate, $row['js'], $row['ls'], 0, $action, $tableId, '',
                $row['weekdays'], ($row['storage']+0));
            if($apiret!=100) return array('code'=>$apiret, 'msg'=>PriceWrite::ErrorMsg($apiret));
        }
        return ['code'=>200, 'msg'=>'success'];
    }

    private function VerifyPrice($pid, $price_section, Ticket $ticketObj, $isSectionTicket)
    {
        // 价格判断
        $compareSec = array();
        $changeNote = array();
        $original_price = $ticketObj->getPriceSection($pid);
        foreach($price_section as $row)
        {
            // 期票模式（有效期是时间段）只能全部有价格
            if($isSectionTicket && ($row['weekdays']!='0,1,2,3,4,5,6'))
                parent::apiReturn(self::CODE_INVALID_REQUEST, '期票模式必须每天都有价格');
            if(($tableId = ($row['id']+0))==0) continue; // 已存在表ID
            $section = $row['sdate'].' 至 '.$row['edate'];
            $diff_js = $original_price[$tableId]['js'] - $row['js'];
            $diff_ls = $original_price[$tableId]['ls'] - $row['ls'];
            if($diff_js) $changeNote[] = $section.' 供货价变动，原:'.($original_price[$tableId]['js']/100).'，现:'.($row['js']/100);
            if($diff_ls) $changeNote[] = $section.' 零售价变动，原:'.($original_price[$tableId]['ls']/100).'，现:'.($row['ls']/100);
        }
    }

    private function savePackage($parent_tid)
    {
        if (is_null($this->packObj)) {
            $this->packObj = new PackTicket($parent_tid);
        }
        $child_info = $this->packObj->getCache();
        if (empty($child_info)) return false;
        $packData = json_decode($child_info, true);
        foreach ($packData as $key => $item) {
            $packData[$key]['parent_tid'] = $parent_tid;
        }
        $ret = $this->packObj->savePackageTickets($packData);
        if ($ret!==false) $this->packObj->rmCache();
        return $ret;
    }

    /**
     * 删除价格
     */
    public function remove_price()
    {
        $id     = I('post.id');
        $pid    = I('post.pid');
        $price  = new PriceWrite();
        $res = $price->RemovePrice($id, $pid);
        if ($res !== false) {
            self::apiReturn(self::CODE_SUCCESS, [], '设置成功');
        }
        self::apiReturn(self::CODE_INVALID_REQUEST, [], '设置失败');
    }
}