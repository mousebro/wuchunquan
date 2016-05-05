<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/3/26
 * Time: 08:43
 */

namespace Controller;


use Library\Controller;
use Model\Report\OrderReport;

class OnlineTrade extends Controller
{
    private $model;
    public function __construct()
    {
        $this->model = new \Model\TradeRecord\OnlineTrade();
    }

    public function OrderCount()
    {
        $model = new OrderReport();
        $tm = strtotime('- 1 days');
        $bt = date('Y-m-d', $tm);
        $model->OrderCountEveryDayCreate($bt);
    }

    public function Summary()
    {
        $tm = strtotime('- 1 days');
        $bt = date('Y-m-d 00:00:00', $tm);
        $et = date('Y-m-d 23:59:59', $tm);
        $this->model->Summary($bt, $et);
    }

    public function Summary_Manual($data)
    {
        //$time_begin = strtotime('2014-01-01 00:00:00');
        //$time_end   = strtotime('2015-01-01 00:00:00');
        //print_r($data);
        $bt = $data[3];
        $et = $data[4];
        if (!$bt || !$et) exit('Error BeginTime Or End Time.');
        $time_begin = strtotime("$bt 00:00:00");
        $time_end   = strtotime("$et 00:00:00");
        $diff       = ($time_end - $time_begin) / 86400;
        for ($i=0; $i<$diff; $i++) {
            $tm = strtotime(" +$i days", $time_begin);
            $bt = date('Y-m-d 00:00:00', $tm);
            $et = date('Y-m-d 23:59:59', $tm);
            echo $bt,'---', $et, PHP_EOL;
            $this->model->Summary($bt, $et);
        }

    }
}