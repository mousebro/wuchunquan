<?php

namespace Model\Ota;
use Library/Model;
/**
 * all_api_order表的Model 放在pft001库
 * @since 2016-07-22
 * @author liubb
 */
class AllApiOrderModel extends Model {


    private $_all_api_order = 'all_api_order';

    public function __construct() {
        parent::__construct('pft001');
    }

    /**
     * 通过pftOrder更新all_api_order表的oStatus，handleStatus
     * 只更新一条
     * @param
     * @return int | boolen
     */
    public function updateStatusByOrder($oStatus = 1, $handleStatus = 0, $pftOrder) {
        if (!is_numeric($oStatus) || !is_numeric($handleStatus)) {
            return false;
        }
        $params = array(
            'pftOrder' => $pftOrder,
        );
        $data = array(
            'oStatus' => $oStatus,
            'handleStatus' => $handleStatus,
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
        $res = $this->table($table)->add($params);
        return $res;
    }
}