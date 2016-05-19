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
use Library\Cache\Cache;
use pft\member\MemberAccount;


class Register extends Controller{

    public function __construct() {
        //授权验证
        $isLegal = $this->_auth();

        if(!$isLegal) {
            $this->apiReturn(403, '非法请求');
        }
    }

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
        $mobile = I('post.mobile');

        if(!$mobile) {
            $this->apiReturn(406, '参数错误');
        }

        //手机号码验证
        if(!ismibile($mobile)) {
            $this->apiReturn(406, '手机号码不正确');
        }

        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        //判断手机号码是不是已经注册了
        $res = $memModel->_chkPassport($mobile);

        if($res) {
            //已经注册
            $this->apiReturn(200, '已经注册', array('is_register' => 1));
        } else {
            $this->apiReturn(200, '没有注册', array('is_register' => 0));
        }
    }

    /**
     * 发送短信验证码
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function sendVcode() {
        $mobile = I('post.mobile');

        if(!$mobile) {
            $this->apiReturn(406, '参数错误');
        }

        //手机号码验证
        if(!ismibile($mobile)) {
            $this->apiReturn(406, '手机号码不正确');
        }

        //图形验证码验证
        // $isLegal = $this->_authImage();

        // if(!$isLegal) {
        //     $this->apiReturn(403, '图形验证码错误');
        // }

        $blackList   = load_config('black_list');
        $blackMobile = $blackList['mobile'];
        if (in_array($mobile, $blackMobile)) {
            $this->apiReturn(403, '该手机号已经被加入黑名单。');
        }

        $cacheRedis = Cache::getInstance('redis');
        $ip         = ip();
        $cacheKey   = "mobile:$ip:$mobile";
        $send_time  = $cacheRedis->get($cacheKey,'', true);
        if ($send_time > 8) {
            $this->apiReturn(403, '该手机号发送次数超出系统限制。');
        }

        //发送频率验证
        if(I('session.timer')) {
            $timeDiff = $_SERVER['REQUEST_TIME'] - I('session.timer');
            if($timeDiff <= 5) {
                $this->apiReturn(403, '发送间隔太短！请在60秒后再重试。');
            }
        }

        //判断手机号码是不是已经注册了
        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        //判断手机号码是不是已经注册了
        $res = $memModel->_chkPassport($mobile);
        if($res) {
            $this->apiReturn(403, '该手机号用户已注册票付通会员，请尝试更换其它号码，若有疑问请联系我们！');
        }

        //获取短信模板
        $smsConfig = load_config('sms');
        $registerTpl = $smsConfig['register'];
        if(!$registerTpl) {
            $this->apiReturn(403, '短信模板不存在！');
        }

        //发送短信
        $soap = Helpers::GetSoapInside();
        $res  = MemberAccount::sendVcode($mobile, $registerTpl, $soap, true);

         if($res == 100) {
            $res = $cacheRedis->incrBy($cacheKey);

            $this->apiReturn(200, '发送验证码成功');
        } else {
            $this->apiReturn(500, '对不起，短信服务器发生故障，造成的不便我们感到十分抱歉。请联系我们客服人员。');
        }
    }
    /**

     * 注册保存账号等信息
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function index() {
        //参数验证
        $company = I('post.company');
        $mobile  = I('post.mobile');
        $pwd     = I('post.pwd');
        $vcode   = I('post.vcode');
        $dtype   = I('post.dtype');

        if(!$company || !$mobile ||!$pwd ||!$vcode || !in_array($dtype, array(0, 1))) {
            $this->apiReturn(406, '参数错误。');
        }

        //手机验证码验证
        $isLegal = $this->_authVcode();

        if(!$isLegal) {
            $this->apiReturn(406, '验证码错误！');
        }

        //个人/企业实名验证
        $company = safetxt($company);
        if(!is_chinese($company)) {
            $this->apiReturn(406, '个人或企业实名仅限中文汉字，请重新输入');
        }

        //手机号码验证
        if(!ismibile($mobile)) {
            $this->apiReturn(406, '手机号码不正确');
        }

        //密码验证
        $res    = chkPassword($pwd, $pwd);
        if($res!==1) {
            $this->apiReturn(406, $res);
        }

        

    }

    /**
     * 完善资料
     * @author dwer
     * @date   2016-05-18
     *
     */
    public function addInfo() {

    }

    /**
     * 手机短信验证码验证
     * @author dwer
     * @date   2016-05-19
     *
     * @return
     */
    private function _authVcode() {
        $clientVcode = I('post.vcode');
        $serverVcode = I('session.vcode');

        if(!$clientVcode || !$serverVcode) {
            return false;
        }

        if($clientVcode == $serverVcode) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 图形验证码的验证
     * @author dwer
     * @date   2016-05-19
     *
     * @return
     */
    private function _authImage() {
        $clientImageCode = I('post.auth_code');
        $serverImageCode = I('session.auth_code');

        if(!$serverImageCode || !$clientImageCode) {
            return false;
        }

        if($serverImageCode == $clientImageCode) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 授权验证
     * @author dwer
     * @date   2016-05-19
     *
     * @return
     */
    private function _auth() {
        $clientToken = I('post.token');
        $serverToken = I('session.token');

        if(!$serverToken || !$clientToken) {
            return false;
        }

        if($clientToken == $serverToken) {
            return true;
        } else {
            return false;
        }
    }

}
