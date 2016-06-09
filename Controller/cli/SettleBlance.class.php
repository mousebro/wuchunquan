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

    }

    /**
     * 运行清算任务
     * @author dwer
     * @date   2016-06-09
     *
     * @return 
     */
    public function runSettleTask() {
        
    }

    /**
     * 运行打款任务
     * @author dwer
     * @date   2016-06-09
     *
     * @return
     */
    public function runTransTask() {
        
    }
}