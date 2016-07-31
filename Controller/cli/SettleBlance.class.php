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
namespace Controller\cli;
use Library\Controller;

//权限判断
if(!defined('PFT_CLI')) {
    exit('Access Deny');
}

class SettleBlance extends Controller {
    private $_logPath = 'auto_withdraw/auto';

    public function __construct() {
        //运行时间不做限制
        set_time_limit(0);
    }

    /**
     * 按设定的规则生成清分记录
     * 可以一个小时执行一次
     * 
     * @author dwer
     * @date   2016-06-09
     *
     * @return 
     */
    public function generateTransRecord() {
        //日结
        $this->runDaySettle();

        //周结
        $this->runWeekSettle();

        //月结
        $this->runMonthSettle();
    }

    /**
     * 检测生成日结清分记录
     * @author dwer
     * @date   2016-06-15
     *
     * @return 
     */
    public function runDaySettle() {
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //获取日结的记录
        $dayMark = date('Ymd'); 
        $dayList = $settleBlanceModel->getSettingList(1, 200, false, 1, $dayMark);

        //日志数据
        $logData = [];

        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $dayMark, $item['close_time']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $freezeData = @json_decode($item['freeze_data'], true);
            $freezeData = $freezeData ? $freezeData : [];
            $freezeData['freeze_type'] = $item['freeze_type'];
            $freezeData['service_fee'] = $item['service_fee'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $dayMark, 1, $freezeData);

            //清分数据
            $logData[] = [
                'fid'          => $fid,
                'settleTime'   => $timeArr['settle_time'],
                'transferTime' => $timeArr['transfer_time'],
                'result'       => $res ? 1 : 0
            ];
        }

