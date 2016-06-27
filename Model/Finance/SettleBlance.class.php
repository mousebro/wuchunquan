<?php
/**
 * 用户余额清分模型
 * 清分模型：1=日结，2=周结，3=月结
 * 资金冻结类型：1=冻结未使用的总额，2=按比例或是具体数额冻结
 * 
 *
 * @author dwer
 * @date 2016-01-20 
 * 
 */
namespace Model\Finance;
use Library\Model;
use Model\Member\Member as Member;
use Model\Finance\Withdraws as Withdraws;

class SettleBlance extends Model{
    //自动清分配置表
    private $_settingTable = 'pft_auto_withdraw_setting';
    //清分记录表
    private $_recordTable = 'pft_auto_withdraw_record';

    //订单表
    private $_orderTable = 'uu_ss_order';

    /**
     * 添加自动清分配置
     * @author dwer
     * @date   2016-06-08
     *
     * @param $fid 用户ID
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freezeType 资金冻结类型，1=冻结未使用的总额，2=按比例冻结
     * @param $closeDate 结算日期，日结（几点），月结（几号），周结（周1-周7）
     * @param $closeTime 结算时间，具体几点
     * @param $transferDate 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $transferTime 转账时间，具体几点
     * @param $updateUid 配置修改用户ID
     * @param $accountInfo 账号的数组 {"bank_name":"","bank_ins_code":"","bank_account":"","account_name":"","acc_type":"","select_no":}',
     * @param $serviceFee 提现手续费
     * @param $freezeData 资金冻结详情，比例或是具体的金额 - {"type":"1/2","value":"30"}
     *
     * @return bool
     */
    public function addSetting($fid, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee,
            $freezeData = false) {

        if(!$fid) {
            return false;
        }

        //参数统一校验，格式化
        $data = $this->_formatParam($mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee, 
            $freezeData);

        $data['fid'] = $fid;

        if(!$data) {
            return false;
        }
        
        $res = $this->table($this->_settingTable)->add($data);

        return $res === false ? false : true;
    }

    /**
     * 更新自动清分配置
     * @author dwer
     * @date   2016-06-08
     *
     * @param $id 记录ID
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freezeType 资金冻结类型，1=冻结未使用的总额，2=按比例冻结
     * @param $closeDate 结算日期，日结（几点），月结（几号），周结（周1-周7）
     * @param $closeTime 结算时间，具体几点
     * @param $transferDate 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $transferTime 转账时间，具体几点
     * @param $updateUid 配置修改用户ID
     * @param $accountInfo '账号的数组 {"bank_name":"","bank_ins_code":"","bank_account":"","acc_type":""}',
     * @param $freezeData 资金冻结详情，比例或是具体的金额 - {"type":"1/2","value":"30"}
     *
     * @return bool
     */
    public function updateSetting($id, $mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee, $freezeData = false) {
        if(!$id) {
            return false;
        }

        //参数统一校验，格式化
        $data = $this->_formatParam($mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee, $freezeData);
        if(!$data) {
            return false;
        }

        $res = $this->table($this->_settingTable)->where(['id' => $id])->save($data);
        return $res === false ? false : true;
    }

    /**
     * 更改配置的状态
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $updateUid 更新用户UID
     * @param  $status 状态 off=无效，on=有效
     * @return
     */
    public function settingStatus($id, $updateUid, $status = 'off') {
        if(!$id || !$updateUid || !in_array($status, ['on', 'off'])) {
            return false;
        }

        $where = ['id' => $id];
        $data = [
            'status'      => ($status == 'on' ? 1 : 0),
            'update_uid'  => $updateUid,
            'update_time' => time()
        ];

        $res = $this->table($this->_settingTable)->where($where)->save($data);
        return $res === false ? false : true;
    }

