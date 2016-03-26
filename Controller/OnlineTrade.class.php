<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/3/26
 * Time: 08:43
 */

namespace Controller;


use Library\Controller;

class OnlineTrade extends Controller
{
    private $model;
    public function __construct()
    {
        $this->model = new \Model\TradeRecord\OnlineTrade();
    }

    public function Summary()
    {
        $tm = strtotime('2016-03-25');
        $bt = date('Y-m-d 00:00:00', $tm);
        $et = date('Y-m-d 23:59:59', $tm);
        $data = $this->model->Summary($bt, $et);
    }

}