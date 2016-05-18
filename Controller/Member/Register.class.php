<?php
/**
 * 新版注册控制逻辑
 *
 * @author dwer
 * @date   2016-05-18
 */

namespace Controller\Member;

use Library\Controller;
use Library\Tools\Helpers;

class Register extends Controller{

    /**
     * 检测手机号码
     * @author dwer
     * @date   2016-05-18
     *
     * @param $mobile 手机号码
     *
     * @return 
     */
    public function checkMobile() {
        // $mobile = $this->getParam('mobile', 'post');
        // if(!$mobile) {
        //     $this->apiReturn(403, '参数错误');
        // }

        //手机号码验证
        
        $db = Helpers::getPrevDb();
        var_dump($db);


    }

    /**
     * 发送短信验证码
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function sendVcode() {

    }

    /**
     * 注册保存账号等信息
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function index() {

    }

    /**
     * 完善资料
     * @author dwer
     * @date   2016-05-18
     *
     */
    public function addInfo() {

    }

}
