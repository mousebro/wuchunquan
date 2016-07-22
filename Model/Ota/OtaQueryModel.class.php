<?php
/**
 * ota用到的model类
 * @since 2016年7月22日
 * @author liubb
 */
namespace Model\Ota;
use Library\Model;

class OtaQueryModel extends Model {

    private $_uu_land = 'uu_land';
    private $_pft_member = 'pft_member';
    private $_uu_jq_ticket = 'uu_jq_ticket';
    private $_pft_csys_landid = 'pft_csys_landid';

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
     * 通过id获取最新的单条信息(uu_jq_ticket表)
     * @param string $field 要获取的字段名
     * @param string $pftOrder
     * @param int $start
     * @param int $limit
     * @param string $orderby
     * @return array
     */
    public function selectInfoByIdInTicket($field = '*', $id, $start = 0, $limit = 15, $orderby = '') {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }
        $params = array(
            'id' => $id,
        );
        $res = $this->table($this->_uu_jq_ticket)->field($field)->where($params)->limit($start, $limit);
        if ($orderby) {
            $res = $res->order($order);
        }
        $res = $res->find();
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
            'c.lid' => $lid,
            'c.status' => 0,
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
}