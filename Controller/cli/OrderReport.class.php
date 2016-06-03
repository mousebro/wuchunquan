<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/5/16
 * Time: 23:18
 */

namespace Controller\cli;
if (PHP_SAPI !='cli') exit('error');
defined('PFT_INIT') or exit('Access Denied');
class OrderReport
{
    public function OrderSummaryByLid()
    {
        $date = date('Ymd', strtotime('-1 days'));
        $model = new \Model\Report\ApplyerReport();
        $model->OrderSummaryByLid($date);
        //$startTime = strtotime('2016-01-01');
        //$endTime   = '2016-05-17';
        //$diff      = (strtotime($endTime) - $startTime) / 86400;
        //for ($i=0; $i< $diff; $i++) {
        //    $date = date('Ymd', strtotime("+ $i days ", $startTime));
        //    echo $date,"\n";
        //
        //}
    }
}