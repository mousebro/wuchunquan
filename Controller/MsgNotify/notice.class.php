<?php
/**
 * User: Fang
 * Time: 11:15 2016/5/25
 */
namespace Controller\MsgNotify;

use Library\Controller;
use Library\Exception;
use Model\Notice\Announce;

class notice extends Controller
{
    //获取重要公告
    public function get_nts()
    {
        $this->isLogin('ajax');
        try {
            if ( ! ($memberId = session('memberID'))) {
                throw new Exception('非法访问', '201');
            };
            $ntcModel = new Announce();
            $ntc      = $ntcModel->get_rcnt_nts();
            if ($ntc && ! $ntcModel->is_read($memberId, $ntc['an_id'])) {
                $data = [
                    'an_id'   => $ntc['an_id'],
                    'title'   => $ntc['title'],
                    'details' => $ntc['details'],
                ];
                $this->apiReturn('200', $data, '有重要公告');
            } else {
                $this->apiReturn('202', [], '无未读公告');
            }
        } catch (Exception $e) {
            $this->apiReturn($e->getCode(), [], $e->getMessage());
            \pft_log('announce/err', $e->getCode() . "|" . $e->getMessage());
        }

    }

    //已读重要公告
    public function read_nts()
    {
        $this->isLogin('ajax');
        $memberId = session('memberID');
        $an_id    = intval(I('an_id'));
        if ($an_id && $memberId) {
            $ntcModel = new Announce();
            $ntcModel->add_read($memberId, $an_id);
        }
        $this->apiReturn('200');
    }

//done：测试专用，必删
// http://www.12301.test/route/?c=MsgNotify_notice&a=test
//    public function test()
//    {
//        $_SESSION['sid']      = 57675;
//        $_SESSION['memberID'] = 57675;
//        //        $this->get_nts();
//        $this->read_nts();
//    }
}