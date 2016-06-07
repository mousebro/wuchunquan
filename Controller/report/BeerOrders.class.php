<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 6/7-007
 * Time: 9:34
 */

namespace Controller\report;


use Library\Controller;
use Model\Report\ApplyerReport;

class BeerOrders extends Controller
{
    public function index()
    {
        if (!$_SESSION['memberID']) {
            self::apiReturn(self::CODE_AUTH_ERROR);
        }
        $model          = new ApplyerReport();
        $lid            = I('get.lid', 19810, 'intval');
        $start_day      = I('get.start_day', '20160606');
        $end_day        = I('get.end_day','20160705');
        $data_history   = $model->GetOrderSummaryById($start_day, $end_day, $lid);
        $data_today     = $model->OrderSummaryByLid(date('Ymd'), $lid, true);
        $output = [];
        foreach($data_history as $history) {
            $output['history']['tnum']       += $history['tnum'];
            $output['history']['totalmoney'] += $history['total_money'] / 100;
        }
        if ($data_today===false) {
            $data_today = [['tnum'=>0, 'totalmoney'=>0]];
        }
        foreach($data_today as $today) {
            $output['today']['tnum']        += $today['tnum'];
            $output['today']['totalmoney']  += $today['totalmoney'] / 100;
            $output['history']['tnum']      += $today['tnum'];
            $output['history']['totalmoney']+= $today['totalmoney'] / 100;
        }
        self::apiReturn(200, $output);
    }
}