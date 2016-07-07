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
    private $_sid      = null;
    private $_logPath  = 'auto_withdraw/setting';

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
        $this->_sid = $this->isLogin('ajax');
        $memberID   = $_SESSION['memberID'];
        $qx         = $_SESSION['qx'];

        //只有几个方法可以给非管理员调用
        $method = I('get.a', '');
        $authMethodArr = ['getRecords', 'getFrozeSummary', 'getFrozeOrders'];

        if(!in_array($method, $authMethodArr)) {
            //角色判斷
            if(!($memberID == 1 || ($this->_sid == 1 && strpos($qx,'fees')))){
                $this->apiReturn(403, [], '没有权限');
            }
        }

        $this->_memberId = $memberID;
    }

    /**
     * 添加配置记录 
     * @author dwer
     *
     * @param $fid 用户ID
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freeze_type 资金冻结类型，1=冻结未使用的总额，2=按比例或是具体金额冻结
     * @param $close_date 结算日期，日结（几点），月结（几号），周结（周1-周7）
     * @param $close_time 结算时间，具体几点
     * @param $transfer_date 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $transfer_time 转账时间，具体几点
     * @param $account_no 银行账户的序号
     * @param $service_fee 提现手续费
     * @param $money_type 资金冻结详情类别：1=比例，2=具体金额
     * @param $money_value 资金冻结详情数值：具体比例或是具体金额
     * 
     * @date   2016-06-09
     *
     */
    public function add($updateId = false) {
        //参数过滤
        $fid          = intval(I('post.fid', false));
        $mode         = intval(I('post.mode'));
        $freezeType   = intval(I('post.freeze_type'));
        $closeDate    = intval(I('post.close_date'));
        $closeTime    = intval(I('post.close_time'));
        $transferDate = intval(I('post.transfer_date'));
        $transferTime = intval(I('post.transfer_time'));
        $accountNo    = intval(I('post.account_no'));
        $serviceFee   = floatval(I('post.service_fee'));
        $moneyType    = intval(I('post.money_type', 1));
        $moneyValue   = floatval(I('post.money_value'));

        //参数合法性
        if(!in_array($mode, $this->_modeArr) || !in_array($freezeType, $this->_freezeArr) || !in_array($accountNo, $this->_accountArr) || !in_array($moneyType, $this->_moneyArr) || ($serviceFee < 0 || $serviceFee > 100)) {
            $this->apiReturn(400, [], '参数错误');
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        if($updateId) {
            //如果是更新数据的话
            $info = $settleBlanceModel->getSettingInfo($updateId);
            if(!$info) {
                $this->apiReturn(400, [], '参数错误');
            }
            $fid = $info['fid'];
        } else {
            //新增数据
            if(!$fid) {
                $this->apiReturn(400, [], '参数错误');
            }
        }
        
        //时间验证
        if($mode == 1) {
            //日结只要传closeTime
            if($closeTime < 0 || $closeTime > 23) {
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
            if($closeDate < 1 || $closeDate > 31) {
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
        $memberInfo = $memberModel->getMemberInfo($fid);

        //银行账户信息验证
        if(!$memberInfo) {
            $this->apiReturn(400, [], '用户不存在');
        }

        if($accountNo == 1) {
            $accountStr = $memberInfo['bank_account1'];
        } else {
            $accountStr = $memberInfo['bank_account2'];
        }

        if(!$accountStr) {
            $this->apiReturn(400, [], '银行账户信息错误');
        }

        $accountInfo = $this->_checkBankAccount($accountStr);
        if($accountInfo == -1 || $accountInfo == -2) {
            $this->apiReturn(400, [], '银行账户信息错误');
        }

        //记录选择的是哪个账号
        $accountInfo['select_no'] = $accountNo;

        //冻结资金配置
        if($freezeType == 2) {
            //如果是百分比就要进行判断
            if($moneyType == 1) {
                if($moneyValue < 0 || $moneyValue > 100) {
                    $this->apiReturn(400, [], '冻结金额错误');
                }
            }

            $freezeData = ['type' => $moneyType, 'value' => $moneyValue];
        } else {
            $freezeData = false;
        }

        if($updateId) {
            $res = $settleBlanceModel->updateSetting($updateId, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $this->_memberId, $accountInfo, $serviceFee, $freezeData);
        } else {
            $res = $settleBlanceModel->addSetting($fid, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $this->_memberId, $accountInfo, $serviceFee, $freezeData);
        }

        if($res) {
            //写日志
            $logData = [
                'updateId'     => $updateId,
                'fid'          => $fid,
                'mode'         => $mode,
                'freezeType'   => $freezeType,
                'closeDate'    => $closeDate,
                'closeTime'    => $closeTime,
                'transferDate' => $transferDate,
                'transferTime' => $transferTime,
                'memberId'     => $this->_memberId,
                'accountInfo'  => $accountInfo,
                'serviceFee'   => $serviceFee,
                'freezeData'   => $freezeData,
            ];
            pft_log($this->_logPath, json_encode($logData));

            $this->apiReturn(200);
        } else {
            $this->apiReturn(500, [], '服务器错误');
        }
    }

    /**
     * 修改配置记录
     * @author dwer
     *
     * @param $id 记录ID
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freeze_type 资金冻结类型，1=冻结未使用的总额，2=按比例冻结
     * @param $close_date 结算日期，日结（几点），月结（几号），周结（周1-周7）
     * @param $close_time 结算时间，具体几点
     * @param $transfer_date 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $transfer_time 转账时间，具体几点
     * @param $account_no 银行账户的序号
     * @param $service_fee 提现手续费
     * @param $money_type 资金冻结详情类别：1=比例，2=具体金额
     * @param $money_value 资金冻结详情数值：具体比例或是具体金额
     * 
     * @date   2016-06-09
     *
     */
    public function udpate() {
        $updateId = intval(I('post.id'));
        //参数合法性
        if(!$updateId) {
            $this->apiReturn(400, [], '参数错误');
        }

        $this->add($updateId);
    }

    /**
     * 获取某条配置的具体信息
     * @author dwer
     *
     * @param $id 记录ID
     *      
     * @date   2016-06-09
     *
     */
    public function getSettingInfo() {
        $updateId = intval(I('post.id'));
        //参数合法性
        if(!$updateId) {
            $this->apiReturn(400, [], '参数错误');
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $info = $settleBlanceModel->getSettingInfo($updateId);
        if(!$info) {
            $this->apiReturn(400, [], '获取不到记录信息');
        }

        $res = $info;
        $freezeData  = $res['freeze_data'];
        $accountInfo = $res['account_info'];
        unset($res['circle_mark'], $res['update_uid'], $res['freeze_data'], $res['account_info']);

        if($res['mode'] == 1) {
            unset($res['close_date'], $res['transfer_date'], $res['transfer_time']);
        } else if($res['mode'] == 2) {
            unset($res['transfer_date'], $res['transfer_time']);
        }

        $accountInfo = json_decode($accountInfo, true);
        $res['account_no'] = $accountInfo['select_no'];

        if($res['freeze_type'] == 2) {
            $tmp = json_decode($freezeData, true);
            $res['money_type']  = $tmp['type'];
            $res['money_value'] = $tmp['value'];
        }

        //获取上次清分日期
        $lastInfo = $settleBlanceModel->getLastTransferInfo($info['fid']);
        if($lastInfo) {
            $res['last_settle_time'] = $lastInfo['transfer_time'];
        } else {
            $res['last_settle_time'] = 0;
        }

        $res['status'] = $res['status'] == 1 ? 'on' : 'off';

        $this->apiReturn(200, $res);
    }

    /**
     * 设置配置的状态
     * @author dwer
     *
     * @param $id 记录ID
     * @param $status 状态 off=无效，on=有效
     * 
     * @date   2016-06-09
     *
     */
    public function setStatus() {
        $updateId = intval(I('post.id'));
        $status   = strval(I('post.status'));

        //参数合法性
        if(!$updateId || !in_array($status, ['on', 'off'])) {
            $this->apiReturn(400, [], '参数错误');
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $info = $settleBlanceModel->getSettingInfo($updateId);
        if(!$info) {
            $this->apiReturn(400, [], '获取不到记录信息');
        }

        if(($status == 'on' && $info['status'] == 1) || ($status == 'off' && $info['status'] == 0)) {
            //不需要调整了
            $this->apiReturn(200);
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $res = $settleBlanceModel->settingStatus($updateId, $this->_memberId, $status);

        if($res) {
            //写日志
            pft_log($this->_logPath, json_encode(['id' => $updateId, 'status' => $status]));

            $this->apiReturn(200);
        } else {
            $this->apiReturn(500, [], '服务器错误');
        }
    }

    /**
     * 获取常用的银行账户
     * @author dwer
     *
     * @param $fid 账号ID
     * 
     * @date   2016-06-12
     *
     * @return
     */ 
    public function getAccounts() {
        $uid = intval(I('post.fid'));
        if(!$uid) {
            $this->apiReturn(400, [], '参数错误');
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
            '1' => [
                'status' => 0,
                'msg'    => '银行账户信息不存在'
            ],
            '2' => [
                'status' => 0,
                'msg'    => '银行账户信息不存在'
            ],
        ];

        //获取具体账号信息 - 账号1
        if($account1) {
            $checkRes = $this->_checkBankAccount($account1);

            if($checkRes == -1) {
                $res['1']['status'] = 0;
                $res['1']['msg']    = '银行账户信息缺失，请重新编辑';
            } else if($checkRes == -2){
                $res['1']['status'] = 0;
                $res['1']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
            } else {
                $res['1'] = $checkRes;
                $res['1']['status']        = 1;
                $res['1']['msg']           = '';
            }
        }
        //获取具体账号信息 - 账号2
        if($account2) {
            $checkRes = $this->_checkBankAccount($account2);

            if($checkRes == -1) {
                $res['2']['status'] = 0;
                $res['2']['msg']    = '银行账户信息缺失，请重新编辑';
            } else if($checkRes == -2){
                $res['2']['status'] = 0;
                $res['2']['msg']    = '现代化支付系统行号錯誤，请重新编辑';
            } else {
                $res['2'] = $checkRes;
                $res['2']['status']        = 1;
                $res['2']['msg']           = '';
            }
        }

        //同时在这里返回默认的提现配置
        $defaultConf = load_config('withdraw_default');
        unset($defaultConf['day']['limit_money'], $defaultConf['week']['limit_money'], $defaultConf['month']['limit_money']);

        $data = [
            'default_config' => $defaultConf,
            'account_info'   => $res
        ];

        //返回账号信息
        $this->apiReturn(200, $data);
    }

    /**
     * 获取具体的转账记录
     * @author dwer
     * @date   2016-06-21
     *
     * @return  
     */
    public function getRecords() {
        $page = intval(I('post.page'));
        $size = intval(I('post.size', 20));

        //是管理员才能传参数
        if($this->_sid == 1) {
            $fid  = intval(I('post.fid', '')) ? intval(I('post.fid', '')) : $this->_sid;
        } else {
            $fid = $this->_sid;
        }

        if(!$fid) {
            $this->apiReturn(400, [], '参数错误');
        }

        $page = max($page, 1);
        $size = $size < 0 ? 20 : ($size > 100 ? $size : $size);

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $tmp = $settleBlanceModel->getRecords($fid, $page, $size);

        $count = $tmp['count'];
        $list  = $tmp['list'];

        //获取总页数
        $totalPage = ceil($count / $size);

        $res = ['count' => $count, 'page' => $page, 'total_page' => $totalPage, 'list' => []];
        foreach($list as $item) {
            //获取状态
            if($item['status'] != 0) {
                //清算或是清分出现问题,具体见备注
                $status = 4;
            } else {
                if($item['is_settle'] == 0) {
                    //待清算
                    $status = 2;
                } else if($item['is_transfer'] == 0) {
                    //清算,待转账
                    $status = 3;
                } else {
                    //清分成功
                    $status = 1;
                }
            }

            $frozeData  = @json_decode($item['froze_data'], true);
            if(is_array($frozeData)) {
                $freezeType = $frozeData['freeze_type'] == 1 ? 0 : $frozeData['type'];
            } else {
                $freezeType = 0;
            }

            $res['list'][] = [
                'fid'            => $item['fid'],
                'settle_time'    => $item['settle_time'],
                'transfer_time'  => $item['transfer_time'],
                'status'         => $status,
                'freeze_money'   => $item['freeze_money'],
                'transfer_money' => $item['transfer_money'],
                'settle_remark'  => $item['remark'],
                'trans_remark'   => $item['trans_remark'],
                'update_time'    => $item['update_time'],
                'mode'           => $item['mode'],
                'cycle_mark'     => $item['cycle_mark'],
                'freeze_type'    => $freezeType
            ];
        }

        $this->apiReturn(200, $res);
    }

    /**
     * 获取冻结订单汇总信息
     * @author dwer
     * @date   2016-06-21
     *
     * @return  
     */
    public function getFrozeSummary() {
        $mode = intval(I('post.mode'));
        $mark = intval(I('post.cycle_mark'));

        if(!$mode || !$mark) {
            $this->apiReturn(400, [], '参数错误');
        }

        //是管理员才能传参数
        if($this->_sid == 1) {
            $fid  = intval(I('post.fid', '')) ? intval(I('post.fid', '')) : $this->_sid;
        } else {
            $fid = $this->_sid;
        }

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $tmp = $settleBlanceModel->getFrozeOrders($fid, $mode, $mark, true);

        if($tmp === false) {
            $this->apiReturn(500, [], '系统错误');
        } else {
            //['orders' : 100, 'tickets' : 200, 'money' : 14000]
            $this->apiReturn(200, $tmp);
        }
    }

    /**
     * 获取冻结订单汇总信息
     * @author dwer
     * @date   2016-06-21
     *
     * @return
     * {
     *      'count' : 27,
     *      'list'  : [{
     *          ltitle : '【测试】没那么简单',
     *          ttitle : '成人测试测试票',
     *          ordernum : '3316099',
     *          money : '200',
     *          tickets : '2',
     *      }]
     * }
     */
    public function getFrozeOrders() {
        $mode = intval(I('post.mode'));
        $mark = intval(I('post.cycle_mark'));
        $page = intval(I('post.page', 1));
        $size = intval(I('post.size', 20));

        if(!$mode || !$mark) {
            $this->apiReturn(400, [], '参数错误');
        }

        //是管理员才能传参数
        if($this->_sid == 1) {
            $fid  = intval(I('post.fid', '')) ? intval(I('post.fid', '')) : $this->_sid;
        } else {
            $fid = $this->_sid;
        }

        $page = max($page, 1);
        $size = $size < 0 ? 20 : ($size > 100 ? $size : $size);

        $settleBlanceModel = $this->model('Finance/SettleBlance');
        $res = $settleBlanceModel->getFrozeOrdersInfo($fid, $mode, $mark, $page, $size);

        //获取总页数
        $totalPage = ceil($res['count'] / $size);

        //添加页数等信息
        $res['total_page'] = $totalPage;
        $res['page']       = $page;

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