<?php

namespace Model\Order;
use Library\Model;

/**
 * 异常订单 Model类
 * @author liubb
 */
class orderAbnormal extends Model {

    const UU_QUNAR_USE = 'uu_qunar_use';
    const ALL_API_ORDER = 'all_api_order';
    const UU_JQ_TICKET = 'uu_jq_ticket';
    const UU_LAND = 'uu_land';
    //对接系统标识码 0去哪儿 20美团直连 13百度直达 21美团 22糯米 23美团V2
    const GROUPON_IDENT = array(0,20,13,21,22,23);
    //异常订单状态码
    const errorStatus = array(1, 2);
    /**
     * 通过fid从uu_qunar_use表获取tid，coop_id
     * @param int $fid 登录账号的id
     * @return array
     */
    public function getTidCoopIdByFid($fid) {
        if (!isset($fid) || !is_numeric($fid)) {
            return false;
        }
        $params = array(
            'fid' => $fid,
        );
        $data = $this->table(self::UU_QUNAR_USE)->field('tid', 'coop_id')->where($params)->select();
        return $data;
    }

    /**
     * 获取第三方异常订单（管理员）
     * handleStatus in (1,2)  1失败 2超时   状态
     * coopB not in (0,20,13,21,22,23)    对接系统标识码 0去哪儿 20美团直连 13百度直达 21美团 22糯米
     * @param $bTime 开始时间 eq:2016-07-14 00:00:00
     * @param $eTime 结束时间 eq:2016-07-14 00:00:00
     * @param $start 从第几条数据开始 默认0
     * @param $size  一次性获取几条数据 默认15
     * @return 
     */
    public function getOrderForManager($bTime, $eTime, $start = 0, $size = 15) {
        if (empty($bTime) || empty($eTime) || !is_numeric($start) || !is_numeric($size)) {
            return false;
        }
        $params = array(
            'A.cTime' => array(
                array('gt', $bTime),
                array('lt', $eTime),
            ),
            'A.handleStatus' => array('in', self::errorStatus),
            'A.coopB' => array('not in', self::GROUPON_IDENT),
        );
        $table = self::ALL_API_ORDER;
        $table = $table.' A';
        $data = $this->table($table)->field('A.*, C.name')
                                        ->join('left join pft_con_sys C on A.coopB=C.coopB')
                                        ->where($params)
                                        ->order('id desc')
                                        ->limit($start, $size)
                                        ->select();
        $count = $this->table($table)->field('A.*, C.name')
                                        ->join('left join pft_con_sys C on A.coopB=C.coopB')
                                        ->where($params)
                                        ->count();
        if (!is_array($data)) {
            return false;
        }
        if (empty($data)) {
            return array(
                'count' => $count,
                'data' => $data,
            );
        }
        $ticketIdArr = array_column($data, 'bCode');
        $ticketName = $this->getTicketName($ticketIdArr);
        return array(
            'count' => $count,
            'data' => $data,
            'ticket' => $ticketName,
        );
    }

    /**
     * 获取第三方异常订单（供销商）
     * handleStatus in (1,2)  1失败 2超时   状态
     * coopB not in (0,20,13,21,22,23)    对接系统标识码 0去哪儿 20美团直连 13百度直达 21美团 22糯米
     * @param $fid   分销商ID
     * @param $bTime 开始时间 eq:2016-07-14 00:00:00
     * @param $eTime 结束时间 eq:2016-07-14 00:00:00
     * @param $start 从第几条数据开始 默认0
     * @param $size  一次性获取几条数据 默认15
     * @return 
     */
    public function getOrderNotForManager($fid, $bTime, $eTime, $start = 0, $size = 15) {
        if (!is_numeric($fid) || empty($bTime) || empty($eTime) || !is_numeric($start) || !is_numeric($size)) {
            return false;
        }
        //获取自己供应的产品
        $applyDidArr = $this->getTickIdByApplyId($fid);
        if (!is_array($applyDidArr)) {
            return false;
        }
        $condition['A.fid'] = $fid;
        $condition['A.bCode'] = array('in', $applyDidArr);
        $condition['_logic'] = 'OR';
        $params = array(
            '_complex' => $condition,
            'A.cTime' => array(
                array('gt', $bTime),
                array('lt', $eTime),
            ),
            'A.handleStatus' => array('in', self::errorStatus),
            'A.coopB' => array('not in', self::GROUPON_IDENT),
        );
        $table = self::ALL_API_ORDER;
        $table = $table.' A';
        $data = $this->table($table)->field('A.*, C.name')
                                        ->join('left join pft_con_sys C on A.coopB=C.coopB')
                                        ->where($params)
                                        ->order('id desc')
                                        ->limit($start, $size)
                                        ->select();
        $count = $this->table($table)->field('A.*, C.name')
                                        ->join('left join pft_con_sys C on A.coopB=C.coopB')
                                        ->where($params)
                                        ->count();
        if (!is_array($data)) {
            return false;
        }
        if (empty($data)) {
            return array(
                'count' => $count,
                'data' => $data,
            );
        }
        $ticketIdArr = array_column($data, 'bCode');
        $ticketName = $this->getTicketName($ticketIdArr);
        return array(
            'count' => $count,
            'data' => $data,
            'ticket' => $ticketName,
        );
    }

