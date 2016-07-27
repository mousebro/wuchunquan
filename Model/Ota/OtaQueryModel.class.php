<?php
/**
 * ota用到的model类
 * @since 2016年7月22日
 */
namespace Model\Ota;
use Library\Model;

class OtaQueryModel extends Model {

    private $_uu_land = 'uu_land';
    private $_pft_member = 'pft_member';
    private $_uu_jq_ticket = 'uu_jq_ticket';
    private $_pft_csys_landid = 'pft_csys_landid';
    private $_uu_qunar_use = 'uu_qunar_use';
    private $_uu_ss_order = 'uu_ss_order';

    /**
     * 根据salerid获取terminal（uu_land表）
     * @param int salerid
     * @return string
     */
    public function getTerminalBySalerId($salerId) {
        $params = array(
            'salerid' => $salerId,
        );
        $res = $this->table($this->_uu_land)->where($params)->limit(1)->getField('terminal');
        if (!$res) {
            return false;
        }
        return $res;
    }


    public function getOtaToConfigureByFidDid($fid = '',$Did, $sup = '', $field = ''){
        if ($fid) {
            $params['fid'] = $fid;
        }
        if ($Did) {
            $params['DockingMode'] => $Did;
        }
        if ($sup) {
            $params['supplierIdentity'] => $sup;
        }
        if (!$field) {
            $field = 'supplierIdentity,signkey';
        }
        $res = $this->Table($this->_uu_qunar_use)
                    ->where($params)
                    ->field($field)
                    ->limit(1)
                    ->find();
        if (!$res) {
            return false;
        }
        return $res;
    }

    /**
     * 通过id获取Mobile（pft_member表）
     * @param int id
     * @return string
     */
    public function getMobileById($id) {
        $params = array(
            'id' => $id,
        );
        $res = $this->table($this->_pft_member)->where($params)->limit(1)->getField('mobile');
        if ($res) {
            return false;
        }
        return $res;
    }


    /**
     * @param $field
     * @param $tid
     * @param $join
     * @return bool|mixed
     */
    public function selectInfoByIdInTicket($field, $tid, $join = ''){
        if (empty($tid) || !is_numeric($tid)) {
            return false;
        }
        $params = array(
            't.id' => $tid,
        );
        $table = $this->_uu_jq_ticket.' t';
        $query_obj = $this->table($table);
        if ($join) {
            $query_obj->join($join);
        }
        $res = $query_obj->field($field)->where($params)->limit(1)->find();
        if (empty($res)) {
            return false;
        }
        return $res;
    }

    /**
     * 获取
     * @param int $lid
     * @return array
     *
     */
    public function getCsysid($lid) {
        $table = $this->_pft_csys_landid.' C';
        $params = array(
            'C.lid' => $lid,
            'C.status' => 0
        );
        $res = $this->table($table)->join('left join pft_con_sys S on C.csysid = S.id')
                                   ->where($params)
                                   ->limit(1)
                                   ->find();
        if (!$res) {
            return false;
        }
        return $res;
    }

    /**
     * 通用的获取信息的方法
     *
     */
    public function getInfo($table, $field = '*', $joinTable = '', $filter = '', $order = '') {
        if (empty($table)) {
            return false;
        }
        $res = $this->table($table)->field($field);
        if ($joinTable) {
            $res = $res->join($joinTable);
        }
        if ($filter) {
            $res = $res->where($filter);
        }
        if ($order) {
            $res = $res->order($order);
        }
        $res = $res->limit(1)->find();
        return $res;
    }

    /**
     * 从uu_ss_order表获取单条信息
     * 
     *
     */
    public function getInfoInUussorder($field = '*', $filter = array()) {
        if (!is_string($field) || !is_array($filter)) {
            return false;
        }
        $res = $this->table($this->_uu_ss_order)->field($field)->where($filter)->find();
        return $res;
    }

    /**
     * 插入数据
     * 
     */
    public function insertTable($table, $data) {
        if (!is_string($table) || !is_array($data)) {
            return false;
        }
        $res = $this->table($table)->data($data)->add();
        return $res;
    }
}