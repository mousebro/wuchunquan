<?php
/**
 * 演出类产品分销商库存控制器
 *
 * @author dwer
 * @date 2016-01-03
 * 
 */
namespace Controller\product;

use Library\Controller;

class storage extends Controller{
    private $memberId = null;

    public function __construct() {
        $this->memberId = $this->isLogin('ajax');
    }

    /**
     * 获取默认的分销商列表
     * @author dwer
     * @date   2016-03-15
     *
     * @return
     */
    public function getListDefault() {
        $areaId   = $this->getParam('area_id');
        $memberId = $this->memberId;
        $venusId  = $this->getParam('venus_id');

        if(!$areaId || !$venusId) {
            $this->apiReturn(203, '', '参数错误');
        }

        $yxModel  = $this->model('Product/YXStorage');
        $tmp      = $yxModel->getResellerList($memberId);
        
        $list     = array();
        foreach($tmp as $item) {
            $resellerId = $item['son_id'];

            //获取用户的分销数量
            $storageTmp = $yxModel->totalDefaultNumber($resellerId, $areaId);
            $item['total_num'] = $storageTmp;

            $list[] = array(
                'name'       => $item['dname'],
                'id'         => $item['son_id'],
                'account'    => $item['account'],
                'total_num'  => $item['total_num'],
            );
        }

        //获取默认的汇总数据
        $storageInfo  = $yxModel->getDefaultInfo($areaId);
        $status = 1; 
        if($storageInfo) {
            $status = $storageInfo['status'];
        }

        //获取综合数据
        $summary = array();
        $tmp     = $yxModel->getDefaultSummary($venusId, $areaId);

        //处理数据
        $summary = array(
            'total'  => $tmp['total'],
            'status' => $status
        );

        $data = array(
            'summary' => $summary,
            'list'    => $list
        );

        $this->apiReturn(200, $data);
    }

    /**
     * 获取具体场次下面的分销商列表  
     * @author dwer
     * @date   2016-03-15
     *
     * @param  [type] $memberSID
     * @return [type]
     */
    public function getList() {
        $roundId = $this->getParam('round_id');
        $areaId  = $this->getParam('area_id');
        $venusId = $this->getParam('venus_id');
        $memberId = $this->memberId;

        if(!$roundId || !$areaId || !$venusId) {
            $this->apiReturn(203, '', '参数错误');
        }

        //获取分销商数据
        $storageModel  = $this->model('Product/YXStorage');

        //判断是否配置了具体场次的分销商库存信息
        $storageInfo    = $storageModel->getInfo($areaId, $roundId);
        $isUseDefault   = $storageInfo ? false : true;

        //获取默认的配置
        if(!$storageInfo) {
            $storageInfo  = $storageModel->getDefaultInfo($areaId);
        }

        //如果之前都没有设置，默认是1
        $status = $storageInfo ? $storageInfo['status'] : 1;

        $tmp = $storageModel->getResellerList($memberId);

        //已经分配的数量
        $allocatedNum = 0;

        //获取分销商的销售数据
        $list     = array();
        foreach($tmp as $item) {
            $resellerId = $item['son_id'];

            //获取用户的分销数量
            if($isUseDefault) {
                //默认配置
                $storageTmp = $storageModel->totalDefaultNumber($resellerId, $areaId);
            } else {
                //针对场次配置
                $storageTmp = $storageModel->totalNumber($resellerId, $roundId, $areaId);
            }
            $item['total_num'] = $storageTmp;

            $sales = $storageModel->getResellerNums($roundId, $areaId, $resellerId);
            $sales = intval($sales);

            $list[] = array(
                'name'       => $item['dname'],
                'id'         => $item['son_id'],
                'account'    => $item['account'],
                'total_num'  => $item['total_num'],
                'selled_num' => $sales
            );

            if($item['total_num'] > 0) {
                $allocatedNum += $item['total_num'];
            }
        }

        //获取综合数据
        $summary = array();
        $tmp = $storageModel->getSummary($roundId, $areaId);

        //未分配数量
        $unallocated = ($tmp['total'] - $allocatedNum ) >= 0 ? ($tmp['total'] - $allocatedNum) : 0;

        //处理数据
        $summary = array(
            'total'       => $tmp['total'] + $tmp['reserve'],
            'selled'      => $tmp['saled'],
            'reserve'     => $tmp['reserve'],
            'unallocated' => $unallocated,
            'status'      => $status
        );

        $data = array(
            'summary' => $summary,
            'list'    => $list,
        );

        $this->apiReturn(200, $data);
    }

    /**
     * 获取默认的分区配置信息
     * @author dwer
     * @date   2016-03-15
     *
     * @return 
     */
    public function getConfigDefault() {
        $venusId  = $this->getParam('venus_id');
        $memberId = $this->memberId;

        if(!$venusId) {
            $this->apiReturn(203, '', '参数错误');
        }

        $yxModel  = $this->model('Product/YXStorage');
        $tmp      = $yxModel->getAreaList($venusId);

        $areaList = array();
        foreach($tmp as $item) {
            $areaList[] = array('id' => $item['id'], 'name' => $item['zone_name']);
        }

        $data = array(
            'area_list' => $areaList
        );

        $this->apiReturn(200, $data);
    }


