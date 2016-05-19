<?php
/**
 * User: Fang
 * Time: 11:14 2016/5/17
 */
namespace Controller\Finance;

use Library\Controller;

class TradeRecord extends Controller{
    //获取交易记录详情
    //testurl：www.12301.local/route/?c=Finance_TradeRecord&a=getDetails&trade_id=6481477
    //testurl：www.12301.test/route/?c=Finance_TradeRecord&a=getDetails&trade_id=6481477
    public function getDetails(){
        $memberId = $this->isLogin('ajax');
        $recordModel = new \Model\Finance\TradeRecord();
        $trade_id = \safe_str(I('trade_id'));
        if($trade_id){
            $record = $recordModel->getDetails($trade_id);
        }
        if(!empty($record['fid']) && !empty($record['aid'])){
            if(!in_array($memberId,[1,$record['fid'],$record['aid']])){
                $this->apiReturn(201,[],'无权查看');
            }else{
                unset($record['fid']);
                unset($record['aid']);
            }
        }else{
            $record = [];
        }
        $this->apiReturn(200,$record,'操作成功');
    }
    //获取交易记录列表

    public function getList(){
        $memberId = $this->isLogin('ajax');
        
        //超级管理员查看会员交易记录
        $super = (intval(I('super')) && $memberId == 1);
        
        //是否导出excel
        $excel = intval(I('excel'));
        //开始时间
        $bdate = \safe_str(I('bdate'));
        if($bdate){
            if(\chk_date($bdate)){
                $map['bdate'] = $bdate;
            }else{
                $this->apiReturn(201,[],'日期格式错误');
            }
        }

        //结束时间
        $edate = \safe_str(I('edate'));
        if($edate){
            if(\chk_date($edate)){
                $map['edate'] = $edate;
            }else{
                $this->apiReturn(201,[],'日期格式错误');
            }
        }
        //支付方式用"|"分隔
        $ptype = \safe_str(I('ptype'));
        $map['ptype'] = ['in', explode('|',$ptype)];
        
        //分页
        $page = intval(I('page'));
        $limit = intval(I('limit'));
        $page = ( $page > 0 ) ? $page : 1;
        $limit = ( $limit > 0 ) ? $limit : 15;
        
        $recordModel = new \Model\Finance\TradeRecord();
        $data = $recordModel->getList($memberId, $map, $page, $limit, $excel, $super);
        if(is_array($data)){
            $this->apiReturn(200,$data);
        }else{
            $this->apiReturn(202,[],'查询结果为空');
        }
    }

}