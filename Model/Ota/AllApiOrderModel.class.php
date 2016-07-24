<?php

namespace Model\Ota;
use Library\Model;
/**
 * all_api_order表的Model 放在pft001库
 * @since 2016-07-22
 * @author liubb
 */
class AllApiOrderModel extends Model {


    private $_all_api_order     = 'all_api_order';
    private $_api_order_track   = 'api_order_track';

    public function __construct() {
        parent::__construct('pft001');
    }

    /**
     * 通过pftOrder更新all_api_order表
     * 只更新一条
     * @param array $data
     * @param $pftOrder
     * @return int | boolen
     */
    public function updateStatusByOrder($data, $pftOrder) {
        if (!is_array($data)) {
            return false;
        }
        $params = array(
            'pftOrder' => $pftOrder,
        );
        $this->table($this->_all_api_order)->where($params)->limit(1)->save($data);
    }

    /**
     * 插入数据库
     * @param string $table 表名
     * @param array  $params array("key" => "value") 对应的字段名和值
     * @return int | boolen
     */
    public function insertData($table, $params) {
        if (!is_string($table) || !is_array($params)) {
            return false;
        }
        $res = $this->table($table)->data($params)->add();
        return $res;
    }

    /**
     * 通过tempOrder更新pftOrder，oStnum
     * @param array $data
     * @param $tempOrder
     * @param $orderby
     * @param int $limit
     * @return
     */
    public function updateInfoByTempOrder($data, $tempOrder, $orderby = '', $limit = 1) {
        if (!is_array($data) || !is_string($orderby)) {
            return false;
        }
        $params = array(
            'tempOrder' => $tempOrder,
        );
        $res = $this->table($this->_all_api_order)->where($params);
        if ($orderby) {
            $res = $res->order($orderby);
        }
        $res = $res->limit($limit)->save($data);
        if (!$res) {
            return false;
        }
        return $res;
    }

    /**
     * 通过pftOrder更新oStnum，apiCode
     * @param $oStnum
     * @param $apiCode
     * @param pftOrder
     * @return
     */

    public function updateInfoByPftOrder( $apiCode, $pftOrder) {
        $params = array(
            'pftOrder' => $pftOrder,
        );
        $data = array(
            'apiCode' => $apiCode
        );
        $res = $this->table($this->_all_api_order)->where($params)->limit(1)->save($data);
        if (!$res) {
            pft_log('sql_error/all_api_order', 
                'errmsg:' . $this->getDbError() . ';sql:' . $this->_sql());
            return false;
        }
        return $res;
    }

    /**
     * 通过tempOrder更新pftOrder。ota订单记录表
     * @param $Ordern
     * @param $tempOrder
     * @return bool
     */
    public function updatePftOrdertrack($Ordern, $tempOrder){
        $params = array(
            'tempOrder' => $tempOrder,
        );
        $data = array(
            'pftOrder' => $Ordern
        );
        $res = $this->table($this->_api_order_track)->where($params)->limit(1)->save($data);
        if (!$res) {
             pft_log('sql_error/all_api_order', 
                'errmsg:' . $this->getDbError() . ';sql:' . $this->_sql());
            return false;
        }
        return $res;
    }

    /**
     * 通过pftOrder获取最新的单条信息
     * @param string $field 要获取的字段名
     * @param string $pftOrder
     * @param int $start
     * @param int $limit
     * @param string $orderby
     * @return array
     */
    public function selectInfoByPftOrder($field = '*', $pftOrder, $start = 0, $limit = 1, $orderby = '') {
        if (!is_string($field) || !is_string($pftOrder) || !is_numeric($start) || !is_numeric($limit) || !is_string($orderby)) {
            return false;
        }
        $params = array(
            'pftOrder' => $pftOrder,
        );
        $res = $this->table($this->_all_api_order)->field($field)->where($params)->limit($start, $limit);
        if ($orderby) {
            $res = $res->order($orderby);
        }
        $res = $res->find();
        if (empty($res)) {
            return false;
        }
        return $res;
    }

    /**
     * 通过tempOrder获取最新的单条信息
     * @param string $field 要获取的字段名
     * @param string $pftOrder
     * @param int $start
     * @param int $limit
     * @param string $orderby
     * @return array
     */
    public function selectInfoByTempOrder($field = '*', $tempOrder, $start = 0, $limit = 1, $orderby = '') {
        if (!is_string($field) || !is_string($tempOrder) || !is_numeric($start) || !is_numeric($limit) || !is_string($orderby)) {
            return false;
        }
        $params = array(
            'tempOrder' => $tempOrder,
        );
        $res = $this->table($this->_all_api_order)->field($field)->where($params)->limit($start, $limit);
        if ($orderby) {
            $res = $res->order($orderby);
        }
        $res = $res->find();
        if (empty($res)) {
            return false;
        }
        return $res;
    }
    
    public function QueryOtaOrder($field = '*', Array $map)
    {
        if (!isset($map['tempOrder']) && !isset($map['pftOrder']) ){
            return false;
        }
        $res = $this->table($this->_all_api_order)->field($field)->where($map)->find();
        if (empty($res)) {
            return false;
        }
        return $res;
    }

}