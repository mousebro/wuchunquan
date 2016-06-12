<?php

namespace Controller\Product;

use Library\Controller;
use Model\Product\AnnualCard as CardModel;
use Model\Product\Ticket;
use Model\Member\Member;

class AnnualCard extends Controller {

    private $_CardModel = null;

    public function __construct() {

        if (!isset($_SESSION['memberID'])) {
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

        if (intval($pid) < 1) {
            $this->apiReturn(400, [], '参数错误');
        }

        $options = [
            'page_size' => I('page_size', '10', 'intval'),
            'page'      => I('page', '1', 'intval')
        ];

        $result = $this->_CardModel->getAnnualCards($_SESSION['sid'], $pid, $options);

        $total = $total_page = 0;
        if ($result) {
            $total = $this->_CardModel->getAnnualCards($_SESSION['sid'], $pid, $options, 'count');
            $total_page = ceil($total / $options['page_size']);
        }

        $return = [
            'list'      => $result ?: [],
            'page'      => $options['page'],
            'page_size' => $options['page_size'],
            'total_page'=> $total_page,
            'total'     => $total
        ];

        $this->apiReturn('200', $return);
    }


    /**
     * 创建\录入年卡,此时都还处于未绑定物理卡状态
     * @return [type] [description]
     */
    public function createAnnualCard() {
        $num = I('num', 1, 'intval');   //创建数量
        $pid = I('pid', '', 'intval');  //关联的年卡产品

        if (intval($num) < 1 || $num > 100 || intval($pid) < 1) {
            $this->apiReturn(400, [], '参数错误');
        }

        if (!$this->_isProductAvalid($pid, $_SESSION['sid'])) {
            $this->apiReturn(400, [], '请选择正确的产品');
        }

        $result = $CardModel->createAnnualCard($num, $_SESSION['sid'], $pid);

        if (!$result) {
            $this->apiReturn(204, [], '生成失败,请重试');
        }

        $this->apiReturn(200, $result);
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
            'p_type'    => 'I'
        ];

        $find = (new Ticket())->getProductInfo($options);

        return $find ? true : false;
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
     * PC端激活年卡,TODO://待优化
     */
    public function activateAnnualCard() {

        $this->_activateCheck();

        $identify   = I('identify');
        $mobile     = I('mobile');
        $name       = I('name');

        $this->_CardModel->startTrans();

        $memberid = $this->_getMemberid($mobile);

        $card = $this->_hasBindAnnualCard($memberid, $_SESSION['sid']);

        if ($card && !isset($_REQUEST['update'])) {
            //需要向用户确认是否进行替换
            // $this->apiReturn(200, ['exist' => 1, 'name' => '来自汪星人的神秘年卡']);
        }
        
        if ($card && isset($_REQUEST['update'])) { 
            //确认替换动作,将会员之前绑定的年卡设为禁用状态
            if (!$this->_CardModel->forbiddenAnnualCard($card['id'])) {
                $this->_CardModel->rollback();
                $this->apiReturn(204, [], '激活失败');
            }
        } 

        if (!$this->activeAction($card_info['id'], $memberid)) {
            $this->_CardModel->rollback();
            $this->apiReturn(200, [], '激活失败');
        }

        $this->_CardModel->commit() && $this->apiReturn(200, [], '激活成功');

    }

    /**
     * pc端年卡激活检测
     * @return [type] [description]
     */
    private function _activateCheck() {
        $identify   = I('identify');
        $mobile     = I('mobile');
        $name       = I('name');

        $identify = "sid={$_SESSION['sid']} and (card_no='{$identify}' or virtual_no='{$identify}' or physics_no='{$identify}')";
        $card_info = $this->_CardModel->getAnnualCard($identify, '_string');

        if (!$card_info) {
            $this->apiReturn(204, [], '未找到相应的卡片信息');
        }

        if ($card_info['memberid']) {
            $this->apiReturn(204, [], '该卡已被使用');
        }
    }

    /**
     * 根据手机号获取用户id，不存在则注册
     * @param  [type] $mobile [description]
     * @return [type]         [description]
     */
    private function _getMemberid($mobile) {
        $member = (new Member())->getMemberInfo($mobile, 'mobile');

        if (!$member) {
            //注册新会员
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
        $identify = "sid={$sid} and memberid={$memberid}";

        return $this->_CardModel->getAnnualCard($identify, '_string');
    }

    /**
     * 年卡激活接口(用于所有渠道的激活操作)
     * @access  public
     * @return [type] [description]
     */
    public function activeAction($card_id, $memberid) {

        //状态变更
        if (!$this->_CardModel->activateAnnualCard($card_id, $memberid)) {
            return false;
        }

        //卡片激活后，清算订单资金
        if (0) {
            $this->_CardModel->rollback();
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

        $memberid_arr = [];
        foreach ($result as $item) {
            if ($item['memberid'])
                $memberid_arr[] = $item['memberid'];
        }   

        $members = $result ? $this->_getMemberInfoByMulti($memberid_arr) : [];
        $members = $this->_replaceKey($members, 'id');

        foreach ($result as $key => $item) {
            if (isset($members[$item['memberid']])) {
                $result[$key]['account'] = $members[$item['memberid']]['account'];
                $result[$key]['mobile'] = $members[$item['memberid']]['mobile'];
            }
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

        return (new Member())->getMemberInfoByMulti($memberid_arr, 'id', 'id,account,mobile');

    }

    /**
     * 获取会员详细信息
     * @return [type] [description]
     */
    public function getMemberDetail() {
        if (($memberid = I('memberid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $result = $this->_CardModel->getMemberDetail($_SESSION['sid'], $memberid);
        $result = $result ?: [];

        foreach ($result as $item) {

        }

        $this->apiReturn(200, $result, []);
    }

    /**
     * 年卡库存详细信息
     * @return [type] [description]
     */
    public function getAnnualCardStorage() {
        if (($pid = I('pid')) < 1) {
            $this->apiReturn(204, [], '参数错误');
        }

        $vir_storage = $this->_CardModel->getAnnualCardStorage($_SESSION['sid'], $pid, 'virtual');
        $phy_storage = $this->_CardModel->getAnnualCardStorage($_SESSION['sid'], $pid, 'physics');

        $cards = [];
        if ($vir_storage && $phy_storage) {
            $options = [
                'status'    => 3,
                'page_size' => I('page_size', '10', 'intval'),
                'page'      => I('page', 1, 'intval'),
            ];

            $cards = $this->_CardModel->getAnnualCards($_SESSION['sid'], $pid, $options);
        }

        $return = [
            'cards'   => $cards,
            'virtual' => $vir_storage,
            'physics' => $phy_storage
        ];

        $this->apiReturn(200, $return);

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