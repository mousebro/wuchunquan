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
        $res = $this->table($table)->data($params)->add();
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
     * 查询all_api_order 订单信息，订单唯一
     * @param array $map 查询条件订单号3者其一
     * @return bool|mixed
     */
    public function queryOtaOrder( Array $map)
    {
        if (!isset($map['tempOrder']) && !isset($map['pftOrder']) && !isset($map['apiOrder']) ){
            return false;
        }
        $res = $this->table($this->_all_api_order)->field('*')->where($map)->find();
        if (empty($res)) {
            return false;
        }
        return $res;
    }

}