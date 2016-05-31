<?php

namespace Controller\Product;

use Library\Controller;
use Model\Product\AnnualCard as CardModel;

class AnnualCard extends Controller {

    public function __construct() {

        if (!isset($_SESSION['memberID'])) {
            $this->apiReturn(403, [], '请先登录');
        }

    }

    /**
     * 获取所属供应商的年卡产品列表
     * @return [type] [description]
     */
    public function getAnnualCardProducts() {

        $this->_initializeCardModel()->getAnnualCardProducts($_SESSION['sid']);
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

        $this->_initializeCardModel()->getAnnualCards($_SESSION['sid'], $pid);

    }


    /**
     * 创建\录入年卡
     * @return [type] [description]
     */
    public function createAnnualCard() {
        $num = I('num', 1, 'intval');   //创建数量

        $pid = I('pid', '', 'intval');

        if (intval($num) < 1 || $num > 100 || intval($pid) < 1) {
            $this->apiReturn(400, [], '参数错误');
        }

        $CardModel = $this->_initializeCardModel()->createAnnualCard($num, $sid, $pid);

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

        $this->_initializeCardModel()->deleteAnnualCard($where);

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

        $this->bindAnnualCard($_SESSION['sid'], $virtual_no, $card_no, $physics_no);
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