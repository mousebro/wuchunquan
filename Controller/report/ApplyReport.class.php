<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/23-023
 * Time: 9:29
 */

namespace Controller\report;
use Library\Controller;
use Model\Report\ApplyerReport;

class ApplyReport extends Controller
{
    public function __construct()
    {
        $this->model = new ApplyerReport();
    }

    private function monthCount()
    {
        //{"status":"ok","xAxis":["iPad","HuaweiPE-CL00"," "],"series":[{"name":"\u6d3b\u8dc3\u73a9\u5bb6","type":"bar","smooth":true,"data":["1","2","9"]}],
        //"legend":{"data":["iPad","HuaweiPE-CL00"," "]},"total":12}
        $top   = I('get.top', 10, 'intval');
        $group = I('get.group', 1, 'intval');
        $order = I('get.order', 1, 'intval');
        $data  = $this->model->MonthCount($top, $group, $order);
        $output = [
            'series'=>[
                'name'  =>'月销量统计',
                'type'  =>'bar',
                'smooth'=>true
            ]
        ];
        foreach ($data as $item) {
            $output['xAxis'][] = $item['title'];
            $output['legend']['data'][] = $item['title'];
            $output['series']['data'][] = $item['cnt'];
        }

        self::ajaxReturn($output, 'json', JSON_UNESCAPED_UNICODE);
    }

    public function index()
    {
        //统计的数据，1：30日销量排行
        $dataType   = I('get.data_type');
        if ($dataType==1) {
            $this->monthCount();
        }
        $day1       = I('get.start_day', 0, 'intval');
        $day2       = I('get.end_day', 0, 'intval');
        $uid        = I('get.uid', 0,'intval');
        $lid        = I('get.lid',0, 'intval');
        $dnames     = $this->model->GetTitleList(1, 20160501, 20160522);
        $products   = $this->model->GetTitleList(2, 20160501, 20160522);
        //print_r($data);
    }
}