    /**
     * 获取清分配置列表
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $page 第几页
     * @param  $size 条目数
     * @param  $fid 用户ID 如果有搜索用户的话，传ID
     * @param  $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param  $circleMark 上次更新的周期标识，日（20160612）-月（20160104）-周（20160238）
     * @return
     */
    public function getSettingList($page, $size, $fid = false, $mode = false, $circleMark = false) {
        $page = intval($page);
        $size = intval($size);

        $where = ['status' => 1];
        if($fid) {
            $where['fid'] = intval($fid);
        }

        if($mode) {
            $where['mode'] = intval($mode);
        }

        if($circleMark) {
            $where['cycle_mark'] = ['LT', intval($circleMark)];
        }
        $field = 'id, fid, mode, close_time, close_date, transfer_time, transfer_date';
        $order = 'update_time desc';

        $res = $this->table($this->_settingTable)->field($field)->order($order)->where($where)->page($page . ',' . $size)->select();

        return $res === false ? [] : $res;
    }

    /**
     * 获取具体的配置信息
     * @author dwer
     * @date   2016-06-13
     *
     * @param  $id 记录ID
     * @return 
     */
    public function getSettingInfo($id) {
        if(!$id) {
            return false;
        }

        $info = $this->table($this->_settingTable)->where(['id' => $id])->find();
        return $info;
    }

    /**
     * 获取最近一次的清分数据
     * @author dwer
     * @date   2016-06-13
     *
     * @param  $fid
     * @return
     */
    public function getLastTransferInfo($fid) {
        if(!$fid) {
            return false;
        }

        $where = [
            'is_transfer' => 1,
            'status'      => 0,
            'fid'         => $fid
        ];
        $order = 'transfer_time desc';

        $info = $this->table($this->_recordTable)->where($where)->order($order)->find();
        return $info;
    }

    /**
     * 批量获取提现配置
     * @author dwer
     * @date   2016-06-13
     *
     * @param  $fidArr 用户ID数组
     * @return
     */
    public function getSettingByFids($fidArr) {
        if(!$fidArr || !is_array($fidArr)) {
            return false;
        }

        $fidArr = array_filter($fidArr);

        $field = '';
        $where = [
            'fid' => ['in', $fidArr]
        ];

        $tmp = $this->table($this->_settingTable)->where($where)->field($field)->select();

        $res = [];
        foreach($tmp as $item) {
            $res[$item['fid']] = [
                                    'id'   => $item['id'],
                                    'mode' => $item['mode'],
                                    'service_fee' => $item['service_fee'], 
                                    'status' => $item['status'] == 1 ? 'on' : 'off'
                                ];
        }

        return $res;
    }

    /**
     * 根据参数获取具体清分和清算的时间
     * @author dwer
     * @date   2016-06-14
     *
     * @param  $mode 模式 1=日结，2=周结，3=月结
     * @param  $settleTime 清算时间
     * @param  $settleDate 清算日期
     * @param  $transferTime 清分时间
     * @param  $transferDate 清分日期
     * @return
     */
    public function createSettleTime($mode, $circleMark, $settleTime, $settleDate = false, $transferTime = false, $transferDate = false) {
        //时间处理
        $year      = substr($circleMark, 0, 4);
        $twoMark   = substr($circleMark, 4, 2); //日结的时候是月,周结的时候是固定的'02',月结的时候是固定的'01'
        $threeMark = substr($circleMark, 6, 2); //日结的时候是日，周结的时候是第几周,月结的时候具体几月份

        if($mode == 1) {
            //日结
            $settleTime = intval($settleTime);
            $settleTime = ($settleTime < 0 || $settleTime > 23) ? 1 : $settleTime;

            //2016-10-23 23:00:00
            $tmpTime = $year . '-' . $twoMark . '-' . $threeMark  . " {$settleTime}:00:00";

            $resSettleTime      = $tmpTime;
            $resTransferTime    = $tmpTime;

        } else if($mode == 2){
            //周结

            //时间处理
            $settleTime = intval($settleTime);
            $settleDate = intval($settleDate);
            $settleTime = ($settleTime < 0 || $settleTime > 23) ? 1 : $settleTime;

            //日期处理
            $needDay = $this->_getDateFromWeekNum($year, $threeMark, $settleDate);
            $tmpTime = $needDay  . " {$settleTime}:00:00";

            $resSettleTime      = $tmpTime;
            $resTransferTime    = $tmpTime;

        } else {
            //月结

            //时间处理
            $settleTime = intval($settleTime);
            $settleTime = ($settleTime < 0 || $settleTime > 23) ? 1 : $settleTime;

            $transferTime = intval($transferTime);
            $transferTime = ($transferTime < 0 || $transferTime > 23) ? 1 : $transferTime;

            //日期处理
            $settleDate = intval($settleDate);
            $settleDate = ($settleDate < 1 || $settleDate > 31) ? 28 : $settleDate;

            $transferDate = intval($transferDate);
            $transferDate = ($transferDate < 1 || $transferDate > 31) ? 28 : $transferDate;

            //有些月份里面没有29, 30, 31号，就在这里处理
            $settleDateTmp = $this->_getRealDate($threeMark, $settleDate, $year);
            $transferDateTmp = $this->_getRealDate($threeMark, $transferDate, $year);

            $resSettleTime   = $settleDateTmp . " {$settleTime}:00:00";
            $resTransferTime = $transferDateTmp . " {$transferTime}:00:00";
        }

        return ['settle_time' => $resSettleTime, 'transfer_time' => $resTransferTime];
    }

