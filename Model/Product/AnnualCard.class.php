<?php

namespace Model\Product;

use Library\Cache\Cache;
use Library\Model;

class AnnualCard extends Model {

    const ANNUAL_CARD_TABLE = 'pft_annual_card';    //卡片信息表
    const CARD_CONFIG_TABLE = 'pft_annual_card_conf';    //年卡激活配置表
    const CARD_PRIVILEGE_TABLE = 'pft_annual_card_privilege';    //年卡景区特权表
    const PRODUCT_TABLE     = 'uu_products';        //产品信息表
    public function __construct($parent_tid=0)
    {
        parent::__construct();
        $this->parent_tid = $parent_tid;
        $this->cacheKey   = "crd:{$_SESSION['memberID']}";
        $this->cache = Cache::getInstance('redis');
    }




    /**
     * 根据字段获取年卡信息
     * @param  [type] $identify [description]
     * @param  string $field    [description]
     * @return [type]           [description]
     */
    public function getAnnualCard($identify, $field = 'id') {

        return $this->table(self::ANNUAL_CARD_TABLE)->where([$field => $identify])->find();

    }

    /**
     * 获取指定产品的关联年卡
     * @return [type] [description]
     */
    public function getAnnualCards($sid, $pid, $options = []) {

        $where = [
            'sid' => $sid,
            'pid' => $pid
        ];

        $this->table(self::ANNUAL_CARD_TABLE)->where($where)->select($options);

    }

    /**
     * 获取指定供应商的年卡产品列表
     * @param  [type] $sid 供应商id
     * @return [type]      [description]
     */
    public function getAnnualCardProducts($sid) {

        $where = [
            'apply_did' => $sid,
            'p_type'    => 'I',
            'p_status'  => '0'
        ];

        $field = 'id,p_name';

        return $this->table(self::PRODUCT_TABLE)->where($where)->field($field)->select();
    }

    /**
     * 生成年卡
     * @return [type] [description]
     */
    public function createAnnualCard($num, $sid, $pid) {
        $insert_data = $return = [];

        while (1) {
            $virtual_no = $this->_createVirtualNo();

            if (!$this->getAnnualCard($virtual_no, 'virtual_no')) {
                $insert_data[] = ['sid' => $sid, 'pid' => $pid, 'virtual_no' => $virtual_no, 'status' => 3];
            }

            $return[] = $virtual_no;

            if (count($insert_data) == $num) break;
        }

        if (!$this->table(self::ANNUAL_CARD_TABLE)->addAll($insert_data)) {
            return false;
        }

        return $return;
    }

    /**
     * 生成虚拟卡号
     * @return [type] [description]
     */
    private function _createVirtualNo() {
        $string = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $number = '0123456789';
        $mix    = $string . $number;

        $virtual_no     = '';
        $head           = str_shuffle($string)[0];
        $second_part    = substr(str_shuffle($string), 0, 3);
        $third_part     = substr(str_shuffle($number), 0, 3);
        $virtual_no    .= $head . $second_part . $third_part;
        $tail           = array_sum(str_split($virtual_no));
        $virtual_no    .= $virtual_no . $tail;

        return $virtual_no;
    }

    /**
     * 删除年卡
     * @return [type] [description]
     */
    public function deleteAnnualCard($where) {
        //TODO:log it
        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->delete();

    }

    /**
     * 绑定物理卡
     * @param  [type] $sid        [description]
     * @param  [type] $virtual_no [description]
     * @param  [type] $card_no    [description]
     * @param  [type] $physics_no [description]
     * @return [type]             [description]
     */
    public function bindAnnualCard($sid, $virtual_no, $card_no, $physics_no) {
        $where = [
            'card_no'       => $card_no,
            'physics_no'    => $physics_no,
            '_logic'        => 'OR'
        ];

        $find = $this->table(self::ANNUAL_CARD_TABLE)->where($where)->find();

        if ($find) {
            return false;
        }

        $update = [
            'card_no'       => $card_no,
            'physics_no'    => $physics_no
        ];

        $where = [
            'sid'           => $sid,
            'virtual_no'    => $virtual_no,
            'card_no'       => ''
        ];

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->save($update);
    }

    /**
     * 获取年卡库存
     * @param  [type] $sid  [description]
     * @param  string $type 虚拟卡 OR 物理卡
     * @return [type]       [description]
     */
    public function getAnnualCardStorage($sid, $type = 'virtual') {
        if ($type == 'virtual') {
            $where = [
                'sid'       => $sid,
                'card_no'   => '',
                'status'    => 0
            ];
        } else {
            $where = [
                'sid'       => $sid,
                'card'      => array('neq', ''),
                'status'    => 0
            ];
        }

        return $this->table(self::ANNUAL_CARD_TABLE)->where($where)->count();
    }

    /**
     * 生成年卡
     * @return [type] [description]
     */

    public function saveCardConfig($crdConf,$crdPriv) {
        $this->startTrans();
        $ret1 = $this->saveCrdConf($crdConf);
        $ret2 = $this->saveCrdPriv($crdPriv);
        if($ret1 && $ret2){
            $this->commit();
            return true;
        }else{
            $this->rollback();
            return false;
        }
    }


    /**
     * 保存年卡激活配置信息
     * @param array $data
     *
     * @return mixed
     */
    public function saveCrdConf($data){
        return $this->table(self::CARD_CONFIG_TABLE)->save($data);
    }


    /**
     * 保存年卡景区特权信息
     * @param array $data
     *
     * @return bool|string
     */
    public function saveCrdPriv(array $data){
        return $this->table(self::CARD_PRIVILEGE_TABLE)->addAll($data);
    }

    public function getCache(){
        return $this->cache->get($this->cacheKey);
    }

    public function setCache($json)
    {
        return $this->cache->set($this->cacheKey, $json, '', 1800);
    }
    public function rmCache()
    {
        return $this->cache->rm($this->cacheKey);
    }
    public function checkPriv($json)
    {
        $arr_list = json_decode($json, true);
        $limit_key_list = ['aid','tid','use_limit','limit_count'];
        foreach ($arr_list as $arr) {
            foreach ($arr as $key=>$val) {
                if(!in_array($key, $limit_key_list) || !is_numeric($val)) {
                    echo $key, $val;
                    return false;
                }
            }
        }
        return $arr_list;
    }
}