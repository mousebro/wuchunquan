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
use Library\Model;
use Model\Product\Land;
use Model\Product\Ticket;

class Product extends ProductBasic
{
    public function __construct()
    {
        parent::__construct();
        $this->config = array_merge($this->config, include  '/var/www/html/Service/Conf/sdk.conf.php');
        $this->memberId = $this->config[I('post.app_id')];
    }


    public function BaseInfo()
    {
        $modelLand = new Land();
        $res = parent::SaveBasicInfo($this->memberId, $modelLand);
        var_dump($res);
    }

    /**
     * 获取票付通地区数据
     */
    public function areas()
    {
        $province_id = I('post.province', 0, 'intval');
        if (!$province_id) {
            $provinces = C(dirname(__FILE__) . '/../Conf/province.conf.php');
            parent::apiReturn(self::CODE_SUCCESS, $provinces);
        }
        $sql = <<<SQL
select b.area_id as city_id, b.area_name as city_name,
a.area_id as zone_id, a.area_name as zone_name
from uu_area b
LEFT JOIN uu_area a ON a.area_parent_id=b.area_id
where b.area_parent_id=$province_id
SQL;
        $m = new Model();
        $data = $m->query($sql);
        if ($data) {
            parent::apiReturn(self::CODE_SUCCESS, $data);
        }
        parent::apiReturn(self::CODE_NO_CONTENT, [], '查不到相应省份的数据');
    }

    public function AddTicket()
    {
        $ticketData = $_POST;
        $landModel   = new Land();
        $ticketObj   = new Ticket();
        $ticketData['ttitle']      = $ticketData['ticket_name'];
        $ticketData['apply_limit'] = 1;
        $price_list = (array)$ticketData['price_list'];
        unset($ticketData['price_list']);
        $ret =  $this->SaveTicket($this->memberId, $ticketData, $ticketObj, $landModel);

        if ($price_list) {
            $price_ret = $this->SavePrice($ret['data']['pid'], $price_list);
        }
        print_r($ret);

        print_r($price_ret);
    }

}