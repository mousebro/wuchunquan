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
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $dayMark, $item['close_time']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $dayMark);
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

        //获取周结的记录
        $weekMark = date('Y02W');
        $dayList = $settleBlanceModel->getSettingList(1, 200, false, 2, $weekMark);
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $weekMark, $item['close_time'], $item['close_date']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $weekMark);
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

        //获取月结的记录
        $montyMark = date('Y01m');
        $dayList = $settleBlanceModel->getSettingList(1, 200, false, 3, $montyMark);
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime($item['mode'], $montyMark, $item['close_time'], $item['close_date'], $item['transfer_time'], $item['transfer_date']);

            $settleTime   = strtotime($timeArr['settle_time']);
            $transferTime = strtotime($timeArr['transfer_time']);
            $fid          = $item['fid'];

            $res = $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $montyMark);
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

        $settleList = $settleBlanceModel->getSettleList(1, 100);
        foreach($settleList as $item) {
            //清算金额
            $fid        = $item['fid'];
            $id         = $item['id'];
            $settleInfo = $settleBlanceModel->settleAmount($fid);

            //状态
            $status = $settleInfo['status'];

            if($status === -1) {
                //记录数据错误
                $settleBlanceModel->stopSettle($id, '自动清分配置错误');
            } else if($status === -2) {
                //清分关闭
                $settleBlanceModel->stopSettle($id, '自动清分处于关闭状态');
            } else if($status === -3) {
                //账户余额没有钱
                $amoney = 
                $settleBlanceModel->stopSettle($id, '账号余额已经没有钱了，账户余额：');
            }  else if($status === -4) {
                $settleBlanceModel->stopSettle($id, '自动清分处于关闭状态');
            }  else if($status === -5) {
                $settleBlanceModel->stopSettle($id, '自动清分处于关闭状态');
            } 
            

            $res = $settleBlanceModel->updateSettleInfo($id, $freezeMoney, $transferMoney, $remark);
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

        $transferList = $settleBlanceModel->getTransferList(1, 100);
        foreach($transferList as $item) {
            //调用自动提现的接口
            

            //更新数据
            $settleBlanceModel->updateTransferInfo($id, $status);
        }

    }
}