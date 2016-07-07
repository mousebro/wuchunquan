<?php

namespace Controller\Product;

use Library\Cache\Cache;
use Library\Controller;
use Model\Product\AnnualCard as CardModel;
use Model\Product\Ticket;
use Model\Product\Land;
use Model\Member\Member;
use Model\Order\OrderTools;
use pft\Member\MemberAccount;

class AnnualCard extends Controller {

    private $_CardModel = null;

    public function __construct() {

        if (!isset($_SESSION['memberID']) && !defined('PFT_API')) {
            $this->apiReturn(401, [], '请先登录');
        }

        // if (!$this->isAjax()) {
        //     $this->apiReturn(403, [], '我要报警了!!!');
        // }
        
        $this->_CardModel =  new CardModel();

    }

    /**
     * 获取所属供应商的年卡产品列表
     * @return [type] [description]
     */
    public function getAnnualCardProducts() {

        $options = [
            'page_size' => I('page_size', '10', 'intval'),
            'page'      => I('page', '1', 'intval')
        ];

        $products = $this->_CardModel->getAnnualCardProducts($_SESSION['sid'], $options , 'select');

        $total = $total_page = 0;
        if ($products) {
            $total = $this->_CardModel->getAnnualCardProducts($_SESSION['sid'], $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $return = [
            'list'      => $products ?: [],
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total
        ];

        $this->apiReturn('200', $return);
    }

    /**
     * 获取指定产品的关联年卡
     * @return [type] [description]
     */
    public function getAnnualCards() {
        $pid = I('pid', '', 'intval');
        $sid = $_SESSION['sid'];

        if (intval($pid) < 1) {
            $this->apiReturn(400, [], '参数错误');
        }

        $options = [
            'status'    => 3,
            'page'      => I('page', '1', 'intval'),
            'page_size' => I('page_size', '10', 'intval'),
            
        ];

        $list = $this->_CardModel->getAnnualCards($sid, $pid, $options);

        $total = $total_page = 0;
        if ($list) {
            $total = $this->_CardModel->getAnnualCards($sid, $pid, $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $virtual_stg = $this->_CardModel->getAnnualCardStorage($sid, $pid);
        $physics_stg = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'physics');

        $return = [
            'list'      => $list ?: [],
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total,
            'virtual'   => $virtual_stg,
            'physics'   => $physics_stg
        ];

        $this->apiReturn('200', $return);
    }


    /**
     * 创建\录入年卡,此时都还处于未绑定物理卡状态
     * @return [type] [description]
     */
    public function createAnnualCard() {
        $pid    = I('pid', '', 'intval');  //关联的年卡产品
        $list   = I('list');

        if (intval($pid) < 1 || !is_array($list)) {
            $this->apiReturn(400, [], '参数错误');
        }

        if  (count($list) == 0) {
            $this->apiReturn(200, [], '录入成功');
        } 

        if (!$this->_isProductAvalid($pid, $_SESSION['sid'])) {
            $this->apiReturn(400, [], '请选择正确的产品');
        }

        $result = $this->_CardModel->createAnnualCard($list, $_SESSION['sid'], $pid);

        if (!$result) {
            $this->apiReturn(204, [], '录入失败,请重试');
        }

        $this->apiReturn(200, [], '录入成功');
    }

    /**
     * 选择的年卡产品是否合法
     * @param  [type]  $pid [description]
     * @param  [type]  $sid [description]
     * @return boolean      [description]
     */
    private function _isProductAvalid($pid, $sid) {
        $options['where'] = [
            'id'        => $pid,
            'apply_did' => $sid,
            'p_status'  => 0,
            // 'p_type'    => 'I'
        ];

        $Ticket = new Ticket();

        $find = $Ticket->getProductInfo($options);

        $type = $Ticket->getProductType($pid);

        return $find && $type == 'I' ? true : false;
    }


    /**
     * 删除年卡
     * @return [type] [description]
     */
    public function deleteAnnualCard() {

        if (isset($_GET['virtual_no'])) {
            $where = [
                'sid'        => $_SESSION['sid'],
                'virtual_no' => I('virtual_no'),
                'status'     => 3
            ];
        } else {
            $where = [
                'sid'        => $_SESSION['sid'],
                'status'     => 3
            ];
        }

        if ($this->_CardModel->deleteAnnualCard($where)) {
            $this->apiReturn(200, [], '删除成功');
        } else {
            $this->apiReturn(200, [], '删除失败');
        }

    }

    /**
     * 绑定物理卡（完善年卡信息）
     * @return [type] [description]
     */
    public function bindAnnualCard() {
        $card_no    = I('card_no');
        $physics_no = I('physics_no');
        $virtual_no = I('virtual_no');

        if (!$card_no || !$physics_no || !$virtual_no) {
            $this->apiReturn(400, [], '参数错误');
        }

        $result = $this->_CardModel->bindAnnualCard($_SESSION['sid'], $virtual_no, $card_no, $physics_no);

        if ($result) {
            $this->apiReturn(200, [], '绑定成功');
        } else {
            $this->apiReturn(204, [], '绑定失败');
        }
    }

    /**
     * pc端激活前的检测
     * @return [type] [description]
     */
    public function activeCheck() {
        $identify   = I('identify');
        $type       = I('type');

        if (!$identify || !$type) {
            $this->apiReturn(204, [], '参数错误');
        }

        $card = $this->_activateCheck($identify, $type, $_SESSION['sid']);

        $ticket = (new Ticket)->getTicketInfoByPid($card['pid']);

        $need_ID = $this->_CardModel->isNeedID($_SESSION['sid'], $ticket['id']);

        $data = [
            'need_ID'       => $need_ID,
            'virtual_no'    => $card['virtual_no'],
            'physics_no'    => $card['physics_no'],
            'card_no'       => $card['card_no']
        ];

        $this->apiReturn(200, $data);
    }

    /**
     * PC端供应商手动激活年卡
     */
    public function activateForPc($sid = 0) {

        $identify   = I('identify');
        $type       = I('type');
        $mobile     = I('mobile');
        $name       = I('name', '');
        $id_card    = I('id_card', '');
        $vcode      = I('vcode');
        $address    = I('address', '');
        $province   = I('province', '', 'intval');
        $city       = I('city', '', 'intval');
        $head_img   = I('head_img', '');

        // $identify = '2427299638';
        // $type = 'physics';
        // $mobile = 13123396340;
        // $name = '翁彬';
        // $id_card = 350181199106012339;
        // $address    = '一念天堂';
        // $province   = 1;
        // $city       = 1111;
        // $head_img   = 'http://git.12301.io/avatars/17';


        if (!$identify || !$type || !$mobile) {
            $this->apiReturn(204, [], '参数错误');
        }

        $sid || $this->_checkVcode($mobile, $vcode);

        $sid = $sid ?: $_SESSION['sid'];

        $card = $this->_activateCheck($identify, $type, $sid);

        $ticket = (new Ticket)->getTicketInfoByPid($card['pid']);

        $need_ID = $this->_CardModel->isNeedID($sid, $ticket['id']);

        if ($need_ID && !$id_card) {
            $this->apiReturn(204, [], '请填写身份证号码');
        }

        if ($card['status'] != 0) {
            $this->apiReturn(204, [], '年卡状态有误,请检查是否出售或禁用');
        }

        $replace = I('replace', '', 'intval') || false;

        //会员只能某个供应商的一张年卡
        $memberid = $this->_isNeedToReplace($mobile, $sid, $replace, $name, $id_card, $address, $province, $city, $head_img);

        if (!$this->activeAction($card['virtual_no'], $memberid)) {

            $this->apiReturn(204, [], '激活失败');

        }

        $this->_CardModel->verifyAnnualOrder($card['virtual_no'], 'virtual_no');

        // $this->_CardModel->createRelationShip($sid, $memberid);

        $this->apiReturn(200, [], '激活成功');

    }

    /**
     * 平台销售虚拟卡时激活
     * @return [type] [description]
     */
    public function activeForVirtualSale() {
        $mobile     = I('mobile');
        $name       = I('name');

        $replace = I('replace') || false;

        $this->_isNeedToReplace($mobile, $_SESSION['sid'], $replace);

        $options = [
            'where' => [
                'card_no'=> '',
                'status' => 3
            ]
        ];

        //从虚拟库存中获取一张未激活的卡片
        $memberid = $card = $this->_CardModel->getAnnualCard(1, 1, $options);

        if (!$this->activeAction($card['virtual_no'], $memberid)) {

            $this->apiReturn(200, [], '激活失败');

        }

        $this->apiReturn(200, [], '激活成功');

    }

    /**
     * 对会员已绑定的年卡进行替换
     * @param  [type]  $mobile  [description]
     * @param  [type]  $sid     [description]
     * @param  [type]  $replace [description]
     * @return boolean          [description]
     */
    private function _isNeedToReplace($mobile, $sid, $replace, $name, $id_card,
         $address = '', $province = 0, $city = 0, $head_img = '') {

        $memberid = $this->_getMemberid($mobile, $name, $id_card, $address, $province, $city, $head_img);

        $card = $this->_hasBindAnnualCard($memberid, $sid);

        //向用户确认是否进行替换
        if ($card && !$replace) {

            $product = (new Ticket)->getProductInfo($card['pid'], ['field' => 'p_name']);

            $data = [
                'exist'     => 1,
                'name'      => $product['p_name'],
                'left'      => $this->getPrivilegessLeft($memberid, $sid, $card['pid'], $card['virtual_no']),
                'mobile'    => $mobile,
                'id_card'   => $id_card
            ];

            $this->apiReturn(200, $data);
        }

        //用户确认进行替换
        if ($card && $replace) {
            if (!$this->_CardModel->forbiddenAnnualCard($card['id'])) {
                $this->apiReturn(204, [], '替换失败');
            }
        }

        return $memberid;
    }

    /**
     * 获取指定年卡的剩余的特权支付次数
     * @param  [type] $memberid [description]
     * @param  [type] $sid      [description]
     * @param  [type] $pid      [description]
     * @return [type]           [description]
     */
    public function getPrivilegessLeft($memberid, $sid, $pid, $virtual_no) {

        return $this->_CardModel->getPrivilegessLeft($memberid, $sid, $pid, $virtual_no);
    
    }

    /**
     * pc端年卡激活检测
     * @return [type] [description]
     */
    private function _activateCheck($identify, $type, $sid) {

        $type = $this->_CardModel->parseIdentifyType($identify, $type);

        if (!in_array($type, ['card_no', 'virtual_no', 'physics_no', 'mobile'])) {
            $this->apiReturn(204, [], '类型参数错误');
        }

        $options = [
            'where' => [
                'sid' => $sid,
                $type => dechex($identify)
            ]
        ];

        $card_info = $this->_CardModel->getAnnualCard(1, 1, $options);

        if ($card_info['sid'] != $_SESSION['sid']) {
            $this->apiReturn(204, [], '您没有激活的权限');
        }

        if (!$card_info) {
            $this->apiReturn(204, [], '未找到相应的年卡信息');
        }

        if ($card_info['memberid']) {
            $this->apiReturn(204, [], '该卡已绑定其他用户');
        }

        return $card_info;
    }

    /**
     * 根据手机号获取用户id，不存在则注册
     * @param  [type] $mobile   手机
     * @param  string $name     姓名
     * @param  string $id_card  身份证
     * @param  string $address  详细地址
     * @param  string $province 省份
     * @param  string $city     城市
     * @param  string $head_img 头像
     * @return int    会员id
     */
    private function _getMemberid($mobile, $name = '', $id_card = '', 
        $address = '', $province = 0, $city = 0, $head_img = '') {

        $member = (new Member())->getMemberInfo($mobile, 'mobile');

        if (!$member) {
            include '/var/www/html/new/com.inc.php';
            include '/var/www/html/new/d/common/func.inc.php';
            include '/var/www/html/new/d/class/MemberAccount.class.php';

            $main_data = [
                'dtype'     => 5,
                'dname'     => $name ?: $mobile,
                'mobile'    => $mobile,
                'password'  => md5( md5( substr($mobile, 6) ) ),
                'address'   => $address,
                'headphoto' => $head_img
            ];

            $extra_data = [
                'id_card_no'    => $id_card,
                'province'      => $province,
                'city'          => $city
            ];

            $mem = new MemberAccount($le);
            $result = $mem->register($main_data, $extra_data);

            if ($result['status'] == 'fail') {
                $this->apiReturn(204, [], '会员注册出现异常');
            } else {
                $body = explode('|', $result['body']);
                return $body[0];
            }
        }

        return $member['id'];
    }
    

    /**
     * 判断用户是否已经绑定过其他年卡
     * @param  [type]  $memberid [description]
     * @param  [type]  $sid      [description]
     * @return boolean           [description]
     */
    private function _hasBindAnnualCard($memberid, $sid) {

        $options['where'] = [
            'sid'       => $sid,
            'memberid'  => $memberid,
            'status'    => ['in', '0,1']
        ];

        return $this->_CardModel->getAnnualCard(1, 1, $options);
    }

    /**
     * 年卡激活接口(用于所有渠道的激活操作)
     * @access  public
     * @return [type] [description]
     */
    public function activeAction($virtual_no, $memberid) {

        //状态变更
        if (!$this->_CardModel->activateAnnualCard($virtual_no, $memberid)) {
            return false;
        }

        return true;
    }

    /**
     * 根据手机号查询会员信息是否已经存在
     * @param  [type]  $mobile [description]
     * @return boolean         [description]
     */
    public function isMemberExists() {

        if (!I('mobile') || strlen(I('mobile')) != 11) {
            $this->apiReturn(400, '请填写正确的手机号');
        }

        //TODO:需要返回的字段筛选
        $member = (new Member())->getMemberInfo(I('mobile'), 'mobile');

        $this->apiReturn(200, $member);

    }

    /**
     * 获取年卡会员列表
     * @return [type] [description]
     */
    public function getMemberList() {

        $options = [
            'page_size' => I('page_size', 10, 'intval'),
            'page'      => I('page', 1, 'intval'),
            'status'    => I('status', 1, 'intval'),
            'identify'  => I('identify', '')
        ];

        $result = $this->_CardModel->getMemberList($_SESSION['sid'], $options, 'select');

        $total = $total_page = 0;
        if ($result) {
            $total = $this->_CardModel->getMemberList($_SESSION['sid'], $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $result = $result ?: [];

        $memberid_arr = $pid_arr = [];
        foreach ($result as $item) {
            if ($item['memberid']) {
                $memberid_arr[] = $item['memberid'];
            }

            $memberid_arr[] = $item['sid'];

            $pid_arr[] = $item['pid'];
        }

        $members = $result ? $this->_getMemberInfoByMulti($memberid_arr) : [];

        if ($members) {
            $members = $this->_replaceKey($members, 'id');
        }
        
        foreach ($result as $key => $item) {
            if (isset($members[$item['memberid']])) {
                $result[$key]['account'] = $members[$item['memberid']]['account'];
                $result[$key]['mobile'] = $members[$item['memberid']]['mobile'];
                            }

            if (isset($members[$item['sid']])) {
                $result[$key]['supply'] = $members[$item['sid']]['dname'];
            }

            $result[$key]['sale_time'] = date('Y-m-d : H:i:s', $item['sale_time']);
        }

        $pname_map = $this->_CardModel->getCardName($pid_arr);

        foreach ($result as $key => $item) {
            $result[$key]['title'] = $pname_map[$item['pid']];
        }


        $return = [
            'list'      => $result,
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total
        ];

        $this->apiReturn(200, $return ?: []);
    }

    /**
     * 获取多用户信息
     * @param  [type] $memberid_arr [description]
     * @return [type]               [description]
     */
    private function _getMemberInfoByMulti($memberid_arr) {

        return (new Member())->getMemberInfoByMulti($memberid_arr, 'id', 'id,dname,account,mobile');

    }

    /**
     * 获取会员详细信息
     * @return [type] [description]
     */
    public function getMemberDetail() {
        if (($memberid = I('memberid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $sid = $_SESSION['sid'];

        $list = $this->_CardModel->getMemberDetail($sid, $memberid);
        $list = $list ?: [];

        $member_info = (new Member())->getMemberInfo($memberid);

        $return['member'] = [
            'account'   => $member_info['account'],
            'mobile'    => $member_info['mobile']
        ];

        $Ticket = new Ticket();

        $pid_arr = [];
        foreach ($list as $key => $item) {

            $pid_arr[] = $item['pid'];

            $ticket = $Ticket->getTicketInfoByPid($item['pid']);

            $valid_time = $this->_CardModel->getPeriodOfValidity(
                $item['sid'], 
                $ticket['id'],
                $item['sale_time'], 
                $item['active_time']
            );

            $list[$key]['valid_time'] = $valid_time;

            $privs = $this->_CardModel->getPrivileges($item['pid']);

            foreach ($privs as $priv) {

                $use = $this->_CardModel->getRemainTimes($sid, $priv['tid'], $memberid, true, $item['virtual_no']);

                $all = $priv['use_limit'] == -1 ? '不限' : explode(',', $priv['use_limit'])[2];

                $list[$key]['priv'][] = [
                    'title' => $priv['ltitle'] . '-' . $priv['title'],
                    'use'   => $use . '/' . $all
                ];
            }

            $sid_arr[] = $item['sid'];
        }

        $pname_map = $this->_CardModel->getCardName($pid_arr);

        $supplys = $list ? $this->_getMemberInfoByMulti($sid_arr) : [];
        $supplys = $this->_replaceKey($supplys, 'id');
        
        foreach ($list as $key => $item) {
            $list[$key]['supply'] = $supplys[$item['sid']]['dname'];
            $list[$key]['title']  = $pname_map[$item['pid']];
        }

        $return['list'] = $list;

        $this->apiReturn(200, $return, []);
    }

    /**
     * 获取用户的年卡消费订单
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function getHistoryOrder() {

        if (!$memberid = I('memberid', '', 'intval')) {
            $this->apiReturn(204, [], '参数错误');
        }

        $options = [
            'page_size' => I('page_size', '10', 'intval'),
            'page'      => I('page', '1', 'intval')
        ];

        $sid = $_SESSION['sid'];

        $orders = $this->_CardModel->getHistoryOrder($sid, $memberid, $options);

        $total = $total_page = 0;
        if ($orders) {
            $total = $this->_CardModel->getHistoryOrder($sid, $memberid, $options, 'count');

            $total_page = ceil($total / $options['page_size']);
        }

        $return = [
            'list'  => $orders ?: [],
            'total' => $total,
            'total_page' => $total_page,
            'page'  => $options['page']
        ];

        $this->apiReturn(200, $return);
    }

    /**
     * 年卡库存详细信息
     * @return [type] [description]
     */
    public function getAnnualCardStorage() {
        if (($pid = I('pid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $product = (new Ticket)->getProductInfo($pid);

        $sid = $_SESSION['sid'];

        $vir_storage = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'virtual');
        $phy_storage = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'physics');

        $page_size  = I('page_size', '10', 'intval');
        $page       = I('page', 1, 'intval');

        $cards = [];
        if ($vir_storage ||  $phy_storage) {
            $options = [
                'status'    => 3,
                'page_size' => $page_size,
                'page'      => $page,
            ];

            $cards = $this->_CardModel->getAnnualCards($sid, $pid, $options);
            $total = $this->_CardModel->getAnnualCards($sid, $pid, $options, 'count');
            // var_dump($total);die;
        }

        $return = [
            'title'         => $product['p_name'],
            'cards'         => $cards,
            'virtual'       => $vir_storage,
            'physics'       => $phy_storage,
            'page'          => $page,
            'total_page'    => ceil($total / $page_size) 
        ];

        $this->apiReturn(200, $return);

    }

    /**
     * 获取虚拟卡库存
     * @return [type] [description]
     */
    public function getVirtualStorage() {
        if (($pid = I('pid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $sid = I('sid', '', 'intval');

        $vir_storage = $this->_CardModel->getAnnualCardStorage($sid, $pid, 'virtual');

        $return = ['storage' => $vir_storage];

        $this->apiReturn(200, $return);
    }

    /**
     * 可添加到年卡特权的产品(自供应 + 转分销一级)
     * @return [type] [description]
     */
    public function getLands() {

        $result = $this->_CardModel->getLands($_SESSION['sid'], I('keyword'));

        $this->apiReturn(200, $result);
    }

    /**
     * [可添加到年卡特权的门票(自供应 + 转分销一级)
     * @return [type] [description]
     */
    public function getTickets() {

        include '/var/www/html/new/d/class/SoapInit.class.php';
        include '/var/www/html/new/d/class/abc/PFTCoreAPI.class.php';

        $soap_cli = (new \SoapInit())->GetSoapInside();

        $tickets = $this->_CardModel->getTickets($_SESSION['sid'], (int)I('aid'), (int)I('lid'));

        foreach ($tickets as $key => $item) {

            if ($item['apply_did'] == $_SESSION['sid']) {
                continue;
            }

            $price = \PFTCoreAPI::pStorage(
                $soap_cli, $_SESSION['saccount'], 
                $item['pid'], (int)I('aid'), date('Y-m-d'), 2
            );

            if ($price['js']['p'] == -1) {
                unset($tickets[$key]);
            }
        }
        
        $this->apiReturn(200, $tickets);
    }

    public function orderSuccess() {

        $ordernum = I('ordernum', '', 'intval');

        if (!$ordernum) {
            $this->apiReturn(204, [], '参数错误');
        }

        
        $order_info = $this->_CardModel->orderSuccess($ordernum);

        $order_detail = (new OrderTools)->getOrderInfo($ordernum);

        $return = [
            'ordernum'  => $ordernum,
            'type'      => $order_info[0]['physics_no'] ? 'physics' : 'virtual',
            'list'      => $order_info,
            'price'     => $order_detail['totalmoney'] / 100,
            'date'      => date('Y-m-d H:i:s', time()) 
        ];

        if ($return['type'] == 'virtual') {
            //订单直接验证
            $this->_CardModel->verifyAnnualOrder($ordernum);
        }

        $this->apiReturn(200, $return);

    }

    /**
     * 图片上传
     * @return [type] [description]
     */
    public function uploadImg() {
        include '/var/www/html/new/d/class/Uploader.class.php';

        $callback_id = I('callback_id', 0, 'intval');

        $config = array(
            "savePath"      => IMAGE_UPLOAD_DIR ."{$_SESSION['account']}/".date('Y-m-d'),
            "maxSize"       => 2048, //单位KB
            "allowFiles"    => array(".gif", ".png", ".jpg", ".jpeg", ".bmp"),
            'simpleFolder'  => true,
        );

        $file   = key($_FILES);
        $Upload = new \Uploader($file, $config);
        $img_info = $Upload->getFileInfo();

        if ($img_info['state'] == 'SUCCESS') {

            $img_url = IMAGE_URL . "{$_SESSION['account']}/".date('Y-m-d').'/'.$img_info['name'];
            $r = ['code' => 200, 'data' => ['src' => $img_url]];

        } else {
            $r = ['code' => 204, 'data' => [], 'msg' => '上传失败']; 
        }

        $r = json_encode($r);

        $script = '<script type="text/javascript">
                var FileuploadCallbacks=window.parent.FileuploadCallbacks['.$callback_id.'];
                for(var i in FileuploadCallbacks) FileuploadCallbacks[i]('.$r.');
                </script>';
        echo $script;
    }

    //下单页面获取卡片接口
    public function getCardsForOrder() {

        $pid = I('pid', '', 'intval');

        if ($pid < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $physics = I('physics', '', 'dechex');

        //购买虚拟卡
        if ($physics == '0') {
            $options = [
                'where' => ['pid' => $pid,'card_no'=> '','status' => 3],
                'field' => 'virtual_no,sid,physics_no,card_no',
                'order' => 'create_time asc'
            ];
            $virtual = $this->_CardModel->getAnnualCard(1, 1, $options);

            if (!$virtual) {
                $this->apiReturn(204, [], '虚拟卡库存不足');
            } else {
                $this->apiReturn(200, [$virtual]);
            }
        }

        $physics_arr = explode(',', $physics);

        if (count($physics_arr) > 10) {
            $this->apiReturn(204, [], '一次最多只能购买10张物理卡');
        }

        $options = [
            'where' => [
                'pid'       => $pid,
                'physics_no'=> ['in', $physics],
                'status'    => 3
            ],
            'field' => 'virtual_no,sid,physics_no,card_no',
        ];

        $physics_cards = $this->_CardModel->getAnnualCard(1, 1, $options, 'select');
        
        $this->apiReturn(200, $physics_cards ?: []);
    }

    /**
     * 获取下单信息
     * @return [type] [description]
     */
    public function getOrderInfo() {

        include '/var/www/html/new/d/class/abc/PFTCoreAPI.class.php';

        $soap = $this->getSoap();

        $aid    = I('aid', '', 'intval');
        $pid    = I('pid', '', 'intval');
        $type   = I('type');

        if ($aid < 1 || $pid < 1 || !in_array($type, ['virtual', 'physics'])) {
            $this->apiReturn(204, [], '参数错误');
        }

        $data = [];

        $price = \PFTCoreAPI::pStorage($soap, $_SESSION['saccount'], $pid, $aid, date('Y-m-d'));

        if ($price['js']['p'] == -1) {  
            //获取不到价格
            $this->apiReturn(403, [], '当前产品不可购买');
        }

        //门票信息
        $product = (new Ticket())->getTicketInfoByPid($pid);
        //景区信息
        $land    = (new Land())->getLandInfo($product['landid'], false, 'title,bhjq');

        //虚拟库存
        $storage = -1;
        if ($type == 'virtual') {
            $storage = $this->_CardModel->getAnnualCardStorage($product['apply_did'], $pid, $type);
        }

        $data['product'] = [
            'ltitle'    => $land['title'],
            'title'     => $product['title'],
            'price'     => $price['js']['p'],
            'storage'   => $storage
        ];

        $data['need_ID'] = $this->_CardModel->isNeedID($aid, $product['id']);

        //年卡囊括的特权信息
        $data['privileges'] = $this->_CardModel->getPrivileges($pid);

        $data['pay']['is_self'] = intval($aid == $_SESSION['sid']);

        if (!$data['pay']['is_self']) {
            $remain = $this->_getRemainAndCredit($_SESSION['memberID'], $aid, $soap);
            $data['pay']['remain'] = $remain['remain'];
            $data['pay']['credit'] = $remain['credit'];
        }

        //供应商信息
        $supplier = (new Member())->getMemberInfo($product['apply_did']);

        $data['supplier'] = [
            'name'      => $supplier['dname'],
            'linkman'   => $supplier['mobile'],
            'intro'     => $land['bhjq']
        ];

        $this->apiReturn(200, $data);

    }

    /**
     * 获取账户余额和供应商授信余额
     * @param  [type] $memberid 会员id
     * @param  [type] $aid      供应商id
     * @return [type]           [description]
     */
    private function _getRemainAndCredit($memberid, $aid, $soap) {
        $remain = $soap->PFT_Member_Fund($memberid, 0, null);
        $remain = simplexml_load_string($remain);
        $distri_info['remain'] = (int)$remain->Rec->UUamoney / 100;

        //供应商账户余额
        $remain = $soap->PFT_Member_Fund($memberid, 1, $aid);
        $remain = simplexml_load_string($remain);
        $s_remain = (int)$remain->Rec->UUkmoney / 100;

        $limit = $soap->PFT_Member_Fund($memberid, 2, $aid);
        $limit = simplexml_load_string($limit);
        $distri_info['credit'] = sprintf("%.2f", $s_remain + (int)$limit->Rec->UUbasecredit / 100);

        return $distri_info;
    }

    /**
     * 虚拟卡下单，判断是否需要替换
     * @return boolean [description]
     */
    public function isNeedToReplace() {
        $mobile     = I('mobile', '', 'intval');
        $name       = I('name');
        $sid        = I('sid');
        $id_card    = I('id_card');

        if (!$mobile || !$name || !$sid) {
            $this->apiReturn(204, [], '参数错误');
        }

        $this->_isNeedToReplace(I('mobile'), $sid, $replace, $name, $id_card);

        $this->apiReturn(200, ['exist' => 0]);
    }

    /**
     * 发送验证码
     * @return [type] [description]
     */
    public function sendVcode() {
        $mobile = I('mobile');

        if (!ismobile($mobile)) {
            $this->apiReturn(204, [], '请输入正确的手机号');
        }

        $code_info = $this->_getCodeInfo($mobile);

        if ($code_info && (time() - $code_info['time'] < 60)) {
            $this->apiReturn(204, [], '操作太频繁');
        }

        $code = substr(str_shuffle('123456789'), 0, 6);

        $content = str_replace('{vcode}', $code, $this->_getVcodeTpl());

        $soap = $this->getSoap();

        $result = $soap->Send_SMS_V($mobile, $content);

        if ($result == 100) {

            $data = [
                'code'  => $code,
                'time'  => time()
            ];

            Cache::getInstance('redis')->set(md5($mobile . 'annual_active'), json_encode($data), '', 1800);

            $this->apiReturn(200, [], '验证码发送成功');

        } else {
            $this->apiReturn(204, [], '验证码发送失败');
        }
    }

    /**
     * 验证码检测
     * @param  [type] $mobile [description]
     * @param  [type] $code   [description]
     * @return [type]         [description]
     */
    private function _checkVcode($mobile, $code) {
        $cache_info = $this->_getCodeInfo($mobile);

        if (!$cache_info) {
            $this->apiReturn(204, [], '验证码已过期');
        }

        if ($cache_info['code'] != $code) {
            $this->apiReturn(204, [], '验证码错误');
        }
    }

    private function _getCodeInfo($mobile) {

        $cache = Cache::getInstance('redis')->get(md5($mobile . 'annual_active'));

        return json_decode($cache, true);

    }

    private function _getVcodeTpl() {

        return '您正在使用年卡激活服务，验证码：{vcode}';

    }


    public function test() {
        // var_dump($this->_CardModel->verifyAnnualOrder(3316562));die;

        // var_dump((new \Api\AnnualCard())->sendVcode());die;

        // $this->_CardModel->getPeriodOfValidity(3385, 29155);die;
        // var_dump((new \Api\AnnualCard())->activate());die;
        // echo json_encode(($this->_CardModel->getCrdConf(5938)), JSON_UNESCAPED_UNICODE);die;
        var_dump((new \Api\AnnualCard())->annualConsume());
        // $this->_CardModel->consumeCheck(3385, 3385, 28460);
    }


    /**
     * 实例化年卡model
     * @return [type] [description]
     */
    private function _initializeCardModel() {
        static $CardModel = null;

        if (is_object($CardModel)) return $CardModel;

        $CardModel = new CardModel();

        return $CardModel;
    }

    private function _replaceKey($arr, $key) {
        $new_arr = [];

        foreach ($arr as $item) {
            $new_arr[$item[$key]] = $item;
        }

        return $new_arr;
    }
}