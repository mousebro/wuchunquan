<?php

use Model\Demo\Foo;
use Model\Demo\Bar;
ini_set('display_errors', 'On');

include_once dirname(__FILE__) . '/../../init.php';

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
