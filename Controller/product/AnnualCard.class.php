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

        if (!$this->isAjax()) {
            $this->apiReturn(403, [], '我要报警了!!!');
        }
        
        $this->_CardModel =  new CardModel();

    }

    /**
     * 获取所属供应商的年卡产品列表
     * @return [type] [description]
     */
    public function getAnnualCardProducts() {

        $products = $this->_CardModel->getAnnualCardProducts($_SESSION['sid']);

        $this->apiReturn(200, $products);

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

        $this->_CardModel->getAnnualCards($_SESSION['sid'], $pid);

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
     * PC端激活年卡
     */
    public function activateAnnualCard() {
        $identify   = I('identify');
        $mobile     = I('mobile');
        $name       = I('name');

        $member = (new Member())->getMemberInfo(I('mobile'), 'mobile');

        if (!$member) {
            //注册新会员
        }

        $memberid = $member['id'];

        
        if (isset($_POST['update'])) { //确认进行替换

        } else {
            //如果此会员已经绑定过年卡,则询问是否进行替换
            if ($card = $this->_hasBindAnnualCard($memberid, $_SESSION['sid'])) {
                $this->apiReturn(200, ['exist' => 1, 'name' => '来自汪星人的神秘年卡']);
            } else {
                $this->activeAction();
            }
        }

    }
    
    private function _hasBindAnnualCard($memberid, $sid) {
        $identify = "sid={$sid} and memberid={$memberid}";

        return $this->_CardModel->getAnnualCard($identify, '_string');
    }

    /**
     * 激活动作(用于所有渠道的激活操作)
     * @access  public
     * @return [type] [description]
     */
    public function activeAction() {
        // thinking...
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
     * 实例化年卡model
     * @return [type] [description]
     */
    private function _initializeCardModel() {
        static $CardModel = null;

        if (is_object($CardModel)) return $CardModel;

        $CardModel = new CardModel();

        return $CardModel;
    }
}