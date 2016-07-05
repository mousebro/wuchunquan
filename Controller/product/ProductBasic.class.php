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
use Library\Model;
use Library\Tools;
use Model\Member\Member;
use Model\Product\AnnualCard;
use Model\Product\Land;
use Model\Product\PackTicket;
use Model\Product\PriceWrite;
use Model\Product\Ticket;

class ProductBasic extends Controller
{
    private $packObj=null;
    private $cardObj=null;
    private $config;
    public function __construct()
    {
        C(include  __DIR__ .'/../../Conf/product.conf.php');
        $this->config = C('');

    }
    private function _return($code, $msg, $title)
    {
        return ['code'=>$code, 'data'=>['ttitle'=>$title,'msg'=>$msg]];
    }

    public function AreaList()
    {
        $area = array();
        $m = new Model();
        $list = $m->table('uu_area')
            ->field('area_id as id, area_name as name, area_parent_id as pid')
            ->select();

        foreach ($list as $item) {
            $area[$item['pid']]['cities'][] = ['id'=>$item['id'], 'name'=>$item['name']];
        }
        return $area;
    }

    /**
     * 保存产品基础数据
     *
     * @param int $apply_did 供应商ID
     * @param Land $landObj
     */
    protected function SaveBasicInfo( $apply_did, Land $landObj )
    {
        $params = [];
        if (!$apply_did || !is_numeric($apply_did)) {
            self::apiReturn(self::CODE_INVALID_REQUEST,'', '供应商不能为空');
        }

        $params = [];

        $params['title']    = I('post.product_name', '', 'strip_tags,addslashes');
        if ( $params['title']=='' ) {
            self::apiReturn(self::CODE_INVALID_REQUEST,'', '产品标题不能为空');
        }
        if(mb_strlen($params['title'],'utf8')>30){
            self::apiReturn(self::CODE_INVALID_REQUEST, [], '景区名称不能超过 30 个字符');
        }
        $params['p_type']    = I('post.product_type');
        if (!in_array($params['p_type'], $this->config['LIMIT_TYPES']) ) {
            self::apiReturn(self::CODE_INVALID_REQUEST, [], '产品类型不对');
        }

        //供应商ID
        $params['apply_did']    = $apply_did;
        $params['sync_id']      = I('post.product_id');
        $params['jtype']        = I('post.product_level');
        if (!$params['sync_id'] || !is_numeric($params['sync_id'])) {
            self::apiReturn(self::CODE_INVALID_REQUEST, [], '对接产品ID错误');
        }
        //详细地址
        $params['address']  = I('post.address', '', 'strip_tags,addslashes');
        //所在地区，省|市|区,获取票付通地区数据：open.12301.cc/areas.json
        $province = I('post.province');//省
        $city     = I('post.city');//市
        if (!is_numeric($province) || !$province) {
            self::apiReturn(self::CODE_INVALID_REQUEST, [], '省份数据类型错误，');
        }
        $zone     = I('post.zone');//区，可为空
        $params['area']     = "$province|$city|$zone";
        //预订须知
        $params['jqts']     = I('post.notice', '', 'strip_tags,addslashes');

        if (I('jqts')) {
            $params['jqts'] = I('jqts', '', 'strip_tags,addslashes');
        }

        //景点详情-图文
        $params['bhjq']     = I('post.details','', 'htmlspecialchars,addslashes');
        //交通指南
        $params['jtzn']     = I('post.traffic','', 'strip_tags,addslashes');
        //缩略图
        $params['imgpath']  = I('post.img_path','', 'strip_tags,addslashes');
        //营业时间
        $params['opentime'] = I('post.opentime', '', 'strip_tags,addslashes');
        //景区联系电话
        $params['tel']      = I('post.tel', '', 'strip_tags,addslashes');
        if($params['tel']!='') {
            if ( !Tools::isphone($params['tel']) && !Tools::ismobile($params['tel']) ) {
                parent::apiReturn(parent::CODE_INVALID_REQUEST,[],'联系电话格式不正确');
            }
        }
        //景区旅游主题,多个主题用英文逗号分隔
        if (isset($_POST['topics'])) {
            $params['topic'] = I('post.topics','','strip_tags');
        }
        if (isset($_POST['lid'])) {
            $result = $landObj->updateProduct($apply_did, I('lid', '', 'intval'), $params);
        } else {
            $result = $landObj->AddProduct($params);
        }
        // $result = $landObj->AddProduct($params);
        self::apiReturn($result['code'], $result['data'], $result['msg']);
    }

