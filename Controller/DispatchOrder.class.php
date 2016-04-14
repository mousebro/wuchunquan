<?php
/**
 * User: Fang
 * Time: 14:45 2016/3/8
 */
namespace Controller;
use Library\Controller;
use Model\Member\MemberRelationship;

class DispatchOrder extends Controller{
    private $memberId;
    public function __construct()
    {
        $this->memberId=$this->isLogin('ajax');
    }

    public function getDistributor(){
        $search = strval(I('param.search'));
        $page = intval(I('param.page'));
        $limit = intval(I('param.limit'));
        if(!$page) $page = 1;
        $limit = $page ? ($limit ? $limit : 20) :9999;
        $relationModel = new MemberRelationship($this->memberId);
        $distributor = $relationModel -> getDistributor($search,$page,$limit);
        $total = $relationModel -> getDistributor($search,$page,$limit,true);
        if(is_array($distributor) && count($distributor)>0){
            $data = array(
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "list" => $distributor
            );

        }else{
            $data = array(
                "page" => $page,
                "limit" => $limit,
                "total" => 0,
                "list" => []
            );
        }
        $this->apiReturn(200,$data,'操作成功');
    }
}
