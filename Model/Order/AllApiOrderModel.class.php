<?php

/**
 * all_api_order表
 * 
 */
namespace Model\Order;
use Library\Model;

class AllApiOrderModel extends Model {

    const ALL_API_ORDER = 'all_api_order';
    //对接系统标识码 0去哪儿 20美团直连 13百度直达 21美团 22糯米 23美团V2
    private $_groupIdent = array(0,20,13,21,22,23);
    //异常订单状态码
    private $_errorsTatus = array(1, 2);
    public function __construct() {
        parent::__construct('pft001');
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
            'handleStatus' => array('in', $this->_errorsTatus),
            'coopB' => array('in', $this->_groupIdent),
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
        $count = $this->table(self::ALL_API_ORDER)->field('pftOrder,cTime,oStnum,fid,errormsg,coopB,bCode,handleStatus')
                                                 ->where($params)
                                                 ->count();
        if (!is_array($data)) {
            return false;
        }
        return array(
            'count' => $count,
            'data' => $data,
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
        $data = $this->table(self::ALL_API_ORDER)->where($params)
                                                 ->limit(1)
                                                 ->select();
        if (!is_array($data)) {
            return false;
        }
        return array('data' => $data);
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
            'cTime' => array(
                array('gt', $bTime),
                array('lt', $eTime),
            ),
            'handleStatus' => array('in', $this->_errorsTatus),
            'coopB' => array('not in', $this->_groupIdent),
        );
        $data = $this->table(self::ALL_API_ORDER)->where($params)
                                                 ->order('id desc')
                                                 ->limit($start, $size)
                                                 ->select();
        $count = $this->table(self::ALL_API_ORDER)->where($params)->count();
        if (!is_array($data)) {
            return false;
        }
        return array(
            'count' => $count,
            'data' => $data,
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
     * @param $applyDidArr 该账号自己供应的产品
     * @return 
     */
    public function getOrderNotForManager($fid, $bTime, $eTime, $start = 0, $size = 15, $applyDidArr = array()) {
        if (!is_numeric($fid) || empty($bTime) || empty($eTime) || !is_numeric($start) || !is_numeric($size) || !is_array($applyDidArr)) {
            return false;
        }
        if ($applyDidArr) {
            $condition['fid'] = $fid;
            $condition['bCode'] = array('in', $applyDidArr);
            $condition['_logic'] = 'OR';
            $params = array(
                '_complex' => $condition,
                'cTime' => array(
                    array('gt', $bTime),
                    array('lt', $eTime),
                ),
                'handleStatus' => array('in', $this->_errorsTatus),
                'coopB' => array('not in', $this->_groupIdent),
            );
        } else {
            $params = array(
                'fid' => $fid,
                'cTime' => array(
                    array('gt', $bTime),
                    array('lt', $eTime),
                ),
                'handleStatus' => array('in', $this->_errorsTatus),
                'coopB' => array('not in', $this->_groupIdent),
            );
        }

        $data = $this->table(self::ALL_API_ORDER)->where($params)
                                                 ->order('id desc')
                                                 ->limit($start, $size)
                                                 ->select();
        $count = $this->table(self::ALL_API_ORDER)->where($params)->count();
        if (!is_array($data)) {
            return false;
        }
        return array(
            'count' => $count,
            'data' => $data,
        );
    }

    /**
     * 根据获取信息
     * @param string    $field  需要获取的字段名
     * @param array     $filter 限制条件
     * @param int       $start   从第几条开始
     * @param int       $size    一次获取的条数
     * @return array
     * @author liubb
     */
    public function getListInfo($field = '*', $filter, $start = 0, $size = 15) {
        if (!is_array($filter) || !is_numeric($start) || !is_numeric($size)) {
            return false;
        }
        $data = $this->table(self::ALL_API_ORDER)->field($field)->where($filter)->limit($start, $size)->select();
        if (!$data) {
            return false;
        }
        return $data;
    }
}