<?php

/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/11-011
 * Time: 9:26
 */
namespace Controller;

use \Library\Controller;
class OrderTrack extends Controller
{
    const KEY = '12306';
    public function __construct()
    {
        $this->model = new \Model\Order\OrderTrack();
    }

    private function verify($ordernum, $token)
    {
        if (md5(strrev($ordernum))!=$token)
            $this->apiReturn(203,[], 'Auth Error');
    }
    public function write()
    {
        $data = file_get_contents('php://input');
        if (empty($data)) {
            $this->apiReturn(201,[], 'empty data');
            return false;
        }
        $json = json_decode(base64_decode($data));
        //data verify
        $action_list = array_keys($this->model->getActionList());
        $source_list = array_keys($this->model->getSourceList());
        if (!$json->ordernum)  $this->apiReturn(202,[], '订单号不能为空');

        $this->verify($json->ordernum, $json->token);

        if (!in_array((int)$json->source, $source_list)) {
            $this->apiReturn(
                202,[], '来源错误'
            );
        }
        if (!in_array($json->action, $action_list)) {
            $this->apiReturn(
                202,[], 'action error'
            );
        }
        $ret = $this->model->addTrack(
            $json->ordernum,
            $json->action,
            $json->tid,
            $json->tnum,
            $json->left_num,
            $json->source,
            $json->terminal_id,
            $json->branch_terminal,
            $json->id_card,
            $json->oper
            );
        if ($ret>0) $this->apiReturn(200,[],'success');
        $this->apiReturn(201,[], 'add error');
    }
    public function getLog()
    {
        $ordernum = I('get.ordernum');
        $token    = I('get.token');
        $this->verify($ordernum, $token);
        $log      = $this->model->getLog($ordernum);
        $this->apiReturn(200, $log, 'success');
    }
}