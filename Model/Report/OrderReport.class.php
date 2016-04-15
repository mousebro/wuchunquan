<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/15-015
 * Time: 17:13
 */

namespace Model\Report;


use Library\Model;

class OrderReport extends Model
{
    public function OrderCreated()
    {
        $sql = <<<SQL
SELECT SUM(tnum),SUM(totalmoney) FROM uu_ss_order WHERE ordertime BETWEEN '' AND ''
SQL;

    }

    public function OrderCancel()
    {

    }

    public function OrderConsume()
    {

    }
}