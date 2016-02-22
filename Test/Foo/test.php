<?php
use Model\Demo\Foo;
use Model\Demo\Bar;
include dirname(__DIR__).'../../init.php';
\Model\Demo\Foo::say();

//$name, $tablePrefix,
print_r(C('db')['localhost']);
exit('0000');
$connection = C('db')['localhost'];

$foo = new Foo('member','pft_',$connection);
$bar = new Bar('jq_ticket','uu_',$connection);
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
