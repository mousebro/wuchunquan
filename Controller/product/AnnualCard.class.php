<?php

namespace Controller\Product;

use Library\Controller;
use Model\Product\AnnualCard as CardModel;

class AnnualCard extends Controller {

    /**
     * 获取所属供应商的年卡产品列表
     * @return [type] [description]
     */
    public function getAnnualCardProducts() {

        $this->_initializeCardModel()->getAnnualCardProducts();
    }


    /**
     * 创建\录入年卡
     * @return [type] [description]
     */
    public function createAnnualCard() {

        $CardModel = $this->_initializeCardModel()->createAnnualCard();

    }


    /**
     * 删除年卡
     * @return [type] [description]
     */
    public function deleteAnnualCard() {

        $this->_initializeCardModel()->deleteAnnualCard();

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