    /**
     * 保存门票数据
     *
     * @param int $memberId 会员ID
     * @param array $ticketData 票类属性
     * @param Ticket $ticketObj 票类模型
     * @param Land $landObj 景区模型
     * @return array
     */
    protected function SaveTicket($memberId,  $ticketData, Ticket $ticketObj, Land $landObj)
    {   
        $isSectionTicket = false;// 是否是期票
        if($ticketData['order_start'] && $ticketData['order_end']) $isSectionTicket = true;

        //价格校验
        if (!empty($ticketData['price_section'])) {
            $ret = $this->VerifyPrice($ticketData['pid'], $ticketData['price_section'], $ticketObj, $isSectionTicket);
            if ($ret['code']!=200) {
                return self::_return(self::CODE_INVALID_REQUEST, $ret['data']['msg'], $ticketData['ttitle']);
            }
        }
        $lid = $ticketData['lid']+0;
        $landInfo = $landObj->getLandInfo($lid,false, 'title,p_type,apply_did');
        $ltitle = $landInfo['title'];
        $p_type = $landInfo['p_type'];
        // 验证景区是否存在
        if (!$landInfo || ($landInfo['apply_did']!=$memberId && $memberId!=0)) {
            return self::_return(self::CODE_NO_CONTENT,  '景区不存在',$ticketData['ttitle']);
        }

        if ($p_type == 'I') {
            $crdModel = $this->getCardObj($ticketData['tid'] + 0);
            // $this->cardObj =  new AnnualCard($ticketData['tid'] + 0, $_SESSION['memberID']);
            $default = $crdModel->createDefaultParams();
            $ticketData = array_merge($ticketData, $default);
            $ticketData['validTime'] = $ticketData['delaytype'];
        }

        // 整合数据
        $tkBaseAttr = array();
        $tkExtAttr = array();
        $tkBaseAttr['title']   = $ticketData['ttitle'];
        $tkBaseAttr['landid']  = $ticketData['lid']+0;
        $tkBaseAttr['tprice']  = $ticketData['tprice']+0;    // 门市价

        $tkBaseAttr['pay']     = $ticketData['pay']+0;       // 支付方式 0 现场 1 在线
        if (isset($ticketData['remote_ticket_id'])) $tkBaseAttr['sync_id'] = $ticketData['remote_ticket_id'];
            //套票只允许在线支付

        if ($p_type=='F' && $tkBaseAttr['pay']==0) {
            if (!$ticketObj->allowOfflinePackage($memberId))
                return self::_return(self::CODE_INVALID_REQUEST,  '套票产品只允许在线支付',$ticketData['ttitle']);
        }

        if ($ticketData['fax']) {
            $landObj->UpdateAttrbites(['id'=>$lid], ['fax'=>$ticketData['fax']]);
        }
        $tkBaseAttr['ddays']   = $ticketData['ddays']+0;     // 提前下单时间
        $tkBaseAttr['getaddr'] = $ticketData['getaddr'];     // 取票信息
        $tkBaseAttr['notes']   = $ticketData['notes'];       // 产品说明
        $tkBaseAttr['buy_limit_up']  = $ticketData['buy_limit_up']+0; // 购买上限
        $tkBaseAttr['buy_limit_low'] = $ticketData['buy_limit_low']+0;
        //$tkBaseAttr['order_limit'] = $ticketData['order_limit'];// 验证限制
        $tkBaseAttr['order_limit'] = implode(',', array_diff(array(1,2,3,4,5,6,7), explode(',', $ticketData['order_limit'])));// 验证限制

        if(($tkBaseAttr['buy_limit_up']>0) && $tkBaseAttr['buy_limit_low']>$tkBaseAttr['buy_limit_up'])
            return self::_return(self::CODE_INVALID_REQUEST, '最少购买张数不能大于最多购买张数', $ticketData['ttitle']);

        // 延迟验证
        $delaytime = array(0,0);
        if(isset($ticketData['vtimehour']) && $ticketData['vtimehour']) $delaytime[0] = $ticketData['vtimehour']+0;
        if(isset($ticketData['vtimeminu']) && $ticketData['vtimeminu']) $delaytime[1] = $ticketData['vtimeminu']+0;
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

        if($tkBaseAttr['buy_limit_low']<=0)
            return self::_return(self::CODE_INVALID_REQUEST,  '购买下限不能小于0',$ticketData['ttitle']);

        $tkBaseAttr['max_order_days']    = isset($ticketData['max_order_days']) ? $ticketData['max_order_days']+0:'-1';// 提前预售天数
        $tkBaseAttr['cancel_auto_onMin'] = abs($ticketData['cancel_auto_onMin']); // 未支付多少分钟内自动取消

        // 取消费用（统一）
        $tkBaseAttr['reb']      = isset($ticketData['reb']) ? $ticketData['reb']+0 : 0;   // 实际值以分为单位
        $tkBaseAttr['reb_type'] = isset($ticketData['reb_type']) ? $ticketData['reb_type'] : 1;// 取消费用类型 0 百分比 1 实际值
        if($tkBaseAttr['reb_type']==0) {
            $reb = $tkBaseAttr['reb'] / 100;
            if($reb > 100 || $tkBaseAttr['reb']<0)
                return self::_return(self::CODE_INVALID_REQUEST,  '取消费用百分比值不合法',$ticketData['ttitle']);
            $tkBaseAttr['reb'] = $tkBaseAttr['reb'] / 100;
        }

        // 阶梯取消费用设置
        if(isset($ticketData['cancel_cost']) && $ticketData['cancel_cost'])
        {
            $c_days = array();
            foreach($ticketData['cancel_cost'] as $row)
            {
                if(in_array($row['c_days'], $c_days))
                    return self::_return(self::CODE_INVALID_REQUEST,  '退票手续费日期重叠',$ticketData['ttitle']);
                $c_days[] = $row['c_days'];
            }
        }

        $tkBaseAttr['cancel_cost'] = (isset($ticketData['cancel_cost'])) ? json_encode($ticketData['cancel_cost']):'';
        //$tkBaseAttr['cancel_cost'] = addslashes($tkBaseAttr['cancel_cost']);
        // exit;

        // 订单有效期 类型 0 游玩时间 1 下单时间 2 区间
        $tkBaseAttr['delaytype'] = $ticketData['validTime']+0;
        $tkBaseAttr['delaydays'] = $ticketData['delaydays']+0;
        $tkBaseAttr['order_end'] = $tkBaseAttr['order_start'] = '';
        if($ticketData['validTime']==2){
            //
            if($ticketData['order_end']=='' || $ticketData['order_start']=='')
                return self::_return(self::CODE_INVALID_REQUEST,  '有效期时间不能为空',$ticketData['ttitle']);
            $tkBaseAttr['order_end']   = date('Y-m-d 23:59:59', strtotime($ticketData['order_end']));// 订单截止有效日期
            $tkBaseAttr['order_start'] = date('Y-m-d 00:00:00', strtotime($ticketData['order_start']));
            if ($tkBaseAttr['order_start'] > $tkBaseAttr['order_end']) {
                return self::_return(self::CODE_INVALID_REQUEST,  '订单有效期开始时间不能大于结束时间',$ticketData['ttitle']);
            }
        }

        // 退票规则 0 有效期内、过期可退 1 有效期内可退 2  不可退
        $tkBaseAttr['refund_rule'] = $tkBaseAttr['refund_early_time'] = 0;
        if(isset($ticketData['refund_rule'])) $tkBaseAttr['refund_rule'] = $ticketData['refund_rule']+0;
        if(isset($ticketData['refund_early_time'])) $tkBaseAttr['refund_early_time'] = $ticketData['refund_early_time']+0;

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
        $tkExtAttr['buy_limit']  = $ticketData['buy_limit']+0;
        $tkExtAttr['buy_limit_date']  = $ticketData['buy_limit_date']+0;
        $tkExtAttr['buy_limit_num']  = $ticketData['buy_limit_num']+0;

        if ($tkExtAttr['buy_limit']>2) {
            return self::_return(self::CODE_INVALID_REQUEST,  '购票限制参数错误，大于0小于2',$ticketData['ttitle']);
        }
        if ($tkExtAttr['buy_limit_date']>3) {
            return self::_return(self::CODE_INVALID_REQUEST,  '购票限制时间类型参数错误，大于0小于3',$ticketData['ttitle']);
        }

        // 取消通知供应商 0 不通知 1 通知
        if(isset($ticketData['cancel_notify_supplier']))
            $tkBaseAttr['cancel_notify_supplier'] = $ticketData['cancel_notify_supplier']+0;

        // 分批验证设置
        $tkBaseAttr['batch_check']       = isset($ticketData['batch_check']) ? ($ticketData['batch_check']+0) : 1;
        $tkBaseAttr['batch_day_check']   = isset($ticketData['batch_day_check']) ? ($ticketData['batch_day_check']+0) : 0;
        $tkBaseAttr['batch_diff_identities'] = isset($ticketData['batch_diff_identities']) ? ($ticketData['batch_diff_identities']+0) : 0;


        // 景点类别属性（二次交互）
        $tkBaseAttr['Mpath'] = '';
        if(isset($ticketData['mpath']) && $ticketData['mpath']!='') {
            $tkBaseAttr['Mpath']     = $ticketData['mpath'];
            $tkBaseAttr['Mdetails']  = 1;
        }
        if ($p_type=='H') {
            $tkBaseAttr['order_limit'] = '';//演出类产品不限制验证日期
            $tkBaseAttr['Mpath']     = MAIN_DOMAIN . '/api/Product_check_h.php';
            $tkBaseAttr['Mdetails']  = 1;
        }
        if(isset($ticketData['re_integral'])) $tkBaseAttr['re_integral'] = $ticketData['re_integral'] + 0;

        $tkBaseAttr['apply_did'] = $memberId;// 产品供应商
        echo $tkBaseAttr['apply_did'];

        // 扩展属性 uu_land_f
        $tkExtAttr['confirm_wx']   = isset($ticketData['confirm_wx']) ? ($ticketData['confirm_wx']+0) : 0;
        $tkExtAttr['sendVoucher']  = isset($ticketData['sendVoucher']) ?($ticketData['sendVoucher']+0) : 0;
        // $fData['confirm_sms']  = $oneTicket['confirm_sms']+0;
        $tkExtAttr['tourist_info'] = isset($ticketData['tourist_info']) ? ($ticketData['tourist_info']+0) : 0;

        // 提前预定小时  01:00:00 - 23:59:00
        if(isset($ticketData['dhour'])){
            $tkExtAttr['dhour'] = str_pad($ticketData['dhour'], 5, 0, STR_PAD_LEFT) . ':00';
        }
        if($p_type=='H'){
            $tkExtAttr['zone_id'] = $ticketData['zone_id']+0;
        }

        // 验证时间 08:00|18:00
        $tkExtAttr['v_time_limit'] = '0';
        if(isset($ticketData['v_time_limit']) && $ticketData['v_time_limit'])
        {
            $arr1 = explode('|', $ticketData['v_time_limit']);
            $arr1[0] = str_pad($arr1[0], 5, 0, STR_PAD_LEFT);
            $arr1[1] = str_pad($arr1[1], 5, 0, STR_PAD_LEFT);
            $tkExtAttr['v_time_limit'] = implode('|', $arr1);
        }

        //套票产品的提前预定时间必须大于等于子票
        $PackModel = new PackTicket($ticketData['tid'] + 0);
        if ($p_type == 'F') {
            $child_ticket = $PackModel->childTicketData();
            foreach ($child_ticket as $item) {
                // if($item['dhour'] < date('H:i:s')) $item['ddays'] += 1;
                if ($item['dhour'] < $tkExtAttr['dhour']) {
                    return self::_return(self::CODE_INVALID_REQUEST,  '套票的提前时间不能小于'.$item['dhour'],$ticketData['ttitle']);
                }
                if ($tkBaseAttr['ddays'] < $item['ddays']) {
                    return self::_return(self::CODE_INVALID_REQUEST,  '套票的提前预定天数不能小于'.$item['ddays'],$ticketData['ttitle']);
                }

                if ($item['refund_rule'] > $tkBaseAttr['refund_rule']) {
                    if ($item['refund_rule'] == 1) {
                        $warning = '您只能选择[游玩日期前可退]或者[不可退]';
                    } else {
                        $warning = '您只能选择[不可退]';
                    }
                    return self::_return(self::CODE_INVALID_REQUEST,  '由于子票的限制，'.$warning,$ticketData['ttitle']);
                }
            }
        }

        //线路产品属性
        if ($p_type == 'B') {
            $tkExtAttr['rdays'] = $ticketData['rdays'] + 0;// 游玩天数
            $tkExtAttr['series_model'] = '';
            if (isset($ticketData['g_number']) && $ticketData['g_number']) {
                $tkExtAttr['series_model'] = $ticketData['g_number'] . '{fck_date}';
            }
            if (isset($ticketData['s_number']) && $ticketData['s_number'] && $tkExtAttr['series_model']) {
                $tkExtAttr['series_model'] .= '-' . $ticketData['s_number'];
            }
            $ass_station = $ticketData['ass_station'];
            $ass_station = str_replace('；', ';', $ass_station);
            $tkExtAttr['ass_station'] = serialize(explode(';', $ass_station));
        }

        //接收年卡配置信息
        if($p_type=='I') {
            // if (!isset($this->cardObj)) {
            //     $this->cardObj = new AnnualCard($ticketData['tid'] + 0, $_SESSION['memberID']);
            // }
            $crdModel = $this->getCardObj($ticketData['tid'] + 0);
            if(!isset($crdConf)) $crdConf = [];
            $crdConf['auto_act_day'] = isset($ticketData['auto_active_days']) ? intval($ticketData['auto_active_days']) : -1; //自动激活天数 -1 不自动激活
            $crdConf['srch_limit'] = isset($ticketData['search_limit']) ? $ticketData['search_limit'] : 1; //购买搜索限制 0 不限制 1：卡号（实体卡/虚拟卡）  2：身份证号 4：手机号
            $crdConf['cert_limit'] = isset($ticketData['cert_limit']) ? intval($ticketData['cert_limit']) : 0; //身份证限制 0 无需填写 1：需要填写

            //激活通知 0 不通知 1 通知游客 2通知供应商 3 通知游客和供应商
            $notice_tourist = isset($ticketData['nts_tour']) ? ($ticketData['nts_tour'] + 0) : 0;
            $notice_supplier = isset($ticketData['nts_sup']) ? ($ticketData['nts_sup'] + 0) : 0;

            $crdConf['act_notice'] = 0;
            if($notice_tourist){
                $crdConf['act_notice'] += 1;
            }
            if($notice_supplier){
                $crdConf['act_notice'] += 2;
            }

            if(count($ticketData['priv'])){
                $crdInfo = json_encode(['crdConf' => $crdConf, 'crdPriv' => $ticketData['priv']]);
                $crdModel->rmCache();
                $crdModel->setCache($crdInfo);
            }else{
                return self::_return(self::CODE_INVALID_REQUEST, '年卡特权信息未配置',$ticketData['ttitle']);
            }
        }


        if(isset($ticketData['tid']) && $ticketData['tid']>0)
        {   // 以下编辑操作
            $tid = $ticketData['tid']+0;
            $ticketOriginData = $ticketObj->getTicketInfoById($tid);
            if (!$ticketOriginData) {
                return self::_return(self::CODE_NO_CONTENT,  '票类不存在,保存失败',$ticketData['ttitle']);
            }
            //print_r($tkBaseAttr);
            //print_r($ticketOriginData);
            //print_r(array_diff($tkBaseAttr, $ticketOriginData));
            //exit;
            $diff_ticket_attr = array_diff_assoc($tkBaseAttr, $ticketOriginData);
            if (isset($diff_ticket_attr['pay'])) {
                return self::_return(self::CODE_INVALID_REQUEST,  '票类支付方式属性不允许修改',$ticketData['ttitle']);
            }
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
                //print_r($diff_ticket_attr);
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
                return self::_return(self::CODE_CREATED, '保存票类属性失败',$ticketData['ttitle']);
            }

            $daction = "对 $ltitle".$tkBaseAttr['title']." 进行编辑";
            $pid = $ticketOriginData['pid'];
            //TODO::产品有效期监控
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
            $create_flag = 1;
            // 以下新增操作
            $create_ret = $ticketObj->CreateTicket($tkBaseAttr);
            if($create_ret['code']!=200)
                return self::_return(self::CODE_CREATED,  $create_ret['msg'],$ticketData['ttitle']);

            $tid =$create_ret['data']['lastid'];
            $ret = $ticketObj->QueryTicketInfo("id=$tid",'pid');
            $pid = $ret[0]['pid'];

            $tkExtAttr['lid'] = $lid;
            $tkExtAttr['pid'] = $pid;
            $tkExtAttr['tid'] = $tid;
            $extRet = $ticketObj->CreateTicketExtendInfo($tkExtAttr);

            if($extRet['code']!=200) {
                return self::_return(self::CODE_CREATED,  $extRet['msg'],$ticketData['ttitle']);
            }
            $daction = '添加门票.'.$ltitle.$tkBaseAttr['title'];
        }
        $ticketObj->UpdateTicketAttributes(
            ['id'=>$pid],
            ['apply_limit'=>$ticketData['apply_limit']+0, 'p_status'=>0],
            Ticket::__PRODUCT_TABLE__
        );

