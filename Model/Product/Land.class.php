<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 3/3-003
 * Time: 9:25
 *
 * 产品模型
 */

namespace Model\Product;
use Library\Model;

class Land extends Model
{
    private $_landExtTable = 'uu_land_f';

    /**
     * 生成并获取终端ID
     * @author Guangpeng Chen
     * @date 2016-03-03 09:05:39
     * @return int
     */
    public  function getTerminalId() {
        return $this->table('pft_terminal_id')->add(['id'=>'null']);
    }

    /**
     * 根据产品ID获取产品扩展信息
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $pid 产品ID
     * @return
     */
    public function getExtFromPid($pid, $field = false){
        if(!$pid) {
            return false;
        }

        if(!$field) {
            $field = '*';
        }

        $extInfo = $this->table($this->_landExtTable)->where(['pid' => $pid])->field($field)->find();
        if(!$extInfo) {
            return false;
        } else {
            return $extInfo;
        }
    }

    public function getLandInfo($lid, $extra = false, $field = '*') {
        if ($extra) {
           $land_info = $this->table('uu_land')
                            ->join('land left join uu_land_f f on land.id=f.lid')
                            ->where(array('land.id' => $lid))
                            ->field($field)
                            ->find();
        } else {
            $land_info = $this->table('uu_land')->field($field)->find($lid);
        }
        return $land_info;
    }

    /**
     * 获取产品所属的景区id
     * @param  [type] $pid [description]
     * @return [type]      [description]
     */
    public function getLandIdByPid($pid) {
        return $this->table('uu_products')->where(['id' => $pid])->getField('contact_id');
    }

    public function AddProduct(Array $params)
    {
        $params['terminal']      = self::getTerminalId();
        $params['terminal_type'] = 1;
        return $this->table('uu_land')->data($params)->add();
    }
}