    /**
     * 根据供应商apply_did查询门票ID
     * @param int $fid 供应商ID
     * @return array  返回门票ID集合数组
     */
    public function getTickIdByApplyId($fid) {
        if (empty($fid) || !is_numeric($fid)) {
            return false;
        }
        $params = array(
            'apply_did' => $fid,
        );
        $data = $this->table(self::UU_JQ_TICKET)->field('id')->where($params)->select();
        if (is_array($data)) {
            $data = array_column($data, 'id');
            return $data;
        }
    }

    /**
     * 通过票类ID获取景区和门票的名称
     * @param array $arr 票的ID
     * @return 
     */
    public function getTicketName($arr) {
        if (!is_array($arr)) {
            return false;
        }
        $table = self::UU_JQ_TICKET;
        $table = $table.' T';
        $params = array(
            'T.id' => array('in', $arr),
        );
        $res = $this->table($table)->field('T.title, T.id, L.title as ltitle')
                                       ->join('left join uu_land L on L.id=T.landid')
                                       ->where($params)
                                       ->select();
        if (!is_array($res)) {
            return false;
        }
        foreach ($res as $key => $val) {
            $ticketNameArr[$val['id']] = $val['ltitle'].'('.$val['title'].')';
        }
        return $ticketNameArr;
    }

    /**
     * 获取异常订单中的团购订单
     * @param $bTime 开始时间 eq:2016-07-14 00:00:00
     * @param $eTime 结束时间 eq:2016-07-14 00:00:00
     * @param $start 从第几条开始获取 默认为0
     * @param $Size  获取的条数 默认为15
     * @param $fid 需要获取异常订单的账户的ID 默认为0 为0则取出所有异常订单
     * @return array
     */
    public function getGrouponOrder($bTime, $eTime, $start = 0, $size = 15, $fid = 0) {
        if (empty($bTime) || empty($eTime) || !is_numeric($start) || !is_numeric($size) || !is_numeric($fid)) {
            return false;
        }
        $params = array(
            'handleStatus' => array('in', self::errorStatus),
            'coopB' => array('in', self::GROUPON_IDENT),
            'cTime' => array(
                array('egt', $bTime),
                array('elt', $eTime),
            ),
        );
        if ($fid != 0) {
            $params['fid'] = $fid;
        }
        $data = $this->table(self::ALL_API_ORDER)->field('pftOrder,cTime,oStnum,fid,errormsg,coopB,bCode,handleStatus')
                                                 ->where($params)
                                                 ->limit($start, $size)
                                                 ->order('id desc')
                                                 ->select();
        // var_dump($data);exit;
        $count = $this->table(self::ALL_API_ORDER)->field('pftOrder,cTime,oStnum,fid,errormsg,coopB,bCode,handleStatus')
                                                 ->where($params)
                                                 ->count();
        if (!is_array($data)) {
            return false;
        }
        if (empty($data)) {
            return array(
                'count' => $count,
                'data' => $data,
            );
        }
        $ticketIdArr = array_column($data, 'bCode');
        $ticketName = $this->getTicketName($ticketIdArr);
        return array(
            'count' => $count,
            'data' => $data,
            'ticket' => $ticketName,
        );
    }

    /**
     * 通过订单号获取数据
     * @param int $orderId
     * @return array
     */
    public function getGrouponOrderById($orderId) {
        if (empty($orderId)) {
            return false;
        }
        $params = array(
            'pftOrder' => $orderId,
        );
        $data = $this->table(self::ALL_API_ORDER)->field('pftOrder,cTime,oStnum,fid,errormsg,coopB,bCode')
                                                 ->where($params)
                                                 ->limit(1)
                                                 ->select();
        if (!is_array($data)) {
            return false;
        }
        if (empty($data)) {
            return array(
                'data' => $data,
            );
        }
        $ticketIdArr = array_column($data, 'bCode');
        $ticketName = $this->getTicketName($ticketIdArr);
        return array(
            'data' => $data,
            'ticket' => $ticketName,
        );
    }
}