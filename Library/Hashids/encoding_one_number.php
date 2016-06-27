<?php
set_time_limit(0);
ini_set('memory_limit', '1G');
$t1 = microtime_float();
include 'HashGenerator.php';
include 'Hashids.php';
$hashids = new Hashids\Hashids('pft12301');
$cnt = 0;
//$arr = [];
for($i=10000; $i<999999;$i++) {
    $id = $hashids->encode($i);
    $de_id = $hashids->decode($id);
//    $arr[$id] = [$de_id];
//    echo $id,'---', $de_id[0],PHP_EOL;
    $cnt += 1;
}
$t2 = microtime_float();
echo 'count=',$cnt,';total time = ',($t2 - $t1),'array count=',count($arr),PHP_EOL;
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
exit;
