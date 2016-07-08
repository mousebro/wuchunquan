<?php
/**
 * 银行相关模型
 *
 * @author dwer
 * @date 2016-07-08 
 * 
 */
namespace Model\Finance;
use Library\Model;

class Banks extends Model{

    private $_banksTable     = 'pft_banks';
    private $_bankAreaTable  = 'pft_bank_area';
    private $_subbranchTable = 'pft_bank_subbranch';

    public function __construct() {
        //默认从从库读取数据
        parent::__construct('slave');
    }

    /**
     * 获取所有的银行及其代码
     * @author dwer
     * @date   2016-07-08
     *
     * @param  $page
     * @param  $size
     * @return
     */
    public function getBanks($page = 1, $size = 200) {
        $res = $this->table($this->_banksTable)->page("$page,$size")->field('code, name')->select();

        return $res;
    }

    /**
     * 获取所有银行所在的身份及其代码
     * @author dwer
     * @date   2016-07-08
     *
     * @return 
     */
    public function getBankProvince() {
        $where = [
            'parent_id' => 0
        ];

        $res = $this->table($this->_bankAreaTable)->where($where)->field('area_id as code, area_name as name')->select();
        return $res;
    }

    /**
     * 根据省份ID获取市及其代码
     * @author dwer
     * @date   2016-07-08
     *
     * @param  $provinceId
     * @return
     */
    public function getCity($provinceId) {
        if(!$provinceId) {
            return false;
        }

        $where = [
            'parent_id' => intval($provinceId)
        ];

        $res = $this->table($this->_bankAreaTable)->where($where)->field('area_id as code, area_name as name')->select();
        return $res;
    }

    /**
     *  
     * @author dwer
     * @date   2016-07-08
     *
     * @param  $cityId 城市代码 1620 => 大同市
     * @param  $bankId 银行代码 504 => 恒生银行
     * @param  $searchName 模糊搜索 词
     * @param  $page 第几页
     * @param  $size 条数
     * @return
     */
    public function getSubbranch($cityId, $bankId, $searchName = '', $page = 1, $size = 500) {
        $cityId     = intval($cityId);
        $bankId     = intval($bankId);
        $searchName = strval($searchName);
        $page       = intval($page);
        $size       = intval($size);

        if(!$cityId || !$bankId) {
            return false;
        }

        $where = [
            'city'    => $cityId,
            'bank_id' => $bankId
        ];

        if($searchName !== '') {
            $where['name'] = ['like', "%{$searchName}%"];
        }

        $page = "{$page},{$size}";
        $field = 'code,name,phone';

        $count   = $this->table($this->_subbranchTable)->field($field)->where($where)->count();
        $list    = $this->table($this->_subbranchTable)->field($field)->where($where)->page($page)->select();
        
        return $list === false ? false : ['count' => $count, 'list' => $list];
    }
}