<?php
/**
 * User: Fang
 * Time: 14:45 2016/3/8
 */
namespace Controller;

use Library\Controller;
use Model\Member\Member;
use Model\Member\MemberRelationship;

class DispatchOrder extends Controller
{
    private $memberId;

    public function __construct()
    {
        $this->memberId = $this->isLogin('ajax');
        //        $this->checkAuth($this->memberId);
    }

    /**
     * 获取分销商列表
     */
    public function getDistributor()
    {
        $search = strval(I('param.search'));
        $page   = intval(I('param.page'));
        $limit  = intval(I('param.limit'));
        if ( ! $page) {
            $page = 1;
        }
        $limit         = $page ? ($limit ? $limit : 20) : 9999;
        $relationModel = new MemberRelationship($this->memberId);
        $distributor   = $relationModel->getDistributor($search, $page, $limit);
        $total         = $relationModel->getDistributor($search, $page, $limit, true);
        if (is_array($distributor) && count($distributor) > 0) {
            $data = array("page" => $page, "limit" => $limit, "total" => $total, "list" => $distributor);
        } else {
            $data = array("page" => $page, "limit" => $limit, "total" => 0, "list" => []);
        }
        $this->apiReturn(200, $data, '操作成功');
    }

    /**
     * 检查是否有计调下单权限
     * @param $memberId
     */
    //    private function checkAuth($memberId)
    //    {
    //        if (in_array($memberId, [3385, 6197,57675])) {
    //            return;
    //        }
    //        $memberModel = new Member();
    //        $member_info = $memberModel->getMemberInfo($memberId);
    //        if ($member_info) {
    //            $member_group = $member_info['group_id'];
    //            if (in_array($member_group, [2,4])) {
    //                return;
    //            }
    //        }
    //        $this->apiReturn(204,[],'当前账号无计调下单权限');
    //    }
}
