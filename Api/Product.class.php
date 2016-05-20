<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/4/23
 * Time: 12:29
 */

namespace Api;


use Controller\product\ProductBasic;
use Library\Controller;
use Model\Product\Land;
use Model\Product\Ticket;

class Product extends ProductBasic
{

    public function basicInfo()
    {

    }

    public function ticketCreate()
    {
        $ticketData = $_POST;
        $landModel   = new Land();
        $ticketObj   = new Ticket();
        $ret =  $this->SaveTicket($this->memberID, $ticketData, $ticketObj, $landModel);
    }

}