    /**
     * 创建打款记录
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $fid 需要转账用户ID
     * @param  $settleTime 具体的结算时间戳
     * @param  $transferTime '具体的打款时间戳
     * @param  $cycleMark 提现周期标识，可明显看出提什么时候的钱，日（20160612）-月（20160104）-周（20160238）
     * @return
     */
    public function createAutoRecord($fid, $settleTime, $transferTime, $cycleMark, $mode) {
        if(!$fid || !$settleTime || !$transferTime || !$cycleMark) {
            return falsel;
        }
        
        $settleTime   = strval($settleTime);
        $transferTime = strval($transferTime);
        $cycleMark    = intval($cycleMark);

        $data = [
            'fid'           => $fid,
            'settle_time'   => $settleTime,
            'transfer_time' => $transferTime,
            'cycle_mark'    => $cycleMark,
            'mode'          => $mode,
            'update_time'   => time()
        ];

        //在这边开启事务
        $this->startTrans();

        $res = $this->table($this->_recordTable)->add($data);
        if(!$res) {
            $this->rollback();
            return false;
        }

        //修改上次更新的周期标识
        $where = ['fid' => $fid];
        $data  = ['cycle_mark' => $cycleMark];

        $res = $this->table($this->_settingTable)->where($where)->save($data);

        if($res === false) {
            $this->rollback();
            return false;
        } else {
            $this->commit();
            return true;
        }
    }

    /**
     * 更新结算信息
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $freezeMoney 冻结的金额 - 分
     * @param  $transferMoney 具体需要转账的金额 - 分
     * @param  $remark
     * @return
     */
    public function updateSettleInfo($id, $freezeMoney, $transferMoney, $remark = false) {
        $id = intval($id);
        $freezeMoney = intval($freezeMoney);
        $transferMoney = intval($transferMoney);

        if($remark) {
            $remark = strval($remark);
        }

        if(!$id || $transferMoney <= 0) {
            return false;
        }

        $data = [
            'freeze_money'   => $freezeMoney,
            'transfer_money' => $transferMoney,
            'is_settle'      => 1,
            'is_transfer'    => 0,
            'update_time'    => time()
        ];
        
        if($remark) {
            $data['remark'] = $remark;
        }

        $res = $this->table($this->_recordTable)->where(['id' => $id])->save($data);
        return $res === false ? false : true;
    }

    /**
     * 终止清算
     * 记录错误或是不能完成清算的情况
     * 
     * @author dwer
     * @date   2016-06-16
     *
     * @param  $id
     * @param  $remark
     * @return
     */
    public function stopSettle($id, $remark = '') {
        if(!$id) {
            return false;
        }

        $data = [
            'status'      => 2,
            'update_time' => time(),
            'is_settle'   => 1,
            'is_transfer' => 1
        ];

        if($remark) {
            $data['remark'] = strval($remark);
        }

        $res = $this->table($this->_recordTable)->where(['id' => $id])->save($data);
        return $res === false ? false : true;
    }

    /**
     *  更新转账信息
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $status 转账的时候出现的状态 0：默认有效的，1=无效的，余额不够转了就会设置为无效，2=清算终止, 3=提现出错了
     * @return
     */
    public function updateTransferInfo($id, $status, $transRemark = '') {
        if(!$id || !in_array($status, [0, 1, 2, 3])) {
            return false;
        }

        $data = [
            'is_transfer' => 1,
            'status'      => $status,
            'update_time' => time()
        ];

        if($transRemark) {
            $data['trans_remark'] = $transRemark;
        }

        $res = $this->table($this->_recordTable)->where(['id' => $id])->save($data);
        return $res === false ? false : true;
    }

