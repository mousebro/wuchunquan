<?php
namespace Controller\MsgNotify;

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/15-015
 * Time: 11:44
 *
 * 即时通短信回调通知
 */

class VCom
{
    /**
     * 获取短信状态报告
     */
    public function Notify()
    {
        if ($_GET['state'] != 'S0S')
            pft_log('vcome/notify', json_encode($_GET));
        echo '0';
    }

    /**
     * 上行回复
     */
    public function Reply()
    {
        pft_log('vcome/reply', json_encode($_GET));
        echo '0';
    }
}