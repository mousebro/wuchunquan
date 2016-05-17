<?php

namespace Controller\Mall;

use Library\Controller;
use Model\Product\Ticket;

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

        $TicketModel = new Ticket();

        $storages = $TicketModel->getMuchStorage($pids, I('date') ?: date('Y-m-d'));

        foreach ($pids as $key => $pid) {
            if (!isset($storages[$pid])) {
                $storages[$pid] = 0;
            }
        }

        $this->apiReturn(200, $storages, '');

    }

}