<?php
/**
 * 用户余额清分后台配置控制器
 * 清分模型：1=日结，2=周结，3=月结
 * 资金冻结类型：1=冻结未使用的总额，2=按比例或是具体数额冻结
 * 
 *
 * @author dwer
 * @date 2016-01-20 
 * 
 */

namespace Controller\Finance;

use Library\Controller;
use Model\Member\Member;

class SettleBlance extends Controller {
    private $_memberId = null;

    private $_modeArr = [
        1, // 日结
        2, // 周结
        3, // 月结
    ];

    private $_freezeArr = [
        1, // 冻结未使用的总额
        2, // 按比例冻结
    ];

    private $_accountArr = [
        1, //银行账户1
        2, //银行账号2
    ]; 

    public function __construct() {
        //只有管理员才能进行操作
        $tmpId = 1;//$this->isLogin('ajax');

        //角色判斷
        

        $this->_memberId = $tmpId;
    }

    /**
     * 添加配置记录 
     * @author dwer
     * @date   2016-06-09
     *
     */
    public function add($updateId) {
        //参数过滤
        $fid          = intval(I('post.fid'));
        $mode         = intval(I('post.mode'));
        $freezeType   = intval(I('post.freeze_type'));
        $closeDate    = intval(I('post.close_date'));
        $closeTime    = intval(I('post.close_ime'));
        $transferDate = intval(I('post.transfer_date'));
        $transferTime = intval(I('post.transfer_time'));
        $accountNo    = intval(I('post.account_no'));

        //参数合法性
        if(!$fid || !in_array($mode, $this->_modeArr) || !in_array($freezeType, $this->_freezeArr) || !in_array($accountNo, $this->_accountArr)) {
            $this->apiReturn(401, [], '');
        }
        
        //时间验证
        if($mode == 1) {

        } else if($mode == 2) {

        } else {

        }

        //获取用户信息
        $memberModel = $this->model('Member/Member');
        $memberInfo = $memberModel->getMemberInfo($uid);

        //银行账户信息验证


        //冻结资金配置
        if($freezeType == 2) {

        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        if($updateUid) {
            $res = $settleBlanceModel->updateSetting($updateUid, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo,$freezeData);
        } else {
            $res = $settleBlanceModel->addSetting($fid, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo,$freezeData);
        }

        if($res) {
            $this->apiReturn(200, [], '');
        } else {
            $this->apiReturn(500, [], '');
        }
    }

    /**
     * 修改配置记录 
     * @author dwer
     * @date   2016-06-09
     *
     */
    public function udpate() {
        $updateId = intval(I('post.id'));
        //参数合法性
        if(!$updateId) {
            $this->apiReturn(401, [], '');
        }

        $this->add($updateId);
    }

    /**
     * 获取某条配置的具体信息
     * @author dwer
     * @date   2016-06-09
     *
     */
    public function getSettingInfo() {
        $updateId = intval(I('post.id'));
        //参数合法性
        if(!$updateId) {
            $this->apiReturn(401, [], '');
        }


    }

    /**
     * 获取某条配置的列表
     * @author dwer
     * @date   2016-06-09
     *
     */
    public function getSettingList() {

    }

    /**
     * 设置配置的状态
     * @author dwer
     * @date   2016-06-09
     *
     */
    public function setStatus() {
        
    }

    /**
     * 获取常用的银行账户
     * @author dwer
     * @date   2016-06-12
     *
     * @return
     */ 
    public function getAccounts() {
        $uid = intval(I('post.uid'));
        if(!$uid) {
            $this->apiReturn(401, [], '');
        }

        $memberModel = $this->model('Member/Member');
        $memberInfo = $memberModel->getMemberInfo($uid);
        if(!$memberInfo) {
            $this->apiReturn(402, [], '');
        }

        $account1 = $memberInfo['bank_account1'];
        $account2 = $memberInfo['bank_account2'];

        $accountArr1 = [];
        $accountArr2 = [];

        $res = [
            'account1' => [
                'status' => 0,
                'msg'    => '银行账户信息不存在'
            ],
            'account2' => [
                'status' => 0,
                'msg'    => '银行账户信息不存在'
            ],
        ];

        //获取具体账号信息
        if($account1) {
            $tmp = explode('|', $account1);

            if(count($tmp) < 5) {
                $res['account1']['status'] = 0;
                $res['account1']['msg']    = '银行账户信息缺失，请重新编辑';
            } else {
                $insCode = $tmp['1'];
                if(!preg_match('/[0-9]{12}/', $insCode)) {
                    $res['account1']['status'] = 0;
                    $res['account1']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
                } else {
                    $res['account1']['status']        = 1;
                    $res['account1']['msg']           = '';
                    $res['account1']['bank_name']     = $tmp[0];
                    $res['account1']['bank_name']     = $tmp[0];
                    $res['account1']['bank_ins_code'] = $tmp[1];
                    $res['account1']['bank_account']  = $tmp[2];
                    $res['account1']['account_name']  = $tmp[3];
                    $res['account1']['accType']       = $tmp[4];
                }
            }
        }

        //获取具体账号信息 
        if($account2) {
            $tmp = explode('|', $account2);

            if(count($tmp) < 5) {
                $res['account2']['status'] = 0;
                $res['account2']['msg']    = '银行账户信息缺失，请重新编辑';
            } else {
                $insCode = $tmp['1'];
                if(!preg_match('/[0-9]{12}/', $insCode)) {
                    $res['account2']['status'] = 0;
                    $res['account2']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
                } else {
                    $res['account2']['status']        = 1;
                    $res['account2']['msg']           = '';
                    $res['account2']['bank_name']     = $tmp[0];
                    $res['account2']['bank_name']     = $tmp[0];
                    $res['account2']['bank_ins_code'] = $tmp[1];
                    $res['account2']['bank_account']  = $tmp[2];
                    $res['account2']['account_name']  = $tmp[3];
                    $res['account2']['accType']       = $tmp[4];
                }
            }
        }

        //返回账号信息
        $this->apiReturn(200, $res);
    }
}