        pft_log($this->_logPath, "生成清分记录（日结标识：{$dayMark}）:");
        foreach($logData as $item) {
            pft_log($this->_logPath, json_encode($item));
        }
    }

    /**
     * 检测生成周结清分记录
     * @author dwer
     * @date   2016-06-15
     *
     * @return 
     */
    public function runWeekSettle() {
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //日志数据
        $logData = [];

        //获取周结的记录
        $weekMark = date('Y02W');
        $weekList = $settleBlanceModel->getSettingList(1, 200, false, 2, $weekMark);
        foreach($weekList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $weekMark, $item['close_time'], $item['close_date']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $freezeData = @json_decode($item['freeze_data'], true);
            $freezeData = $freezeData ? $freezeData : [];
            $freezeData['freeze_type'] = $item['freeze_type'];
            $freezeData['service_fee'] = $item['service_fee'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $weekMark, 2, $freezeData);

            //清分数据
            $logData[] = [
                'fid'          => $fid,
                'settleTime'   => $timeArr['settle_time'],
                'transferTime' => $timeArr['transfer_time'],
                'result'       => $res ? 1 : 0
            ];
        }

        pft_log($this->_logPath, "生成清分记录（周结标识：{$weekMark}）:");
        foreach($logData as $item) {
            pft_log($this->_logPath, json_encode($item));
        }
    }

    /**
     * 检测生成月结清分记录
     * @author dwer
     * @date   2016-06-15
     *
     * @return 
     */
    public function runMonthSettle() {
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //日志数据
        $logData = [];

        //获取月结的记录
        $montyMark = date('Y01m');
        $montyList = $settleBlanceModel->getSettingList(1, 200, false, 3, $montyMark);
        foreach($montyList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $montyMark, $item['close_time'], $item['close_date'], $item['transfer_time'], $item['transfer_date']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $freezeData = @json_decode($item['freeze_data'], true);
            $freezeData = $freezeData ? $freezeData : [];
            $freezeData['freeze_type'] = $item['freeze_type'];
            $freezeData['service_fee'] = $item['service_fee'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $montyMark, 3, $freezeData);

            //清分数据
            $logData[] = [
                'fid'          => $fid,
                'settleTime'   => $timeArr['settle_time'],
                'transferTime' => $timeArr['transfer_time'],
                'result'       => $res ? 1 : 0
            ];
        }

        pft_log($this->_logPath, "生成清分记录（月结标识：{$montyMark}）:");
        foreach($logData as $item) {
            pft_log($this->_logPath, json_encode($item));
        }
    }

    /**
     * 运行清算任务
     * @author dwer
     * @date   2016-06-09
     *
     * @return 
     */
    public function runSettleTask() {
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //日志数据
        $logData = [];

        $settleList = $settleBlanceModel->getSettleList(1, 100);
        foreach($settleList as $item) {
            //清算金额
            $fid       = $item['fid'];
            $id        = $item['id'];
            $mark      = $item['cycle_mark'];
            $mode      = $item['mode'];
            $frozeData = json_decode($item['froze_data'], true);

            $settleInfo = $settleBlanceModel->settleAmount($fid, $mode, $mark, $frozeData);

            //状态
            $status = $settleInfo['status'];

            if($status === -1) {
                //记录数据错误
                $res = $settleBlanceModel->stopSettle($id, '自动清分配置错误');
            } else if($status === -2) {
                //清分关闭
                $res = $settleBlanceModel->stopSettle($id, '自动清分处于关闭状态');
            } else if($status === -3) {
                //账户余额没有钱
                $amoney = round($settleInfo['amoney']/100, 2);
                $res = $settleBlanceModel->stopSettle($id, "账号余额已经没有钱了，账户余额：{$amoney}元");
            } else if($status === -4) {
                //获取未使用订单信息的时候报错
                $res = $settleBlanceModel->stopSettle($id, '获取未使用订单金额的时候系统报错');

            } else if($status === -5) {
                $amoney      = round($settleInfo['amoney']/100, 2);
                $freezeMoney = round($settleInfo['freeze_money']/100, 2);

                $res = $settleBlanceModel->stopSettle($id, "账号余额不足，账户余额：{$amoney}元，清分冻结余额：{$freezeMoney}元");
            } else if($status === -6) {
                $transMoney = round($settleInfo['trans_money']/100, 2);
                $limitMoney = round($settleInfo['limit_money']/100, 2);

                $res = $settleBlanceModel->stopSettle($id, "提现金额{$transMoney}元不足最低提现最低额度{$limitMoney}元");
            }  else {
                //正常清算
                 $freezeMoney   = $settleInfo['freeze_money']; 
                 $transferMoney = $settleInfo['transfer_money'];
                 $remarkData    = $settleInfo['remark_data'];

                 if(isset($remarkData['type'])) {
                    if($remarkData['type'] == 1) {
                        $remark = "按比例冻结，冻结比例：{$remarkData['value']}%";
                    } else {
                        $remark = "按具体金额冻结，冻结金额：{$remarkData['value']}元";
                    }
                 } else {
                    //未使用订单
                    $tmpMoney = round($freezeMoney/100, 2);
                    $remark = "需冻结未使用订单情况：总订单数={$remarkData['order_num']}, 总票数={$remarkData['ticket_num']}, 总金额={$tmpMoney}元";
                 }

                 $res = $settleBlanceModel->updateSettleInfo($id, $freezeMoney, $transferMoney, $remark);
            }

            //清分数据
            $logData[] = [
                'fid'    => $fid,
                'id'     => $id,
                'status' => $status,
                'result' => $res ? 1 : 0
            ];
        }

        $count = count($logData);
        pft_log($this->_logPath, "运行清算任务({$count}):");
        foreach($logData as $item) {
            pft_log($this->_logPath, json_encode($item));
        }
    }

    /**
     * 运行打款任务
     * @author dwer
     * @date   2016-06-09
     *
     * @return
     */
    public function runTransTask() {
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //日志数据
        $logData = [];

        $transferList = $settleBlanceModel->getTransferList(1, 100);
        foreach($transferList as $item) {
            //参数
            $id            = $item['id'];
            $fid           = $item['fid'];
            $freezeMoney   = $item['freeze_money'];
            $transferMoney = $item['transfer_money'];
            $mode          = $item['mode'];

            //调用自动提现的接口
            $transInfo = $settleBlanceModel->transMoney($id, $fid, $freezeMoney, $transferMoney, $mode);

            //错误处理
            $status = $transInfo['status'];
            if($status === -1) {
                //配置出错了
                $res = $settleBlanceModel->updateTransferInfo($id, 1, '自动清分配置错误');
            } else if($status == -2) {
                //账户余额没有钱
                $amoney = round($transInfo['amoney']/100, 2);
                $res = $settleBlanceModel->updateTransferInfo($id, 1, "账号余额已经没有钱了，账户余额：{$amoney}元");
            } else if($status == -3) {
                //余额不够，不能清分
                $amoney        = round($transInfo['amoney']/100, 2);
                $freezeMoney   = round($transInfo['freeze_money']/100, 2);
                $transferMoney = round($transInfo['transfer_money']/100, 2);

                $res = $settleBlanceModel->updateTransferInfo($id, 1, "账号余额不足，账户余额：{$amoney}元，提现金额：{$transferMoney}元，不可提现金额：{$freezeMoney}元");
            } else if($status == -4) {
                //剩余金额不足以支付提现手续费
                $amoney        = round($transInfo['amoney']/100, 2);
                $feeMoney      = round($transInfo['fee_money']/100, 2);
                $transferMoney = round($transInfo['transfer_money']/100, 2);

                $res = $settleBlanceModel->updateTransferInfo($id, 1, "剩余金额不足以支付提现手续费，账户余额：{$amoney}元，提现金额：{$transferMoney}元，提现手续费：{$feeMoney}元");
            } else if($status == -5) {
                //系统错误了，提现出现问题
                $res = $settleBlanceModel->updateTransferInfo($id, 3, "系统错误了，提现出现问题");
            } else {
                //提现成功
                $res = $settleBlanceModel->updateTransferInfo($id, 0, "提现成功");
            }

            //清分数据
            $logData[] = [
                'fid'    => $fid,
                'id'     => $id,
                'status' => $status,
                'result' => $res ? 1 : 0
            ];
        }

        $count = count($logData);
        pft_log($this->_logPath, "具体清分任务({$count}):");
        foreach($logData as $item) {
            pft_log($this->_logPath, json_encode($item));
        }
    }
}