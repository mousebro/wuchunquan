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
     * @param $closeTime 结算时间，日结（几点），月结（几号），周结（周几）
     * @param $transferTime 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $updateUid 配置修改用户ID
     * @param $accountInfo '账号的json信息串 {"bank_name":"","bank_ins_code":"","bank_account":"","acc_type":""}',
     * @param $freezeData 资金冻结详情，比例或是具体的金额 - {"type":"1/2","value":"30"}
     *
     * @return bool
     */
    public function addSetting($fid, $mode, $freezeType, $closeTime, $transferTime, $updateUid, $accountInfo, $freezeData = false) {

        return true;
    }

    /**
     * 更新自动清分配置
     * @author dwer
     * @date   2016-06-08
     *
     * @param $id 记录ID
     * @param $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param $freezeType 资金冻结类型，1=冻结未使用的总额，2=按比例冻结
     * @param $closeTime 结算时间，日结（几点），月结（几号），周结（周几）
     * @param $transferTime 转账日期，日结（几点），月结（几号），周结（周几）
     * @param $updateUid 配置修改用户ID
     * @param $freezeData 资金冻结详情，比例或是具体的金额 - {"type":"1/2","value":"30"}
     *
     * @return bool
     */
    public function updateSetting($id, $mode, $freezeType, $closeTime, $transferTime, $updateUid, $freezeData) {

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

    }

    /**
     * 更改配置的状态
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $status 状态 off=无效，on=有效
     * @return
     */
    public function settingStatus($id, $status = 'off') {

    }

    /**
     *  
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $page 第几页
     * @param  $size 条目数
     * @param  $fid 用户ID 如果有搜索用户的话，传ID
     * @param  $mode 自动清分模式，1=日结，2=周结，3=月结
     * @param  $closeTime 结算时间，日结（几点），月结（几号），周结（周几）
     * @param  $circleMark 上次更新的周期标识，日（20160612）-月（20160104）-周（20160238）
     * @return
     */
    public function getSettingList($page, $size, $fid = false, $mode = false, $closeTime = false, $circleMark = false) {

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

    }

    /**
     * 更新结算信息
     * @author dwer
     * @date   2016-06-08
     *
     * @param  $id 记录ID
     * @param  $freezeMoney 冻结的金额
     * @param  $transferMoney 具体需要转账的金额
     * @param  $remark
     * @return
     */
    public function updateSettleInfo($id, $freezeMoney, $transferMoney, $remark) {

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

    }



}