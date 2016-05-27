<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/23-023
 * Time: 17:22
 */
namespace Controller\product;
use Model\Product\Land;

class scenic extends ProductBasic
{
    private $memberID;
    //private $ticketObj = ;
    public function __construct()
    {
        if (!$_SESSION['memberID']) parent::apiReturn(self::CODE_AUTH_ERROR,[],'未登录');
        //$this->ticketObj = parent::model('\Product\Ticket');
        $this->memberID = $_SESSION['sid'];
        parent::__construct();
    }
    public function save()
    {
        $land = new Land();
        $this->SaveBasicInfo($this->memberID, $land);
    }
}