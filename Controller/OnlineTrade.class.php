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
        $data = $this->model->Summary();
        print_r($data);
    }
}