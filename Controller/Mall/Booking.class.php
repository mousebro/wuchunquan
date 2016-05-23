<?php

namespace Controller\Mall;

use Library\Controller;
use Model\Product\Ticket;
use Model\Member\Member;

class Booking extends Controller {

    /**
     * 获取产品库存
     * @return [type] [description]
     */
    public function getStorage() {
        $pids = explode('-', I('pids'));

        if (count($pids) == 0) {
            $this->apiReturn(400, [], '参数错误');
        }

        if (I('date')) {
            $today_timestamp = strtotime(date('Y-m-d'));
            $submit_timestamp = strtotime(I('date'));

            if ($submit_timestamp < $today_timestamp) {
                $this->apiReturn(400, [], '请选择正确的日期');
            }
        }

        $TicketModel = new Ticket();
        $MemberModel = new Member();

        $memberid = $aid = 0;

        $shop_account = (int)explode('.', $_SERVER['HTTP_HOST'])[0];
        $shop_own = $MemberModel->getMemberInfo($shop_account, 'account');
        $shop_id = $shop_own['id'];

        $storages = [];
        foreach ($pids as $pid) {
            if (!$TicketModel->isSelfApplyProduct($shop_id, $pid)) {
                $tinfo = $TicketModel->getTicketInfoByPid($pid);
                $memberid = $shop_id;
                $aid = $tinfo['apply_did'];
            } else {
                //分销商库存
                if (isset($_SESSION['dtype']) && in_array($_SESSION['dtype'], [0,1])) {
                    $memberid = $_SESSION['memberID'];
                    $aid = I('aid', '', 'intval');
                } else {
                    $memberid = $aid = I('aid', '', 'intval');
                }
            }
            $storage = $TicketModel->getMuchStorage([$pid], I('date') ?: date('Y-m-d'), $memberid, $aid);
            $storages[$pid] = (int)$storage[$pid];

        }

        $this->apiReturn(200, $storages, '');

    }


}