    /**
     * 获具体场次下的分区配置信息
     * @author dwer
     * @date   2016-03-15
     *
     * @return 
     */
    public function getConfig() {
        $date    = $this->getParam('date');
        $venusId = $this->getParam('venus_id');

        if(!$date || !$venusId) {
            $this->apiReturn(203, '', '参数错误');
        }

        //加载模型
        $storageModel = $this->model('Product/YXStorage');

        //获取分区
        $tmp = $storageModel->getAreaList($venusId);
        $areaList = array();
        foreach($tmp as $item) {
            $areaList[] = array('id' => $item['id'], 'name' => $item['zone_name']);
        }

        //获取所有的场次
        $tmp = $storageModel->getRoundList($venusId, $date);
        $roundList = array();
        foreach($tmp as $item) {
            $roundList[] = array('id' => $item['id'], 'name' => $item['round_name']);
        }

        $data = array(
            'round_list' => $roundList,
            'area_list' => $areaList
        );

        $this->apiReturn(200, $data);
    }

    /**
     * 默认情况下设置分销商库存
     * @author dwer
     * @date   2016-03-16
     *
     * @return [type]
     */
    public function setListDefault() {
        $data       = $this->getParam('data');
        $areaId     = $this->getParam('area_id');
        $status     = intval($this->getParam('status'));
        $memberId   = $this->memberId;

        if(!$areaId || !$data || !in_array($status, [0, 1])) {
            $this->apiReturn(203, '', '参数错误');
        }

        $data = @json_decode($data);
        if(!$data) {
            $this->apiReturn(203, '', '设置数据错误');
        }

        //加载模型
        $storageModel = $this->model('Product/YXStorage');

        //获取分区详情
        $zoneInfo = $storageModel->getZoneInfo($areaId);
        if(!$zoneInfo) {
            $this->apiReturn(203, '', '分区数据错误');
        }
        $venusId = $zoneInfo['venue_id'];

        //根据场馆ID获取供应商ID
        $venusInfo = $storageModel->getVenusInfo($venusId);
        if(!$venusInfo) {
            $this->apiReturn(203, '', '分区数据错误');
        }
        $setterId = $venusInfo['apply_did'];

        //获取分销商的数据，核实数据是否合法
        $resellerListArr = [];
        $tmp             = $storageModel->getResellerList($memberId);

        foreach($tmp as $item) {
            $resellerListArr[] = $item['son_id'];
        }

        $allocateNum = 0;
        $resData     = array();

        foreach($data as $item) {
            $item = is_array($item) ? $item : (array)$item;

            if(isset($item['reseller_id']) && $item['reseller_id'] && in_array($item['reseller_id'], $resellerListArr)) {
                $totalNum = isset($item['total_num']) ? intval($item['total_num']) :  0;
                $totalNum = $totalNum < -1 ? -1 : $totalNum;

                $resData[$item['reseller_id']] = $totalNum;

                if($totalNum > 0) {
                    $allocateNum += $totalNum;
                }
            }
        }

        if(!$resData) {
            $this->apiReturn(203, '', '设置数据错误');
        }

        //判断总是是不是超过
        $allSeatsArr = $storageModel->getDefaultSummary($venusId, $areaId);
        $allSeats    = $allSeatsArr['total'];

        if($allocateNum > $allSeats) {
            $this->apiReturn(204, '', '保留库存之和超过总库存');
        }

        $res = $storageModel->setDefaultResellerStorage($areaId, $resData, $status, $setterId);

        if($res) {
            $this->apiReturn(200, array());
        } else {
            $this->apiReturn(500, array(), '服务器错误');
        }
    }