    /**
     * 获取清算列表
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $page 第几页
     * @param  $size 记录数
     * @return
     */
    public function getSettleList($page, $size) {
        $page = intval($page);
        $size = intval($size);

        $where = [
            'status'      => 0,
            'is_settle'   => 0,
            'is_transfer' => 0,
            'settle_time' => ['ELT', time()]
        ];

        $field = 'id, fid, cycle_mark';
        $order = 'update_time asc';

         $res = $this->table($this->_recordTable)->where($where)->field($field)->order($order)->page($page . ',' . $size)->select();

         return $res === false ? [] : $res;
    }

    /**
     * 获取具体清分列表
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $page 第几页
     * @param  $size 记录数
     * @return
     */
    public function getTransferList($page, $size) {
        $page = intval($page);
        $size = intval($size);

        $where = [
            'status'        => 0,
            'is_settle'     => 1,
            'is_transfer'   => 0,
            'transfer_time' => ['ELT', time()]
        ];

        $field = 'id, fid, freeze_money, transfer_money';
        $order = 'update_time asc';

         $res = $this->table($this->_recordTable)->field($field)->order($order)->where($where)->page($page . ',' . $size)->select();

         return $res === false ? [] : $res;
    }

    /**
     * 获取转账详情列表
     * @author dwer
     * @date   2016-06-08
     * 
     * @param  $fid 根据提现用户来查
     * @param  $page 第几页
     * @param  $size 记录数
     * @return
     */
    public function getRecords($fid, $page, $size) {
        if(!$fid) {
            return false;
        }

        $page = intval($page);
        $size = intval($size);

        $where = [
            'fid'      => $fid
        ];

        $order = 'update_time desc';

        $count = $this->table($this->_recordTable)->where($where)->order($order)->count();
        $list  = $this->table($this->_recordTable)->where($where)->order($order)->page($page . ',' . $size)->select();

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 清算账号信息
     * @author dwer
     * @date   2016-06-15
     *
     * @param  $fid
     * @return
     */
    public function settleAmount($fid) {
        if(!$fid) {
            return ['status' => -1];
        }

        $settingInfo = $this->table($this->_settingTable)->where(['fid' => $fid])->field('freeze_type, freeze_data, service_fee, status')->find();
        if(!$settingInfo) {
            return ['status' => -1];
        }

        //判断如果状态是关闭的，就终止清算的工作
        if($settingInfo['status'] == 0) {
            return ['status' => -2];
        }

        $freezeType = $settingInfo['freeze_type'];
        $freezeData = $settingInfo['freeze_data'];
        $serviceFee = $settingInfo['service_fee'];

        //获取账号余额
        $memberModel = new Member();
        $amoney = $memberModel->getMoney($fid, 0);
        if($amoney <= 0) {
            return ['status' => -3, 'amoney' => $amoney];
        }

        if($freezeType == 1) {
            //冻结未使用的总额 - 在线支付的未使用的订单的总额
            $res = $this->_getUnusedOrderInfo($fid);
            if($res === false) {
                //获取未使用订单金额时报错
                return ['status' => -4];
            }

            $orderNum    = $res['order_num'];
            $ticketNum   = $res['ticket_num'];
            $freezeMoney = $res['money'];

            if($freezeMoney >= $amoney) {
                //账号余额不足冻结金额
                return ['status' => -5, 'amoney' => $amoney, 'freeze_money' => $freezeMoney];
            }

            $transferMoney = $amoney - $freezeMoney;
            $remarkData = [
                'order_num'  => $orderNum,
                'ticket_num' => $ticketNum,
            ];
        } else {
            //按比例或是固定金额冻结
            $freezeData = @json_decode($freezeData, true);
            if(!$freezeData || !is_array($freezeData)) {
                return ['status' => -1];
            }

            $type  = intval($freezeData['type']);
            $value = floatval($freezeData['value']);
            if($type == 1) {
                //比例
                $freezeMoney   = round($amoney * ($value / 100), 2);
                $transferMoney = $amoney - $freezeMoney;
            } else {
                //固定金额
                $freezeMoney = $value * 100;//转化为分
                if($freezeMoney >= $amoney) { 
                    //账号余额不足冻结金额
                    return ['status' => -5, 'amoney' => $amoney, 'freeze_money' => $freezeMoney];
                }

                $transferMoney = $amoney - $freezeMoney;
            }

            $remarkData = [
                'type'  => $type,
                'value' => $value,
            ];
        }


        $res = [
            'status'         => 1,
            'amoney'         => $amoney,
            'transfer_money' => $transferMoney,
            'freeze_money'   => $freezeMoney,
            'remark_data'    => $remarkData
         ];

        return $res;
    }

    /**
     * 具体的清分动作
     * @author dwer
     * @date   2016-06-16
     *
     * @param  $id 自动清分记录ID
     * @param  $fid 用户ID
     * @param  $freezeMoney 冻结金额 - 分
     * @param  $transferMoney 提现金额 - 分
     * @return
     */
    public function transMoney($id, $fid, $freezeMoney, $transferMoney) {
        $freezeMoney   = intval($freezeMoney);
        $transferMoney = intval($transferMoney);

        if(!$id || !$fid || $transferMoney <= 0) {
            return ['status' => -1];
        }

        //获取配置信息
        $settingInfo = $this->table($this->_settingTable)->where(['fid' => $fid])->field('account_info, service_fee, status')->find();
        if(!$settingInfo) {
            return ['status' => -1];
        }

        $serviceFee  = floatval($settingInfo['service_fee']);
        $accountInfo = @json_decode($settingInfo['account_info'], true);

        //参数判断
        if(($serviceFee > 1000) || !is_array($accountInfo)) {
            return ['status' => -1];
        }

        //判断现在的账号余额是不是够清分
        $memberModel = new Member();
        $amoney = $memberModel->getMoney($fid, 0);
        if($amoney <= 0) {
            return ['status' => -2, 'amoney' => $amoney];
        }

        if($amoney < ($freezeMoney + $transferMoney)) {
            //余额不够，不能清分
            return ['status' => -3, 'amoney' => $amoney, 'freeze_money' => $freezeMoney, 'transfer_money' => $transferMoney];
        }

        //剩余的账号金额
        $leftMoney = $amoney - $transferMoney;

        //计算手续费，不足一元按一元计算 - 分为单位
        $feeMoney = intval($transferMoney * ($serviceFee / 1000));
        $feeMoney = $feeMoney < 100 ? 100 : $feeMoney;

        if($feeMoney > $leftMoney ) {
            //剩余金额不足以支付提现手续费
            return ['status' => -4, 'amoney' => $amoney, 'fee_money' => $feeMoney, 'transfer_money' => $transferMoney];
        }

        //提现
        $withdrawModel = new Withdraws();
        $feeCutWay     = 1;
        $accountType   = 1;

        $res = $withdrawModel->addRecord($fid, $transferMoney, $serviceFee, $feeCutWay, $accountType, $accountInfo, true);

        if($res) {
            return ['status' => 1];
        } else {
            return ['status' => -5];
        }
    }

    /**
     * 格式化参数
     * @author dwer
     * @date   2016-06-09
     *
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freezeType 资金冻结类型，1=冻结未使用的总额，2=按比例冻结
     * @param $closeDate 结算日期，日结（几点），月结（几号），周结（周1-周7）
     * @param $closeTime 结算时间，具体几点
     * @param $transferDate 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $transferTime 转账时间，具体几点
     * @param $updateUid 配置修改用户ID
     * @param $accountInfo '账号的数组 {"bank_name":"","bank_ins_code":"","bank_account":"","acc_type":""}',
     * @param $freezeData 资金冻结详情，比例或是具体的金额 - {"type":"1/2","value":"30"}
     * @return array / bool
     */
    private function _formatParam($mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee, 
            $freezeData = false) {

        if(!in_array($mode, [1, 2, 3]) || !$updateUid || !$accountInfo || !is_array($accountInfo)) {
            return false;
        }

        $closeDate    = intval($closeDate);
        $closeTime    = intval($closeTime);
        $transferDate = intval($transferDate);
        $transferTime = intval($transferTime);
        $accountInfo  = json_encode($accountInfo);
        $serviceFee   = floatval($serviceFee);
        
        if($freezeType == 2) {
            if(!$freezeData || !is_array($freezeData)) {
                return false;
            }

            $freezeData = json_encode($freezeData);
        }

        if($serviceFee < 0 || $serviceFee > 100) {
            return false;
        }

        $data = [
            'mode'          => $mode,
            'freeze_type'   => $freezeType,
            'close_date'    => $closeDate,
            'close_time'    => $closeTime,
            'transfer_date' => $transferDate,
            'transfer_time' => $transferTime,
            'account_info'  => $accountInfo,
            'service_fee'   => $serviceFee,
            'update_uid'    => $updateUid,
            'update_time'   => time()
        ];

        if($freezeType == 2) {
            $data['freeze_data'] = $freezeData;
        }

        return $data;
    }

    /**
     * 根据第几周获取这一周的第几天的日期
     * @author dwer
     * @date   2016-06-15
     *
     * @param  $year 几年
     * @param  $weekNum 第几周
     * @param  $dayOfWeek 周几 1-7
     * @return
     */
    private function _getDateFromWeekNum($year, $weekNum, $dayOfWeek = 1) {
        $year      = intval($year);
        $weekNum   = intval($weekNum);
        $dayOfWeek = intval($dayOfWeek);
        $weekNum   = $weekNum < 1 ? 1 : $weekNum;
        $dayOfWeek = ($dayOfWeek <= 1 || $dayOfWeek <=7) ? $dayOfWeek : 1;

        //获取第一周的第一天的日期
        $startDate = $year . "-01-01";
        $startTime = strtotime($startDate);

        if (intval(date('N', $startTime)) != 1) {
            //如果新年的第一天不是周一
            $startTime = strtotime("next monday", strtotime($startDate));
        }

        //今天第一天的日期
        $firstDay = date("Y-m-d", $startTime);

        //指定周第一天的日期
        $tmpNum       = $weekNum - 1;
        $weekFirstDay = date("Y-m-d", strtotime("$firstDay {$tmpNum} week "));

        //获取具体星期几的日期
        $tmpNum = $dayOfWeek - 1;
        $needDay = date('Y-m-d', strtotime("$weekFirstDay {$tmpNum} day"));

        return $needDay;
    }

    /**
     * 检测日期是不是存在，如果不存在往后退，找到最近的一天
     * @author dwer
     * @date   2016-06-15
     *
     * @param  int $month 月
     * @param  int $day 日
     * @param  int $year 年
     * @return 具体日期
     */
    private function _getRealDate($month, $day, $year) {
        $res = checkdate($month, $day, $year);
        if($res) {
            return $year . '-' . $month . '-' . $day;
        } else {
            return $this->_getRealDate($month, --$day, $year);
        }
    }

    /**
     * 通过接口获取在线支付的未使用的订单的总额
     * @author dwer
     * @date   2016-06-16
     *
     * @param  $fid
     * @return [type]
     */
    private function _getUnusedOrderInfo($fid) {
        if(!$fid) {
            return false;
        }

        $table = "$this->_orderTable s";
        $joinSplit   = "left join order_aids_split os on s.ordernum=os.orderid";
        $joinDetail  = "left join uu_order_fx_details fd on s.ordernum=fd.orderid";

        $where = [
            'os.sellerid'   => $fid, //供应商
            's.status'      => 0, //未使用
            's.paymode'     => ['in', [1, 2, 5, 6, 7, 8, 9, 10, 11]], //在线支付的
            'fd.pay_status' => 1 //已经支付
        ];

        $field = 's.tnum, s.tprice';
        $page = "1,100000";

        $res = $this->table($table)
                    ->join($joinSplit)
                    ->join($joinDetail)
                    ->field($field)
                    ->page($page)
                    ->where($where)
                    ->select();

        //如果查询出错，就返回错误
        if($res === false) {
            return false;
        }

        //获取统计信息
        $orderNum  = count($res);
        $ticketNum = 0;
        $money     = 0;

        foreach($res as $item) {
            $ticketNum += $item['tnum'];
            $money     += $item['tnum'] * $item['tprice'];
        }

        //返回数据
        return ['order_num' => $orderNum, 'ticket_num' => $ticketNum, 'money' => $money];
    }


}