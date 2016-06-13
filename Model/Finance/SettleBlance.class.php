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

class SettleBlance extends Model{
    //自动清分配置表
    private $_settingTable = 'pft_auto_withdraw_setting';
    //清分记录表
    private $_recordTable = 'pft_auto_withdraw_record';

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
     * @param $accountInfo 账号的数组 {"bank_name":"","bank_ins_code":"","bank_account":"","account_name":"",acc_type":""}',
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

        if($data) {
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
    public function updateSetting($id, $$mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee,
            $freezeData = false) {
        if(!$id) {
            return false;
        }

        //参数统一校验，格式化
        $data = $this->_formatParam($mode, $freezeType, $closeDate, $closeTime, $transferDate, $transferTime, $updateUid, $accountInfo, $serviceFee
            $freezeData);

        if($data) {
            return false;
        }

        $res = $this->table($this->_settingTable)->where(['id' => $id])->save($data);

        return $res === false ? false : true;
    }

    /**
     * 修改上次更新的周期标识
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $circleMark 上次更新的周期标识，日（20160612）-月（20160104）-周（20160238）
     * @return
     */
    public function updateCircle($id, $circleMark) {
        $circleMark = intal($circleMark);
        if(!$id || !$circleMark) {
            return false;
        }

        $where = ['id' => $id];
        $data = ['circel_mark' => $circleMark];

        $res = $this->table($this->_settingTable)->where($where)->save($data);
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

        $where = [];
        if($fid) {
            $where['fid'] => intval($fid);
        }

        if($mode) {
            $where['mode'] => intval($mode);
        }

        if($circleMark) {
            $where['circle_mark'] => ['lt', intval($mode)];
        }

        $res = $this->table($this->_settingTable)->where($where)->page($page . ',' . $size)->select();

        return $res === false ? [] : $res;
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
    public function createAutoRecord($fid, $settleTime, $transferTime, $cycleMark) {
        if(!$fid || !$settleTime || !$transferTime || !$cycleMark) {
            return falsel;
        }
        
        $settleTime   = intval($settleTime);
        $transferTime = intval($transferTime);
        $cycleMark    = intval($cycleMark);

        $data = [
            'fid'           => $fid,
            'settle_time'   => $settleTime,
            'transfer_time' => $transferTime,
            'circle_mark'   => $cycleMark,
            'update_time'   => time()
        ];

        $res = $this->table($this->_recordTable)->add($data);

        return $res === false ? false : true;
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
            'fid'            => $fid,
            'freeze_money'   => $freezeMoney,
            'transfer_money' => $transferMoney,
            'is_settle'      => 0,
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
     *  更新转账信息
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $status 转账的时候出现的状态 0：默认有效的，1=无效的，余额不够转了就会设置为无效 2=提现出错了
     * @return
     */
    public function updateTransferInfo($id, $status) {
        if(!$id || !in_array($status, [0, 1, 2])) {
            return false;
        }

        $data = [
            'status'      => $status,
            'update_time' => time()
        ];

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
            'settle_time' => ['gt', time()]
        ];

         $res = $this->table($this->_recordTable)->where($where)->page($page . ',' . $size)->select();

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
            'transfer_time' => ['gt', time()]
        ];

         $res = $this->table($this->_recordTable)->where($where)->page($page . ',' . $size)->select();

         return $res === false ? [] : $res;
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


}