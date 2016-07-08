<?php
/**
 * 银行相关控制器
 *
 * @author dwer
 * @date 2016-07-08
 * 
 */
namespace Controller\Finance;

use Library\Controller;

class Banks extends Controller{
    private $memberId = null;

    public function __construct() {
        $this->memberId = $this->isLogin('ajax');
    }

    /**
     * 获取银行列表
     * @author dwer
     * @date   2016-07-08
     *
     * @return
     */
    public function getList() {
        $page = I('post.page', 1);
        $size = I('post.size', 200);

        $model = $this->model('Finance/Banks');

        //银行列表
        $list = $model->getBanks($page, $size);

        //省份列表
        $province = $model->getBankProvince();

        if($list === false) {
            $this->apiReturn(500, [], '系统错误');
        } else {
            $this->apiReturn(200, ['list' => $list, 'province' => $province]);
        }
    }

    /**
     * 获取城市
     * @author dwer
     * @date   2016-07-08
     *
     * @return
     */
    public function cityList() {
        $province = I('post.province_id', false);
        if(!$province) {
            $this->apiReturn(400, [], '参数错误');
        }

        $model = $this->model('Finance/Banks');

        //城市列表
        $list = $model->getCity($province);

        if($list === false) {
            $this->apiReturn(500, [], '系统错误');
        } else {
            $this->apiReturn(200, ['list' => $list]);
        }
    }


    public function subbranchList() {
        $page = I('post.page', 1);
        $size = I('post.size', 50);
        $name = I('post.name', '');

        $cityId = I('post.city_id', 0);
        $bankId = I('post.bank_id', 0);

        if(!$cityId || !$bankId) {
            $this->apiReturn(400, [], '参数错误');
        }

        $model = $this->model('Finance/Banks');

        $res = $model->getSubbranch($cityId, $bankId, $name, $page, $size);

        if($res === false) {
            $this->apiReturn(500, [], '系统错误');
        } else {
            $this->apiReturn(200, $res);
        }
    }
}