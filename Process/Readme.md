有些数据需要比较复杂的而且可重用的处理，可以统一放在这边处理
具体可以参照Finance/TradeRecord.class.php 

用法：

use Process\Finance\TradeRecord as RecordProcess;

$process = new RecordProcess();
$params  = $process->getQueryParams();

if($paras['code'] !== 200) {
    //错误提示

}