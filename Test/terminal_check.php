<?php
/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 4/20-020
 * Time: 17:46
 */
include '/var/www/html/new/d/class/Terminal_Check_Socket.class.php';
$tc = new Terminal_Check_Socket('127.0.0.1');
$UUsalerid     = '867366';
$terminal_inum = '14917';
$chkIns        = '499';
$UUcode        = '208129';
$actiontime    = '2016-04-19 00:00:00';
$terminal = $tc->Terminal_Check_In_Voucher($terminal_inum,
    $UUsalerid,$UUcode,array(
        "vMode"=>7,
        "vCmd"=>$chkIns,
        'vCheckDate'=>$actiontime)
); //vMode 7外部系统验证，5自助机验证,3软终端验证。vCmd 499绕过所有限制验证，498已过期验证
var_dump($terminal);