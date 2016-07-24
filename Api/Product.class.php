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
use Model\Product\PriceRead;
use Model\Product\Ticket;

class Product extends ProductBasic
{
    public function __construct()
    {
        parent::__construct();
        $this->config = array_merge($this->config, include  '/var/www/html/Service/Conf/sdk.conf.php');
        $this->memberID = $this->config[I('post.app_id')];
    }


    public function BaseInfo()
    {
        $modelLand = new Land();
        $res = parent::SaveBasicInfo($this->memberID, $modelLand);
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
        if (isset($ticketData['price_list'])) {
            $price_list = (array)$ticketData['price_list'];
            unset($ticketData['price_list']);
        }

        $ret =  $this->SaveTicket($this->memberID, $ticketData, $ticketObj, $landModel);
        //retail price 零售价
        //settlement price 结算价
        if ($ret['code']==200 ) {
            $ret['msg'] = '门票保存成功';
            if (isset($price_list)) {
                array_walk($price_list, function($val, $key) use (&$price_list){
                    $price_list[$key] = (array)$val;
                });
                $price_ret = $this->SavePrice($ret['data']['pid'], $price_list);
                if ($price_ret['code']!=200) $ret['msg'] = '；价格保存失败:' . $price_ret['msg'];
            }
        }
        $msg = isset($ret['data']['msg']) ? $ret['data']['msg'] : $ret['msg'];
        parent::apiReturn($ret['code'],
            [
                'product_id'=>$ret['data']['pid'],
                'ticket_id' =>$ret['data']['tid']
            ],
            $msg);
    }

    /**
     * 更新门票
     */
    public function UpdateTicket()
    {
        $_POST['tid'] = $_POST['ticket_id'];
        unset($_POST['ticket_id']);
        $this->AddTicket();
    }
    /**
     * 保存价格
     */
    public function AddPrice()
    {
        $pid = I('post.product_id', 0, 'intval');
        $price_list = [];
        array_walk($_POST['price_list'], function($val, $key) use (&$price_list){
            $price_list[$key] = (array)$val;
        });
        $price_ret = $this->SavePrice($pid, $price_list);
        parent::apiReturn($price_ret['code'], '', $price_ret['msg']);
    }

    /**
     * 修改价格
     */
    public function UpdatePrice()
    {
        $this->AddPrice();
    }

    /**
     * 获取价格
     */
    public function GetPrice()
    {
        $pid = I('post.product_id', 0, 'intval');
        $bt  = I('post.sdate');
        $et  = I('post.date');
        $modelPrice = new PriceRead();
        $price_list = $modelPrice->get_Dynamic_Price_Merge($pid, '', 3, $bt, $et);
        $output = [];
        foreach ($price_list as $item) {
            $output[] = [
                'price_id'  => $item['id'],
                'product_id'=> $item['pid'],
                'sdate'     => $item['start_date'],
                'edate'     => $item['end_date'],
                'weekdays'  => $item['weekdays'],
                'ls'        => $item['l_price'],
                'js'        => $item['ptype']==0 ? $item['n_price'] : $item['s_price'],
                'storage'   => $item['storage'],
            ];
        }
        parent::apiReturn(200, $output);
    }

    /**
     * 删除价格
     */
    public function DeletePrice()
    {
        $_POST['id']  = $_POST['price_id'];
        $_POST['pid'] = $_POST['product_id'];
        parent::remove_price();
    }

    /**
     * 门票上下架
     */
    public function SetTicketStatus()
    {
        $pid    = I('post.product_id', 0, 'intval');
        $status = I('post.status', 0, 'intval');
        $modelTicket = new Ticket();
        $ret = $modelTicket->SetTicketStatus(0, $status, $this->memberID, $pid);
        self::apiReturn($ret['code'],[], $ret['msg']);
    }
}