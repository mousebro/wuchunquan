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
}