    /**
     * 具体场次下设置分销商库存
     * @author dwer
     * @date   2016-03-16
     *
     * @return [type]
     */
    public function setList() {
        $data       = $this->getParam('data');
        $roundId    = $this->getParam('round_id');
        $areaId     = $this->getParam('area_id');
        $status     = intval($this->getParam('status'));
        $memberId   = $this->memberId;

        if(!$roundId || !$areaId || !$data || !in_array($status, [0, 1])) {
            $this->apiReturn(203, '', '参数错误');
        }

        $data = @json_decode($data);
        if(!$data) {
            $this->apiReturn(203, '', '设置数据错误');
        }

        //加载模型
        $storageModel = $this->model('Product/YXStorage');

        //获取分区详情
        $roundInfo = $storageModel->getRoundInfo($roundId);
        if(!$roundInfo) {
            $this->apiReturn(203, '', '场次数据错误');
        }
        $venusId  = $roundInfo['venue_id'];
        $useDate  = $roundInfo['use_date'];
        $useDate  = intval(str_replace('-', '', $useDate));
        $setterId = $roundInfo['opid'];

        //获取分销商的数据，核实数据是否合法
        $resellerListArr = [];
        $tmp             = $storageModel->getResellerList($memberId);

        foreach($tmp as $item) {
            $resellerListArr[] = $item['son_id'];
        }

        $resData = array();
        $allocateNum = 0;

        foreach($data as $item) {
            $item = is_array($item) ? $item : (array)$item;

            if(isset($item['reseller_id']) && $item['reseller_id'] && in_array($item['reseller_id'], $resellerListArr)) {
                $totalNum = isset($item['total_num']) ? intval($item['total_num']) :  0;
                $totalNum = $totalNum < -1 ? -1 : $totalNum;
                $resData[$item['reseller_id']] = $totalNum;

                if($totalNum > 0) {
                    $allocateNum += $totalNum;
                }
            }
        }

        if(!$resData) {
            $this->apiReturn(203, '', '设置数据错误');
        }

        //判断总是是不是超过
        $allSeatsArr = $storageModel->getRoundSeats($roundId, $areaId);
        $allSeats    = $allSeatsArr['total'];

        if($allocateNum > $allSeats) {
            $this->apiReturn(204, '', '保留库存之和超过总库存');
        }

        $res = $storageModel->setResellerStorage($roundId, $areaId, $resData, $status, $useDate, $setterId);
        if($res) {
            $this->apiReturn(200, array());
        } else {
            $this->apiReturn(500, array(), '服务器错误');
        }
    }

    /**
     * 开启场次的分销库存
     * @author dwer
     * @date   2016-03-16
     *
     * @return [type]
     */
    public function open() {
        $roundId = $this->getParam('round_id');
        $areaId  = $this->getParam('area_id');

        if(!$roundId || !$areaId) {
            $this->apiReturn(203, '', '参数错误');
        }

        //加载模型
        $storageModel = $this->model('Product/YXStorage');

        //获取场次信息
        $roundInfo = $storageModel->getRoundInfo($roundId);
        if(!$roundInfo) {
            $this->apiReturn(203, '', '场次参数错误');
        }
        $setterId = $roundInfo['opid'];

        //判断是否配置了具体场次的分销商库存信息
        $storageInfo    = $storageModel->getInfo($areaId, $roundId);
        $isUseDefault   = $storageInfo ? false : true;

        if($isUseDefault) {
            $res = $storageModel->copyDataFromDefault($areaId, $roundId);

            if(!$res) {
                $this->apiReturn(205, '', '初始化场次的分销库存数据错误');
            }
        }

        $res = $storageModel->setInfo($roundId, $areaId, $setterId, 1);

        if($res) {
            $this->apiReturn(200, array());
        } else {
            $this->apiReturn(500, array(), '服务器错误');
        }
    }

    /**
     * 关闭场次的分销库存
     * @author dwer
     * @date   2016-03-16
     *
     * @return [type]
     */
    public function close() {
        $roundId = $this->getParam('round_id');
        $areaId  = $this->getParam('area_id');

        if(!$roundId || !$areaId) {
            $this->apiReturn(203, '', '参数错误');
        }

        //加载模型
        $storageModel = $this->model('Product/YXStorage');

        //获取场次信息
        $roundInfo = $storageModel->getRoundInfo($roundId);
        if(!$roundInfo) {
            $this->apiReturn(203, '', '场次参数错误');
        }
        $setterId = $roundInfo['opid'];

        //判断是否配置了具体场次的分销商库存信息
        $storageInfo    = $storageModel->getInfo($areaId, $roundId);
        $isUseDefault   = $storageInfo ? false : true;

        if($isUseDefault) {
            $res = $storageModel->copyDataFromDefault($areaId, $roundId);

            if(!$res) {
                $this->apiReturn(205, '', '初始化场次的分销库存数据错误');
            }
        }

        $res = $storageModel->setInfo($roundId, $areaId, $setterId, 0);

        if($res) {
            $this->apiReturn(200, array());
        } else {
            $this->apiReturn(500, array(), '服务器错误');
        }
    }

    //测试使用
    public function test() {
        //加载模型
        //$storageModel = $this->model('Product/YXStorage');

        //获取库存
        //$res = $storageModel->getResellerStorage('1000190', '4508', 550);
        //var_dump($res);

        //消耗库存
        //$res = $storageModel->useStorage('4443384', '1000190', '4508', '550', 3);
        //var_dump($res);


        //修改库存  changeStorage($orderNum, $reducedNum)
        //$res = $storageModel->changeStorage('4443384', 1);
        //var_dump($res);

        //取消订单
        //$res = $storageModel->recoverStorage('4443384');
        //var_dump($res);

        //清除分销商的数据
        //$res = $storageModel->removeReseller('1000026', '1000026', '25451');
        //var_dump($res);
    }
}