        //监听子票的提前预定时间的变化
        $PackModel->updateParentAdvanceAttr($pid, $tkBaseAttr['ddays'], $tkExtAttr['dhour']);

        //监听子票退票规则的变化
        $PackModel->updateParentRefundRuleAttr($pid, $tkBaseAttr['refund_rule'], $tkBaseAttr['refund_early_time']);

        $output = [
            'code'=>200,
            'data'=>[
                'lid'=>$lid, 'tid'=>$tid, 'pid'=>$pid, 'ttitle'=>$tkBaseAttr['title']
            ]
        ];
        if ($ticketData['p_type']=='F' && isset($create_flag)) {
            $packRet = $this->savePackage($tid);
            $output['data']['savePackResult'] = $packRet;
        }

        if($ticketData['p_type']=='I') {
            $cardRet = $this->saveCardConfig($memberId, $tid, $crdModel);
            $output['data']['saveCardResult'] = $cardRet;
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
    protected function SavePrice($pid, $price_section)
    {
        if (!$pid || !is_array($price_section)) {
            return array('code'=>0, 'msg'=>PriceWrite::ErrorMsg(0));
        }
        $priceWrite = new PriceWrite();
        foreach($price_section as $row) {
            if(($tableId = ($row['id']+0))>0) {
                $intersect = isset($this->original_price[$tableId]) ?
                    array_diff_assoc($row, $this->original_price[$tableId]) : $row;
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

    /**
     * 价格校验
     *
     * @param int $pid
     * @param array $price_section 时间段价格
     * @param Ticket $ticketObj  票类Model
     * @param bool $isSectionTicket 是否期票模式
     * @return array
     */
    private function VerifyPrice($pid, $price_section, Ticket $ticketObj, $isSectionTicket)
    {
        // 价格判断
        //$compareSec = array();
        $changeNote = array();
        $original_price = $ticketObj->getPriceSection($pid);
        foreach($price_section as $row)
        {
            // 期票模式（有效期是时间段）只能全部有价格
            if($isSectionTicket && ($row['weekdays']!='0,1,2,3,4,5,6'))
                return $this->_return(self::CODE_INVALID_REQUEST, '期票模式必须每天都有价格', '');
            if(($tableId = ($row['id']+0))==0) continue; // 已存在表ID
            $section = $row['sdate'].' 至 '.$row['edate'];
            $diff_js = $original_price[$tableId]['js'] - $row['js'];
            $diff_ls = $original_price[$tableId]['ls'] - $row['ls'];
            if($diff_js) $changeNote[] = $section.' 供货价变动，原:'.($original_price[$tableId]['js']/100).'，现:'.($row['js']/100);
            if($diff_ls) $changeNote[] = $section.' 零售价变动，原:'.($original_price[$tableId]['ls']/100).'，现:'.($row['ls']/100);
        }
        $this->original_price = $original_price;
        return ['code'=>200];
    }

    /**
     * 保存套票
     *
     * @param $parent_tid
     * @return bool
     */
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
        $id     = I('post.id', 0, 'intval');
        $pid    = I('post.pid', 0, 'intval');
        if (!$pid || !$id) {
            self::apiReturn(self::CODE_INVALID_REQUEST, [], '参数错误');
        }
        $price  = new PriceWrite();
        $res = $price->RemovePrice($id, $pid);
        if ($res !== false) {
            self::apiReturn(self::CODE_SUCCESS, [], '设置成功');
        }
        self::apiReturn(self::CODE_INVALID_REQUEST, [], '设置失败');
    }

    /**
     * 票类上下架
     */
    protected function set_status()
    {

    }

    /**
     * 保存年卡特权信息
     * @param $parent_tid
     *
     * @return bool|string
     */
    private function saveCardConfig($aid, $parent_tid,\Model\Product\AnnualCard $crdModel)
    {
        $card_info = $crdModel->getCache();

        if (empty($card_info) || empty($card_info)) {
            return false;
        }

        $cardData = json_decode($card_info, true);

        $crdConf = $cardData['crdConf'];

        $crdPriv = $cardData['crdPriv'];

        foreach ($crdPriv as $key => $item) {
            $packData[$key]['parent_tid'] = $parent_tid;
        }

        $crdConf['tid'] = $parent_tid;
        $crdConf['aid'] = $aid;
        $ret = $crdModel->saveCardConfig($parent_tid, $crdConf, $crdPriv);
        if ($ret!==false) $crdModel->rmCache();
        return $ret;
    }

    /**
     * @param   integer $parent_tid 年卡主产品的门票id
     *
     * @return AnnualCard|null
     */
    protected function getCardObj($parent_tid)
    {

        if (!isset($_SESSION['memberID'])) {
            parent::apiReturn(self::CODE_AUTH_ERROR, [], '未登录');
        }

        if (!isset($this->cardObj)) {
            $this->cardObj = new AnnualCard($parent_tid, $_SESSION['memberID']);
        }

        return $this->cardObj;
    }
}