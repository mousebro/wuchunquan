<?php

use Model\Demo\Foo;
use Model\Demo\Bar;
ini_set('display_errors', 'On');

include_once dirname(__FILE__) . '/../../init.php';

/*'3761392',
'3761372',
'3759444',
'3755242',
'3712315',*/

$online = new \Model\TradeRecord\OnlineRefund();
$arr = [
    '3761392'=>0.02,
    //'3761372'=>0.02,
    //'3759444'=>0.01,
    '3755242'=>0.02,
    //'3712315'=>0.01,
];
$arr2 = [
    3761372=>3932,
    3759444=>3933,
    3712315=>3934,
    //3761392=>3935,
    //3755242=>3936,
];
foreach ($arr2 as $ordernum=>$log_id)
{
    $url = "http://open.12301.test/pft_api.php?c=OnlineRefund&a=wx&ordernum={$ordernum}&log_id=$log_id";
    echo $url,PHP_EOL;
    //$res = file_get_contents("http://open.12301.test/pft_api.php?c=OnlineRefund&a=wx&ordernum={$ordernum}&log_id=$log_id");
}
//$ordernum  = 3712315;
//foreach ($arr as $ordernum=>$money) {
//    $id1  = $online->AddMemberLog(94, $ordernum, $money*100);
//    $id2  = $online->AddRefundLog(94, $ordernum, 1, $money*100, 0, 'test');
//    var_dump($id2);
//    echo $ordernum . ':$id=',$id2,PHP_EOL;
//}
//echo '$id1=',$id1,PHP_EOL;
//$id2  = $online->GetTradeLog($ordernum);
//echo '$id2=',$id2,PHP_EOL;
//$info = (object)$online->GetRefundLog($id2);
//echo $info->appid;
//print_r($info);

//$res = $online->UpdateRefundLogOk($id2);
var_dump($res);
//$redis = \Library\Cache\RedisCache::Connect();
//$redis->set('foo','bar',180);
//echo $redis->get('foo');

exit;
$online = new \Model\TradeRecord\OnlineTrade();

print_r(C('redis')['main']);
exit;
$orderid = 'test_'.time();
$res = $online->addLog($orderid, 100, 'test','test',1);
var_dump($res);
$ret = $online->getLog($orderid, 1);
var_dump($ret);
exit;
$model = new Foo();
$name = $model->getName();
var_dump($name);


die;


$obj = new \Model\Product\Land('','',C('db')['localhost']);
$res = $obj->getTerminalId();
var_dump($res);
exit;
$res = $OnlineTrade->addLog('test_1232',1,'test','test', \Model\TradeRecord\OnlineTrade::CHANNEL_ALIPAY);
var_dump($res);
//$connection = C('db')['localhost'];
//
//$foo = new Foo('member','pft_',$connection);
//$bar = new Bar('jq_ticket','uu_',$connection);
//var_dump($foo->show_ticket(800,  $bar));
//print_r($bar->call_procudure());
//$member->members();
//print_r($member->findMemberById(94));


//$res = $member->register(['dname'=>'事务测试','cname'=>'thinkphp', 'account'=>'dev_cgp_'.mt_rand(100000,999999)],[]);
//var_dump($res);
//print_r($member->updateMember(94));
//print_r($member->findMemberById(94));
//\Model\Member\Foo::say();
/*print_r(C('db'));*/
//print_r(C('db'));
