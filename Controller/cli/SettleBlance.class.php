<?php if(!defined('PFT_CLI')) {exit('Access Deny');}
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

class SettleBlance extends Controller {
    
    public function __construct() }{
        //做下运行模式的判断

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
        $settleBlanceModel = $this->model('Finance/SettleBlance');

        //获取日结的记录
        $dayMark = date('Ymd');
        $dayList = $settleBlanceModel->getSettingList(1, 100, false, 1, $dayMark);
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime();


            $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $cycleMark);

            $settleBlanceModel->updateCircle($id, $dayMark)
        }

        //获取周结的记录
        $weekMark = date('Y02W');
        $dayList = $settleBlanceModel->getSettingList(1, 100, false, 2, $weekMark);
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime();


            $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $cycleMark);

            $settleBlanceModel->updateCircle($id, $weekMark)
        }


        //获取月结的记录
        $montyMark = date('Y01m');
        $dayList = $settleBlanceModel->getSettingList(1, 100, false, 3, $montyMark);
        foreach($dayList as $item) {
            $timeArr = $settleBlanceModel->createSettleTime();


            $settleBlanceModel->createAutoRecord($fid, $settleTime, $transferTime, $cycleMark);

            $settleBlanceModel->updateCircle($id, $montyMark)
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