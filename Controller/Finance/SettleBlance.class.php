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

    private $_moneyArr = [
        1, //按比例冻结
        2, //按具体的金额冻结
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
        $serviceFee   = floatval(I('post.service_fee'));
        $moneyType    = intval(I('post.money_type', 1));
        $moneyNumber  = floatval(I('post.money_number'));

        //参数合法性
        if(!$fid || !in_array($mode, $this->_modeArr) || !in_array($freezeType, $this->_freezeArr) || !in_array($accountNo, $this->_accountArr) || !in_array($moneyType, $this->_moneyArr) || ($serviceFee < 0 || $serviceFee > 100)) {
            $this->apiReturn(400, [], '参数错误');
        }
        
        //时间验证
        if($mode == 1) {
            //日结只要传closeTime
            if($closeTime <=0 || $closeTime > 23) {
                $this->apiReturn(400, [], '日结时间错误');
            }

            //日结这些时间都是一致的
            $closeDate = $transferDate = $transferTime = $closeTime;

        } else if($mode == 2) {
            //周结
            if($closeDate < 1 || $closeDate > 7) {
                $this->apiReturn(400, [], '周结日期错误');
            }

            if($closeTime <=0 || $closeTime > 23) {
                $this->apiReturn(400, [], '周结时间错误');
            }

            $transferDate = $closeDate;
            $transferTime = $closeTime;

        } else {
            //月结
            if($closeDate < 1 || $closeDate > 7) {
                $this->apiReturn(400, [], '月结清算日期错误');
            }

            if($closeTime <=0 || $closeTime > 23) {
                $this->apiReturn(400, [], '月结清算时间错误');
            }

            if($transferDate < 1 || $transferDate > 31) {
                $this->apiReturn(400, [], '月结清分日期错误');
            }

            if($transferTime <=0 || $transferTime > 23) {
                $this->apiReturn(400, [], '月结清分时间错误');
            }
        }

        //获取用户信息
        $memberModel = $this->model('Member/Member');
        $memberInfo = $memberModel->getMemberInfo($uid);

        //银行账户信息验证
        if(!$memberInfo) {
            $this->apiReturn(400, [], '用户不存在');
        }

        if($accountNo == 1) {
            $accountStr = $memberInfop['bank_account1'];
        } else {
            $accountStr = $memberInfop['bank_account2'];
        }

        if(!$accountStr) {
            $this->apiReturn(400, [], '银行账户信息错误');
        }

        $accountInfo = $this->_checkBankAccount($accountStr);
        if($checkRes == -1 || $res == -2) {
            $this->apiReturn(400, [], '银行账户信息错误');
        }

        //冻结资金配置
        if($freezeType == 2) {
            if($moneyNumber < 0 || $moneyNumber > 100) {
                $this->apiReturn(400, [], '冻结金额错误');
            }

            $freezeData = json_encode(['type' => $moneyType, 'value' => $moneyNumber]);
        } else {
            $freezeData = false;
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        if($updateId) {
            $res = $settleBlanceModel->updateSetting($updateId, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $this->_memberId, $accountInfo, $serviceFee, $freezeData);
        } else {
            $res = $settleBlanceModel->addSetting($fid, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $this->_memberId, $accountInfo, $serviceFee, $freezeData);
        }

        if($res) {
            $this->apiReturn(200, [], '数据添加成功');
        } else {
            $this->apiReturn(500, [], '数据添加失败');
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
            $checkRes = $this->_checkBankAccount($account1);

            if($checkRes == -1) {
                $res['account1']['status'] = 0;
                $res['account1']['msg']    = '银行账户信息缺失，请重新编辑';
            } else if($checkRes == -2){
                $res['account1']['status'] = 0;
                $res['account1']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
            } else {
                $res['account1'] = $checkRes;
                $res['account1']['status']        = 1;
                $res['account1']['msg']           = '';
            }
        }
        //获取具体账号信息 
        if($account2) {
            $checkRes = $this->_checkBankAccount($account2);

            if($checkRes == -1) {
                $res['account2']['status'] = 0;
                $res['account2']['msg']    = '银行账户信息缺失，请重新编辑';
            } else if($checkRes == -2){
                $res['account2']['status'] = 0;
                $res['account2']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
            } else {
                $res['account2'] = $checkRes;
                $res['account2']['status']        = 1;
                $res['account2']['msg']           = '';
            }
        }

        //返回账号信息
        $this->apiReturn(200, $res);
    }

    /**
     * 检测银行账户信息是不是可以自动转账
     * @author dwer
     * @date   2016-06-13
     *
     * @param  $accountStr 银行账户字符串 - 中国民生银行股份有限公司上海东门支行|305290002254|6212225554441699821|测试123757|1
     * @return
     */
    private function _checkBankAccount($accountStr) {
        if(!$accountStr) {
            return false;
        }

        $tmp = explode('|', $accountStr);
        if(count($tmp) < 5) {
            return -1;
        }

        //支行行号是12位的数字
        $insCode = $tmp['1'];
        if(!preg_match('/[0-9]{12}/', $insCode)) {
            return -2;
        }

        $res = [
            'bank_name'     => $tmp[0],
            'bank_ins_code' => $tmp[1],
            'bank_account'  => $tmp[2],
            'account_name'  => $tmp[3],
            'acc_type'      => $tmp[4],
        ];

        return $res;
    }
}