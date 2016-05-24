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
    private $dt = ['onum'=>'总订单量','tnum'=>'票数','money'=>'总金额(单位:万元)'];

    public function __construct()
    {
        $this->model = new ApplyerReport();
    }

    private function monthCount()
    {

        $top   = I('get.top', 10, 'intval');
        $group = I('get.group', 1, 'intval');
        $order = I('get.order', 1, 'intval');
        $data  = $this->model->MonthCount($top, $group, $order);
        $titles = $series = $json_data = [];
        foreach ($data as $item) {
            //$output['xAxis'][] = $item['title'];
            //$output['legend']['data'][] = $item['title'];
            //$output['series']['data'][] = $item['cnt'];

            $titles[] = $item['title'];
            $series['onum'][$item['gid']] = $item['onum'];
            $series['tnum'][$item['gid']] = $item['tnum'];
            $series['money'][$item['gid']] = $item['total_money'] / 100;
        }
        foreach ($series as $key=>$item) {
            $legend['data'][] = $this->dt[$key];
            $type = 'bar';
            if ($key=='money') $type = 'line';
            $json_data[] = [
                'name' => $this->dt[$key],
                'type' => $type,
                'smooth'=> true,
                'data'  => array_values($item),
            ];
        }
        //$xAxis = array_unique($date);
        self::apiReturn(self::CODE_SUCCESS, [
                'xAxis' =>$titles,
                'series'=>$json_data,
                'legend'=>$legend]
        );
    }
    public function view()
    {
        include __DIR__ . '/../../Views/crm/applyer_report.html';
    }
    public function index()
    {
        //统计的数据，1：30日销量排行
        $dataType   = I('get.data_type');
        if ($dataType==1) {
            $this->monthCount();
        }
        $date1       = I('get.start_day', date('Y-m-d', strtotime('-30 days')));
        $date2       = I('get.end_day', date('Y-m-d'));
        $day1 = date('Ymd', strtotime($date1));
        $day2 = date('Ymd', strtotime($date2));
        if ($dataType==2) {
            $dnames     = $this->model->GetTitleList(1, $day1, $day2);
            $products   = $this->model->GetTitleList(2, $day1, $day2);
            self::apiReturn(
                self::CODE_SUCCESS,
                [
                    'members'=>$dnames,
                    'products'=>$products,
                ]
            );
        }

        $uid        = I('get.uid', 0,'intval');
        $lid        = I('get.lid',0, 'intval');
        $group      = I('get.group', 0, 'intval');
        $data       = $this->model->GetOrderSummaryById($day1, $day2, $lid, $uid, $group);
        //print_r($data);
        $legend = $series = $date = $json_data = [];
        foreach ($data as $item) {
            $date[] = date('m/d', strtotime($item['sday']));
            $series['onum'][$item['sday']] = $item['onum'];
            $series['tnum'][$item['sday']] = $item['tnum'];
            $series['money'][$item['sday']] = $item['total_money'] / 100 / 100;
        }
        foreach ($series as $key=>$item) {
            $legend['data'][] = $this->dt[$key];
            $type = 'line';
            if ($key=='money') $type = 'bar';
            $json_data[] = [
                'name' => $this->dt[$key],
                'type' => $type,
                'smooth'=> true,
                'data'  => array_values($item),
            ];
        }
        $xAxis = array_unique($date);
        self::apiReturn(self::CODE_SUCCESS,
            [
                'xAxis'=>$xAxis,
                'series'=>$json_data,
                'legend'=>$legend,
            ]
        );
    }
}