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
            $this->apiReturn(403, [], '非法请求');
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
            $this->apiReturn(406, [], '参数错误');
        }

        //手机号码验证
        if(!ismobile($mobile)) {
            $this->apiReturn(406, [], '手机号码不正确');
        }

        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        //判断手机号码是不是已经注册了
        $res = $memModel->_chkPassport($mobile);

        if($res) {
            //已经注册
            $this->apiReturn(200, array('is_register' => 1), '已经注册');
        } else {
            $this->apiReturn(200, array('is_register' => 0), '没有注册');
        }
    }

    /**
     * 发送注冊短信验证码
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function regVcode() {
        $mobile = I('post.mobile');

        if(!$mobile) {
            $this->apiReturn(406, [], '参数错误');
        }   

        //手机号码验证
        if(!ismobile($mobile)) {
            $this->apiReturn(406, [], '手机号码不正确');
        }

        //图形验证码验证
        $isLegal = $this->_authImage();
        if(!$isLegal) {
            $this->apiReturn(403, [], '图形验证码错误');
        }

        $blackList   = load_config('black_list');
        $blackMobile = $blackList['mobile'];
        if (in_array($mobile, $blackMobile)) {
            $this->apiReturn(403, [], '该手机号已经被加入黑名单。');
        }

        $cacheRedis = Cache::getInstance('redis');
        $ip         = ip();
        $cacheKey   = "mobile:$ip:$mobile";
        $send_time  = $cacheRedis->get($cacheKey,'', true);
        if ($send_time > 8) {
            $this->apiReturn(403, [], '该手机号发送次数超出系统限制。');
        }

        //判断手机号码是不是已经注册了
        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        //判断手机号码是不是已经注册了
        $res = $memModel->_chkPassport($mobile);
        if($res) {
            $this->apiReturn(403, [],'该手机号用户已注册票付通会员，请尝试更换其它号码，若有疑问请联系我们！');
        }

        //获取短信模板
        $smsConfig = load_config('sms');
        $registerTpl = $smsConfig['register'];
        if(!$registerTpl) {
            $this->apiReturn(403, [], '短信模板不存在！');
        }

        //发送短信
        $soap = Helpers::GetSoapInside();
        $res  = MemberAccount::SendVerifyCode($mobile, $registerTpl, $soap, false);

        if($res == 100) {
            $res = $cacheRedis->incrBy($cacheKey);
            $this->apiReturn(200, [], '发送验证码成功');

        } else if($res == -1){
            $this->apiReturn(403, [], '发送间隔太短！请在120秒后再重试。');
        } else {
            $this->apiReturn(500, [], '对不起，短信服务器发生故障，造成的不便我们感到十分抱歉。请联系我们客服人员。');
        }
    }


    /**
     * 发送注册短信验证码
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function resetVcode() {
        $passport = I('post.passport');

        if(!$passport) {
            $this->apiReturn(406, [], '参数错误');
        }

        //判断是手机号还是账号
        if(strlen($passport) < 11) {
            //账号，获取手机号码
            $db = Helpers::getPrevDb();
            Helpers::loadPrevClass('MemberAccount');
            $memModel = new MemberAccount($db);

            $mobile = $memModel->chkPassport($passport);
            if(!$mobile) {
                $this->apiReturn(406, [], '手机号码不正确');
            }

        } else {
            $mobile = $passport;

            //手机号码验证
            if(!ismobile($mobile)) {
                $this->apiReturn(406, [], '手机号码不正确');
            }
        }

        //图形验证码验证
        $isLegal = $this->_authImage();

        if(!$isLegal) {
            $this->apiReturn(403, [], '图形验证码错误');
        }

        $blackList   = load_config('black_list');
        $blackMobile = $blackList['mobile'];
        if (in_array($mobile, $blackMobile)) {
            $this->apiReturn(403, [], '该手机号已经被加入黑名单。');
        }

        $cacheRedis = Cache::getInstance('redis');
        $ip         = ip();
        $cacheKey   = "mobile:$ip:$mobile";
        $send_time  = $cacheRedis->get($cacheKey,'', true);
        if ($send_time > 8) {
            $this->apiReturn(403, [], '该手机号发送次数超出系统限制。');
        }

        //判断手机号码是不是已经注册了
        if(!isset($memModel) || !$memModel) {
            $db = Helpers::getPrevDb();
            Helpers::loadPrevClass('MemberAccount');
            $memModel = new MemberAccount($db);
        }

        //判断手机号码是不是已经注册了
        $res = $memModel->_chkPassport($mobile);
        if(!$res) {
            $this->apiReturn(403, [], '该手机号还没有注册');
        }

        //获取短信模板
        $smsConfig = load_config('sms');
        $forgetTpl = $smsConfig['forget'];
        if(!$forgetTpl) {
            $this->apiReturn(403, [], '短信模板不存在！');
        }

        //发送短信
        $soap = Helpers::GetSoapInside();
        $res  = MemberAccount::SendVerifyCode($mobile, $forgetTpl, $soap, false);

         if($res == 100) {
            $res = $cacheRedis->incrBy($cacheKey);

            //记录发送验证码的手机号
            $_SESSION['reset_mobile'] = $mobile;
            $this->apiReturn(200, [], '发送验证码成功');

        } else if($res == -1){
            $this->apiReturn(403, [], '发送间隔太短！请在120秒后再重试。');
        } else {
            $this->apiReturn(500, [], '对不起，短信服务器发生故障，造成的不便我们感到十分抱歉。请联系我们客服人员。');
        }
    }

    /**
     * 检测验证码
     * @author dwer
     * @date   2016-05-20
     *
     * @return
     */
    public function checkVcode() {
        $mobile = I('session.reset_mobile');
        $vcode  = I('post.vcode');

        if(!$vcode || !$mobile) {
            $this->apiReturn(406, [], '参数错误');
        }

        //手机号码验证
        if(!ismobile($mobile)) {
            $this->apiReturn(406, [], '手机号码不正确');
        }

        //验证
        Helpers::loadPrevClass('MemberAccount');

        //手机验证码验证
        $isLegal = MemberAccount::ChkVerifyCode($mobile, $vcode);
        if($isLegal !== true) {
            $this->apiReturn(406, [], '身份验证失败');
        }

        //将验证成功的数据写入
        $data = array('mobile' => $mobile, 'time' => time());
        $_SESSION['reset_data'] = $data;

        $this->apiReturn(200, [], '身份验证成功');
    }

    /**
     * 重置密码
     * @author dwer
     * @date   2016-05-20
     *
     * @return
     */
    public function resetPwd() {
        $mobile = I('session.reset_mobile');
        $pass1  = I('post.pass1');
        $pass2  = I('post.pass2');

        if(!$mobile || !$pass1 || !$pass2) {
            $this->apiReturn(406, [], '参数错误');
        }

        //进行身份认证
        $resetData = $_SESSION['reset_data'];
        if(!$resetData || !$resetData['mobile']) {
            $this->apiReturn(406, [], '请先进行身份验证');
        }

        $authMobile = $resetData['mobile'];
        $authTime   = $resetData['time'];

        if($authMobile != $mobile) {
            $this->apiReturn(406, [], '请先进行身份验证');
        }

        $diff = time() - $authTime;
        if($diff > 30 * 60) {
            $this->apiReturn(406, [], '请重新进行身份验证');
        }

        //判断手机号码是不是已经注册了
        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        $res = chkPassword($pass1, $pass2);
        if($res != 1) {
             $this->apiReturn(406, [], '密码格式错误：' . $res);
        }

        $res = $memModel->resetPassword($authMobile, $pass1);
        if($res) {
             unset( $_SESSION['reset_data']);

            $this->apiReturn(200, [], '密码重置成功');
        } else {
            $this->apiReturn(500, [], '密码重置失败');
        }
    }

    /**

     * 注册保存账号等信息
     * @author dwer
     * @date   2016-05-18
     *
     * @return
     */
    public function account() {
        //参数验证
        $company = I('post.company');
        $mobile  = I('post.mobile');
        $pwd     = I('post.pwd');
        $vcode   = I('post.vcode');
        $dtype   = I('post.dtype');

        if(!$company || !$mobile ||!$pwd ||!$vcode || !in_array($dtype, array(0, 1))) {
            $this->apiReturn(406, [], '参数错误。');
        }

        //手机号码验证
        if(!ismobile($mobile)) {
            $this->apiReturn(406, [], '手机号码不正确');
        }

        //密码验证
        $res    = chkPassword($pwd, $pwd);
        if($res!==1) {
            $this->apiReturn(406, [], '密码格式错误：' . $res);
        }

        $status = $dtype == 0 ? 3 : 0;

        //进行注册
        Helpers::loadPrevClass('MemberAccount');

        //手机验证码验证
        $isLegal = MemberAccount::ChkVerifyCode($mobile, $vcode);
        if($isLegal !== true) {
            $this->apiReturn(406, [], '验证码错误！');
        }

        //个人/企业实名验证
        $company = safetxt($company);
        if(!is_chinese($company)) {
            $this->apiReturn(406, [], '个人或企业实名仅限中文汉字，请重新输入');
        }

        //注册参数整理
        $data = array(
            'dname'    => $company,
            'mobile'   => $mobile,
            'password' => md5(md5($pwd)),
            'dtype'    => $dtype,
            'status'   => $status
        );

        $db         = Helpers::getPrevDb();
        $memModel   =  new MemberAccount($db);

        //获取介绍人
        $inviterID = $memModel->getInviterId(1);
        if($inviterID) {
            $data['inviterID'] = $inviterID;
        }

        //扩展数据
        $extData = array('com_name' => $company);
        $res = $memModel->register($data, $extData);

        if($res['status'] == 'fail') {
            $msg = $res['msg'];
            $this->apiReturn(406, [], $msg);
        } else {
            $body = $res['body'];
            $tmp = explode('|', $body);
            $account = $tmp[1];

            //在session里面记录第一步注册的数据
            $tmpData = array('account' => $account, 'time' => time(), 'dtype' => $dtype);
            $_SESSION['reg_data'] = $tmpData;

            $this->apiReturn(200, array('account' => $account), '注册成功');
        }
    }

    /**
     * 完善资料
     * @author dwer
     * @date   2016-05-18
     *
     */
    public function accountInfo() {
        $nickname = safetxt(I('post.nickname'));
        $companyType  = safetxt(I('post.company_type'));

        if(!$nickname || !$companyType) {
            $this->apiReturn(406, [], '参数错误');
        }

        $typeArr = array(
            0 => array('景区', '酒店','旅行社','其他'),
            1 => array('加盟门店', '电商','团购网','旅行社','淘宝/天猫','其他')
        );

        if(!is_chinese($nickname)) {
            $this->apiReturn(406, [], '姓名仅限中文汉字，请重新输入');
        }

        $province = intval(I('post.province'));
        $city     = intval(I('post.city'));
        $address  = safetxt(I('post.address'));
        $business = safetxt(I('post.business'));

        $regData = I('session.reg_data');
        if(!$regData || !isset($regData['account'])) {
            $this->apiReturn(406, [], '请先注册');
        }

        $account = $regData['account'];
        $dtype   = $regData['dtype'];
        $regTime = $regData['time'];

        //参数验证
        $typeTmp = $typeArr[$dtype];
        if(!in_array($companyType, $typeTmp)) {
            $this->apiReturn(406, [], '公司类型错误');
        }

        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $memModel = new MemberAccount($db);

        $id = $memModel->getIdByAccount($account);
        if(!$id) {
            $this->apiReturn(500, [], '完善资料失败');
        }

        $data    = array(
            'cname' => $nickname
        );

        $extData = array(
            'com_type' => $companyType
        );

        if($province) {
            $extData['province'] = $province;
        }

        if($city) {
            $extData['city'] = $city;
        }

        if($address) {
            $data['address'] = $address;
        }

        if($business) {
            $extData['business'] = $business;
        }

        //修改
        $res = $memModel->update($id, $data, $extData);
        if($res['status'] == 'ok') {
            unset($_SESSION['reg_data']);

            $this->apiReturn(200, array('account' => $account), '更新成功');
        } else {
            $msg = $res['msg'];
            $this->apiReturn(500, $msg);
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

        if(strtolower($serverImageCode) == strtolower($clientImageCode)) {
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
