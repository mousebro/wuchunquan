<?php
/**
 * 分销商库存管理相关的类库 
 *
 * @author dwer
 * @time 2016-01-20 18:45
 */
namespace Model\Product;
use Library\Model;
use Model\Member\Reseller;

class YXStorage extends Model{
    private $_storageTable        = 'pft_yx_storage';
    private $_defaultStorageTable = 'pft_yx_storage_default';

    private $_infoTable        = 'pft_yx_info';
    private $_defaultInfoTable = 'pft_yx_info_default';

    private $_areaTable     = 'pft_roundzone';
    private $_roundTable    = 'pft_round';
    private $_dynTable      = 'pft_roundseat_dyn'; 
    private $_seatsTable    = 'pft_roundseat';

    //可以使用印象分销库存功能的供应商
    //43517--印象， 4971, 94, 1000026, 6970--测试账号
    private  static $_legalProviderArr = array( 4971, 94, 1000026, 6970);

    //初始化数据库
    public function __construct() {
        //默认连接演出库
        parent::__construct('remote_1');
    }

    /**
     * 是不是需要使用印象分销商库存功能
     *
     * @param $applyId 供应商ID
     */
    public static function isLegalProvider($applyId) {
        //判断账号是不是在可用数组里面
        if(in_array($applyId, self::$_legalProviderArr)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取分区的默认配置信息 
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $areaId 分区ID
     * @return
     */
    public function getDefaultInfo($areaId) {
        if(!$areaId) {
            return false;
        }

        $where = array('area_id' => $areaId);

        $info = $this->table($this->_defaultInfoTable)->find();
        if($info) {
            return $info;
        } else {
            return false;
        }
    }

    /**
     * 获取分区的配置信息 
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $areaId 分区ID
     * @param  $roundId 场次ID
     * @return
     */
    public function getInfo($areaId, $roundId) {
        if(!$areaId || !$roundId) {
            return false;
        }

        $where = array(
            'area_id'  => $areaId,
            'round_id' => $roundId
        );

        $info = $this->table($this->_infoTable)->find();
        if($info) {
            return $info;
        } else {
            return false;
        }
    }

    /**
     * 获取分销商在某个场次、分区的情况下可以分销的设置数量
     *
     * @param $resellerId 分销商 - 25501
     * @param $roundId 场次 - 199944
     * @param $area 分区 - 33
     *
     * @return 返回 bool/number 如果参数错误返回false，否则返回数量
     * 
     */
    public function totalNumber($resellerId, $roundId, $area) {
        //参数判断
        if(!$resellerId || !$roundId || !$area) { 
            return false;
        }

        $field = 'total_num';
        $where = array(
            'reseller_id' => $resellerId,
            'round_id'    => $roundId,
            'area_id'     => $area,
        );

        $info = $this->table($this->_storageTable)->field($field)->where($where)->find();

        if($info) {
            return intval($info['total_num']);
        } else {
            return -1;
        }
    }

    /**
     * 获取分销商默认的分销配置数量
     *
     * @param $resellerId 分销商 - 25501
     * @param $area 分区 - 33
     *
     * @return 返回 bool/number 如果参数错误返回false，否则返回数量
     * 
     */
    public function totalDefaultNumber($resellerId, $roundId, $area) {
        //参数判断
        if(!$resellerId || !$area) { 
            return false;
        }

        $field = 'total_num';
        $where = array(
            'reseller_id' => $resellerId,
            'area_id'     => $area,
        );

        $info = $this->table($this->_defaultStorageTable)->field($field)->where($where)->find();

        if($info) {
            return intval($info['total_num']);
        } else {
            return -1;
        }
    }


    /**
     * 获取某个场次、分区的情况下所有已经设置的分销商库存
     *
     * @param $roundId 场次 - 199944
     * @param $areaId 分区 - 33
     *
     * @return 返回 bool/number 如果参数错误返回false，否则返回数量
     * 
     */
    public function allSetNumber( $roundId, $areaId) {
        //参数判断
        if(!$roundId || !$areaId) {
            return false;
        }

        $info = $this->getInfo($areaId, $roundId);

        if($info) {
            return $info['reserve_num'];
        } else {
            return 0;
        }
    }

    /**
     * 给分销商设置分销库存  
     * @author dwer
     * @date   2016-03-20
     *
     * @param   $roundId 场次ID
     * @param   $areaId 分区ID
     * @param   $setData 设置数组 [分销商ID => 保留库存数量]
     * @param   $status 状态：1=开启，0=关闭
     */
    public function setResellerStorage($roundId, $areaId, $setData, $status = 0) {
        if(!$roundId || !$areaId || !is_array($setData)) {
            return false;
        }

        //计算数据
        $reserveNum = 0;
        foreach($setData as $resellerId => $storage) {
            if($storage >= 0) {
                $reserveNum += $storage;
            }
        }

        $mark = true;
        $this->startTrans();

        foreach($setData as $resellerId => $storage) {

            $res = $this->_setNum($roundId, $areaId, $resellerId, $storage);

            if(!$res) {
                $mark = false;
                break;
            }
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        $res = $this->_setInfo($roundId, $areaId, $reserveNum, $status);
        if(!$res) {
            $this->rollback();
            return false;
        } else {
            $this->commit();
            return true;
        }
    }

    /**
     * 给分销商设置分销库存  
     * @author dwer
     * @date   2016-03-20
     *
     * @param   $areaId 分区ID
     * @param   $setData 设置数组 [分销商ID => 保留库存数量]
     * @param   $status 状态：1=开启，0=关闭
     */
    public function setDefaultResellerStorage($areaId, $setData, $status = 0) {
        if(!$areaId || !is_array($setData)) {
            return false;
        }

        //计算数据
        $reserveNum = 0;
        foreach($setData as $resellerId => $storage) {
            if($storage >= 0) {
                $reserveNum += $storage;
            }
        }

        $mark = true;
        $this->startTrans();

        foreach($setData as $resellerId => $storage) {

            $res = $this->_setDefaultNum($areaId, $resellerId, $storage);

            if(!$res) {
                $mark = false;
                break;
            }
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        $res = $this->_setDefaultInfo($areaId, $reserveNum, $status);
        if(!$res) {
            $this->rollback();
            return false;
        } else {
            $this->commit();
            return true;
        }
    }


    /**
     * 获取场次
     */
    public function getRoundList($venusId, $date, $page = 1, $size = 100) {
        $field = 'id, round_name';
        $where = array(
            'venus_id' => $venusId,
            'use_date' => $date
        );
        $page = "{$page},{$size}";
        $res = $this->table($this->_roundTable)->field($field)->where($where)->page($page)->select();

        return $res;
    }

    /**
     * 获取分区
     */
    public function getAreaList($venusId) {
        if(!$venusId) {
            return array();
        }

        $where = array('venue_id' => $venusId);
        $res = $this->table($this->_areaTable)->where($where)->page('1,100')->select();

        return $res;
    }

    /**
     * 获取分销商
     *
     * @param $providerId 供应商ID
     */
    public function getResellerList($providerId) {
        if(!$providerId) {
            return array();
        }

        $resellerModel = new Reseller();
        $res = $resellerModel->getResellerList($providerId);

        return $res;
    }

    /**
     * 获取默认的总结信息
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $venusId 场馆ID
     * @param  $areaId 分区ID
     * @return
     */
    public function getDefaultSummary($venusId, $areaId) {

    }

    /**
     * 获取总结信息
     */
    public function getSummary($venusId, $roundId, $areaId) {
        //获取总座位数和预留座位数
        $where = array(
            ''
        );

        //获取销售数据
        $saled = $this->getResellerNums($roundId, $areaId, $resellerId);


        //返回
        return array('total' => $totalNum, 'saled' => $saled, 'reserve' => $reserve, 'repeat' => $repeatNum);
    }

    /**
     * 获取分销商的销售量
     */
    public function getResellerNums($roundId, $areaId, $resellerId) {
        if(!$roundId || !$areaId || !$resellerId) {
            return false;
        }

        $where = array(
            'zone_id'  => $areaId,
            'round_id' => $roundId,
            'opid'     => $resellerId,
            'status'   => array('in', '2, 3')
        );

        $sales = $this->table($this->_dynTable)->where($where)->count();
        $sales = $sales === false ? 0 : $sales;

        return $sales;
    }


    /**
     * 设置保留库存
     * @author dwer
     * @date   2016-03-20
     *
     * @param   $roundId 场次ID
     * @param   $areaId 分区ID
     * @param  $resellerId 分销商ID
     * @param  $storage 保留库存
     */
    private function _setNum($roundId, $areaId, $resellerId, $storage) {
        $where = array(
            'round_id'    => $roundId,
            'area_id'     => $areaId
            'reseller_id' => $resellerId
        );

        $field = 'id';
        $tmp = $this->table($this->_storageTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'total_num'   => $storage,
                'update_time' => time()
            );

            $res = $this->table($this->_storageTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['total_num'] = $storage;
            $newData['update_time'] = time();

            $res = $this->table($this->_storageTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *  
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @param  $reserveNum 分销商保留库存总和
     * @param  $status 状态
     */
    private function _setInfo($roundId, $areaId, $reserveNum, $status){
        $where = array(
            'round_id'    => $roundId,
            'area_id'     => $areaId
        );

        $field = 'id';
        $tmp = $this->table($this->_infoTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'reserve_num' => $reserveNum,
                'status'      => $status,
                'update_time' => time()
            );

            $res = $this->table($this->_infoTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['total_num']   = $storage;
            $newData['status']      = $status;
            $newData['update_time'] = time();

            $res = $this->table($this->_infoTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

        /**
     * 设置默认的库存信息
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @param  $reserveNum 分销商保留库存总和
     * @param  $status 状态
     */
    private function _setDefaultInfo($areaId, $reserveNum, $status){
        $where = array(
            'area_id'     => $areaId
        );

        $field = 'id';
        $tmp = $this->table($this->_defaultInfoTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'reserve_num' => $reserveNum,
                'status'      => $status,
                'update_time' => time()
            );

            $res = $this->table($this->_defaultInfoTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['total_num']   = $storage;
            $newData['status']      = $status;
            $newData['update_time'] = time();

            $res = $this->table($this->_defaultInfoTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 设置默认保留库存
     * @author dwer
     * @date   2016-03-20
     *
     * @param   $areaId 分区ID
     * @param  $resellerId 分销商ID
     * @param  $storage 保留库存
     */
    private function _setDefaultNum($areaId, $resellerId, $storage) {
        $where = array(
            'area_id'     => $areaId
            'reseller_id' => $resellerId
        );

        $field = 'id';
        $tmp = $this->table($this->_defaultStorageTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'total_num'   => $storage,
                'update_time' => time()
            );

            $res = $this->table($this->_defaultStorageTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['total_num'] = $storage;
            $newData['update_time'] = time();

            $res = $this->table($this->_defaultStorageTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

}