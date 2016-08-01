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
     * 插入数据库
     * @param string $table 表名
     * @param array  $params array("key" => "value") 对应的字段名和值
     * @return int | boolen
     */
    public function insertData($table, $params) {
        if (!is_string($table) || !is_array($params)) {
            return false;
        }
        if ($table == 'all_api_order') {
            if (isset($params['tempOrder'])) {
                $data = array('tempOrder' => $params['tempOrder']);
            }
            $res = $this->table($table)->where($data)->select();
            if ($res) {
                $this->table($table)->where($data)->save($params);
            } else {
                $this->table($table)->data($params)->add();
            }
        } else {
            $res = $this->table($table)->data($params)->add();
        }
        return $res;
    }


    public function updateOtaOrder(Array $map,Array $data){
        if (!isset($map['tempOrder']) && !isset($map['pftOrder']) && !isset($map['apiOrder']) ){
            return false;
        }
        $res = $this->table($this->_all_api_order)->where($map)->limit(1)->save($data);
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
     * 查询all_api_order信息的通用方法
     * 只获取第一条信息
     * @param string $field
     * @param array $filter
     * @return array
     * @author liubb
     */
    public function getInfo($field = '*', $filter = array(), $order = '') {
        if (!is_string($field) || !is_array($filter)) {
            return false;
        }
        $res = $this->table($this->_all_api_order)->field($field)->where($filter);
        if ($order) {
            $res = $res->order($order)->limit(1)->find();
        }
        $res = $res->limit(1)->find();
        return $res;
    }

    /**
     * 获取全部满足条件的数据
     * @return array
     */
    public function selectInfo($field, $filter) {
        if (!is_string($field) || !is_array($filter)) {
            return false;
        }
        $res = $this->table($this->_all_api_order)->field($field)->where($filter)->select();
        return $res;
    }

    /**
     * all_api_order表通过的更新方法
     * @param array $data
     * @param array $filter
     * @return int | boolen
     * @author liubb
     */
    public function updateTable($data, $filter) {
        if (!is_array($data) || !is_array($filter)) {
            return false;
        }
        $res = $this->table($this->_all_api_order)->where($filter)->limit(1)->save($data);
        return $res;
    }
}