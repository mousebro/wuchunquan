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
use Model\Product\Land;

class YXStorage extends Model{
    private $_storageTable        = 'pft_yx_storage';
    private $_defaultStorageTable = 'pft_yx_storage_default';
    private $_usedTable           = 'pft_yx_storage_used';
    private $_logTable            = 'pft_yx_storage_log';

    private $_infoTable        = 'pft_yx_info';
    private $_defaultInfoTable = 'pft_yx_info_default';

    private $_areaTable      = 'pft_roundzone';
    private $_roundTable     = 'pft_round';
    private $_dynTable       = 'pft_roundseat_dyn'; 
    private $_seatsTable     = 'pft_roundseat';
    private $_roundSeatTable = 'pft_round_zoneseats';
    private $_venusTable     = 'pft_venues';

    //日志记录路径
    private $_setLogPath = 'product/show_storage_set';
    private $_getLogPath = 'product/show_storage_get';

    //初始化数据库
    public function __construct() {
        //默认连接演出库
        parent::__construct('remote_1');
    }

    /**
     * 是否有权限配置
     * @author dwer
     * @date   2016-04-06
     *
     * @param $venusId
     * @param $memberId
     * @return bool
     */
    public function isAuth($venusId, $memberId) {
        if(!$venusId || !$memberId) {
            return false;
        }

        //查询场馆信息
        $tmp = $this->table($this->_venusTable)->where(['id' => $venusId])->find();
        if (!$tmp) {
            return false;
        }

        $applyDid = $tmp['apply_did'];

        if($applyDid == $memberId) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否开启了分销库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param $roundId 场次 - 199944
     * @param $areaId 分区 - 33
     * @return
     */
    public function isOpen($roundId, $areaId) {
        if(!$roundId || !$areaId) {
            return false;
        }

        //获取是使用默认配置还是具体配置
        $info = $this->getInfo($areaId, $roundId);

        if(!$info) {
            $info = $this->getDefaultInfo($areaId);
        }

        if(!$info) {
            return false;
        }

        //判断库存判断是否开启
        if($info['status'] == 0) {
            //没有开启
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取分销商可以消耗的库存量
     * @author dwer
     * @date   2016-03-22
     *
     * @param $resellerId 分销商 - 25501
     * @param $roundId 场次 - 199944
     * @param $areaId 分区 - 33
     * @return
     */
    public function getResellerStorage($resellerId, $roundId, $areaId) {
        if(!$areaId || !$roundId || !$resellerId) {
            return false;
        }

        //是否使用默认配置
        $useDefault = false;

        //获取是使用默认配置还是具体配置
        $info = $this->getInfo($areaId, $roundId);

        if(!$info) {
            $info = $this->getDefaultInfo($areaId);
            if(!$info) {
                return false;
            }

            $useDefault = true;
        }

        //判断库存判断是否开启
        if($info['status'] == 0) {
            //没有开启
            return false;
        }

        if($useDefault) {
            $sellerStorage = $this->totalDefaultNumber($resellerId, $areaId);
        } else {
            $sellerStorage = $this->totalNumber($resellerId, $roundId, $areaId);
        }
        $sellerStorage = intval($sellerStorage);

        //保留的库存之和
        $reserveNum = $info['reserve_num'];

        $resStorage = 0;

        if($sellerStorage == -1) {
            //使用未分配的库存
            $leftStorage = $this->_getLeftStorage($roundId, $areaId, $reserveNum);

            $resStorage = $leftStorage;
        } else {
            if($sellerStorage == 0) {
                $resStorage = 0;
            } else {
                //获取该分销商已经消耗了多少库存
                $usedStorage = $this->getResellerNums($roundId, $areaId, $resellerId);

                //使用给他分配的固定库存
                $leftNums   = $sellerStorage - $usedStorage;
                $resStorage = $leftNums < 0 ? 0 : $leftNums;
            }
        }

        //写日志
        $logData         = ['ac' => 'getResellerStorage'];
        $logData['data'] = [$resellerId, $roundId, $areaId];
        $logData['rs']   = $resStorage;
        $this->_log($logData, 'get');

        return $resStorage;
    }

    /**
     * 使用库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $orderNum 订单ID
     * @param  $resellerId 分销商ID
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @param  $ticketNum 购买的数量
     * @return
     */
    public function useStorage($orderNum, $resellerId, $roundId, $areaId, $ticketNum) {
        $ticketNum = intval($ticketNum);
        if(!$orderNum || !$resellerId || !$roundId || !$areaId) {
            return false;
        }

        //是否使用默认配置
        $useDefault = false;

        //获取是使用默认配置还是具体配置
        $info = $this->getInfo($areaId, $roundId);
        if(!$info) {
            $info = $this->getDefaultInfo($areaId);
            if(!$info) {
                return false;
            }

            $useDefault = true;
        }

        //判断库存判断是否开启
        if($info['status'] == 0) {
            //没有开启
            return false;
        }

        if($useDefault) {
            $sellerStorage = $this->totalDefaultNumber($resellerId, $areaId);
        } else {
            $sellerStorage = $this->totalNumber($resellerId, $roundId, $areaId);
        }
        $sellerStorage = intval($sellerStorage);

        //保留的库存之和
        $reserveNum = $info['reserve_num'];

        if($reserveNum != -1) {
            $leftStorage = $this->_getLeftStorage($roundId, $areaId, $reserveNum);

            //如果使用量超过剩余库存
            if($ticketNum > $leftStorage) {
                $ticketNum = $leftStorage;
            }

            //消耗未分配的库存
            $res = $this->_useStorage($orderNum, $roundId, $ticketNum);

            //写日志
            $logData         = ['ac' => 'useStorage'];
            $logData['data'] = [$orderNum, $resellerId, $roundId, $areaId, $ticketNum];
            $logData['rs']   = $res;
            $this->_log($logData, 'get');

            if($res) {
                return true;
            } else {
                return false;
            }

        } else {
            return true;
        }
    }

    /**
     * 取消订单，恢复库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $orderNum 订单ID
     * @return
     */
    public function recoverStorage($orderNum) {
        if(!$orderNum) {
            return false;
        }

        $logInfo = $this->table($this->_logTable)->where(['order_num' => $orderNum])->find();

        //如果没有使用未分配库存的情况下，返回成功
        if(!$logInfo) {
            return true;
        }

        //如果已经恢复过,就不再恢复，返回成功
        if($logInfo['recover_time'] > 0) {
            return true;
        }

        $num     = intval($logInfo['num']);
        $roundId = $logInfo['round_id'];

        $this->startTrans();

        //减少库存使用量
        $res = $this->_recoverUsedNum($roundId, $num);

        if(!$res) {
            $this->rollback();
            return false;
        }

        //更新历史记录
        $data = [
            'recover_time' => time(),
            'update_time'  => time()
        ];
        $res = $this->table($this->_logTable)->where(['id' => $logInfo['id']])->save($data);

        //写日志
        $logData         = ['ac' => 'recoverStorage'];
        $logData['data'] = [$orderNum, $num];
        $logData['rs']   = $res;
        $this->_log($logData, 'get');

        if($res) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 修改订单数量
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $orderNum 订单ID
     * @param  $reducedNum 减少的库存
     * 
     * @return
     */
    public function changeStorage($orderNum, $reducedNum) {
        $reducedNum = intval($reducedNum);
        if(!$orderNum) {
            return false;
        }

        $logInfo = $this->table($this->_logTable)->where(['order_num' => $orderNum])->find();

        //如果没有使用未分配库存的情况下，返回成功
        if(!$logInfo) {
            return true;
        }

        //如果已经恢复过,就不再恢复，返回成功
        if($logInfo['recover_time'] > 0) {
            return true;
        }

        $num        = intval($logInfo['num']);
        $roundId    = $logInfo['round_id'];
        $reducedNum = $num < $reducedNum ? $num : $reducedNum;

        $this->startTrans();

        //减少库存使用量
        $res = $this->_recoverUsedNum($roundId, $reducedNum);

        if(!$res) {
            $this->rollback();
            return false;
        }

        //更新历史记录
        $data = [
            'num'         => ['exp', "num - {$reducedNum}"],
            'update_time' => time()
        ];
        $res = $this->table($this->_logTable)->where(['id' => $logInfo['id']])->save($data);

        //写日志
        $logData         = ['ac' => 'changeStorage'];
        $logData['data'] = [$orderNum, $reducedNum];
        $logData['rs']   = $res;
        $this->_log($logData, 'get');

        if($res) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 删除了分销商后，清除库存配置
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $resellerId 分销商ID
     * @param  $pid 产品ID
     * @return
     */
    public function removeReseller($setterId, $resellerId, $pid = false) {
        if(!$setterId || !$resellerId) {
            return false;
        }

        //由产品ID获取分区ID
        $areaId = false;
        if($pid) {
            $landModel = new Land();
            $extInfo = $landModel->getExtFromPid($pid, 'zone_id');
            if($extInfo) {
                $areaId = $extInfo['zone_id'];
            }
        }

        //清除今天以及之后的数据
        $nowDate = date('Ymd');
        $res = $this->_removeResellerData($setterId, $resellerId, $nowDate, $areaId);

        //写日志
        $logData         = ['ac' => 'removeReseller'];
        $logData['data'] = [$setterId, $resellerId, $pid];
        $logData['rs']   = $res;
        $this->_log($logData, 'get');

        if($res) {
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

        $info = $this->table($this->_infoTable)->where($where)->find();

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
    public function totalDefaultNumber($resellerId, $area) {
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
     * @param   $setterId 供应商ID
     */
    public function setResellerStorage($roundId, $areaId, $setData, $useDate, $setterId) {
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

            $res = $this->_setNum($roundId, $areaId, $resellerId, $storage, $useDate, $setterId);

            if(!$res) {
                $mark = false;
                break;
            }
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        $res = $this->_setInfo($roundId, $areaId, $reserveNum, $useDate, $setterId);

        //写日志
        $logData         = ['ac' => 'setResellerStorage'];
        $logData['data'] = [$roundId, $areaId, $setData, $status, $useDate, $setterId];
        $logData['rs']   = $res;
        $this->_log($logData, 'set');

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
     * @param   $setterId 供应商ID
     */
    public function setDefaultResellerStorage($areaId, $setData, $status, $setterId) {
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

            $res = $this->_setDefaultNum($areaId, $resellerId, $storage, $setterId);

            if(!$res) {
                $mark = false;
                break;
            }
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        $res = $this->_setDefaultInfo($areaId, $reserveNum, $status, $setterId);

        //写日志
        $logData         = ['ac' => 'setDefaultResellerStorage'];
        $logData['data'] = [$areaId, $setData, $status, $setterId];
        $logData['rs']   = $res;
        $this->_log($logData, 'set');

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
     * 获取场次的详细信息
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @return [type]
     */
    public function getRoundInfo($roundId) {
        $where = array(
            'id' => $roundId
        );
        $field = 'lid,venus_id,opid,status,use_date';

        $info = $this->table($this->_roundTable)->where($where)->field($field)->find();

        return $info;
    }

    /**
     * 获取场次的详细信息
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @return [type]
     */
    public function getVenusInfo($venusId) {
        $where = array(
            'id' => $venusId
        );
        $field = 'venue_name,apply_did,total_seats,status';

        $info = $this->table($this->_venusTable)->where($where)->field($field)->find();

        return $info;
    }

    /**
     * 获取分区的详细信息
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $areaId 分区ID
     * @return [type]
     */
    public function getZoneInfo($areaId) {
        $where = array(
            'id' => $areaId
        );

        $info = $this->table($this->_areaTable)->where($where)->find();

        return $info;
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
        //获取总的座位数
        $where = array(
            'venue_id'    => $venusId,
            'zone_id'     => $areaId,
            'seat_status' => array('neq', 5)
        );

        $allSeats = $this->table($this->_seatsTable)->where($where)->count();
        $allSeats = $allSeats === false ? 0 : intval($allSeats);

        return array('total' => $allSeats);
    }

    /**
     * 获取总结信息
     */
    public function getSummary($roundId, $areaId) {
        $data = $this->getRoundSeats($roundId, $areaId);

        //获取销售数据
        $saled = $this->getResellerNums($roundId, $areaId);
        $data['saled'] = $saled;

        //返回
        return $data;
    }

    /**
     * 获取分销商的销售量
     */
    public function getResellerNums($roundId, $areaId, $resellerId = false) {
        if(!$roundId || !$areaId) {
            return false;
        }

        $where = array(
            'zone_id'  => $areaId,
            'round_id' => $roundId,
            'status'   => array('in', '2, 3')
        );

        if($resellerId) {
            $where['opid'] = $resellerId;
        }

        $sales = $this->table($this->_dynTable)->where($where)->count();
        $sales = $sales === false ? 0 : intval($sales);

        return $sales;
    }

    /**
     * 获取场次下面座位数据
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @return
     */
    public function getRoundSeats($roundId, $areaId) {
        //获取总座位数和预留座位数
        $where = array(
            'round_id' => $roundId,
            'zone_id'  => $areaId
        );

        $field = 'seat_storage,seat_reverse';
        $info = $this->table($this->_roundSeatTable)->where($where)->field($field)->find();

        $totalNum = 0;
        $reserve  = 0;
        if($info) {
            $totalNum = $info['seat_storage'];
            $reserve  = $info['seat_reverse'];
        }

        return array('total' => $totalNum, 'reserve' => $reserve);
    }

    /**
     * 将默认配置复制到具体的场次中去
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @return
     */
    public function copyDataFromDefault($areaId, $roundId) {
        if(!$areaId || !$roundId) {
            return false;
        }

        $defaultInfo = $this->getDefaultInfo($areaId);
        if(!$defaultInfo) {
            return false;
        }

        //获取场次的是哟个日期
        $roundInfo = $this->getRoundInfo($roundId);
        $useDate   = $roundInfo ? $roundInfo['use_date'] : date('Ymd');
        $useDate   = intval(str_replace('-', '', $useDate));

        $this->startTrans();

        $res = $this->_setInfo($roundId, $areaId, $defaultInfo['reserve_num'], $useDate, $defaultInfo['setter_id'], $defaultInfo['status']);
        if(!$res) {
            $this->rollback();
            return false;
        }

        //获取给分销商的配置
        $where = array(
            'area_id' => $areaId
        );

        $storageList = $this->table($this->_defaultStorageTable)->field('reseller_id,total_num')->where($where)->select();

        $setData = array();
        foreach($storageList as $item) {
             $setData[] = [
                'reseller_id' => $item['reseller_id'],
                'round_id'    => $roundId,
                'area_id'     => $areaId,
                'total_num'   => $item['total_num'],
                'update_time' => time(),
             ];
        }

        //将数据写入
        if($setData) {
            $res = $this->table($this->_storageTable)->addAll($setData);

            if($res === false) {
                $this->rollback();
                return false;
            }
        }

        $this->commit();
        return true;
    }

    /**
     * 设置场次下面分销库存的状态
     * @author dwer
     * @date   2016-03-20
     *
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @param  $setterId 供应商ID
     * @param  $status 状态
     */
    public function setInfo($roundId, $areaId, $setterId, $status){
        $where = array(
            'round_id'    => $roundId,
            'area_id'     => $areaId
        );

        $field = 'id';
        $tmp = $this->table($this->_infoTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'status'      => $status,
                'update_time' => time()
            );

            $res = $this->table($this->_infoTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['status']      = $status;
            $newData['setter_id']   = $setterId;
            $newData['update_time'] = time();

            $res = $this->table($this->_infoTable)->add($newData);
        }

        //写日志
        $logData         = ['ac' => 'setInfo'];
        $logData['data'] = [$roundId, $areaId, $setterId, $status];
        $logData['rs']   = $res;
        $this->_log($logData, 'set');

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取已经使用掉的未分配库存
     * @autho dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @return
     */
    public function getUsedStorage($roundId) {
        $where = array('round' => $roundId);
        $field = 'used_num';

        $info = $this->table($this->_usedTable)->where($where)->field($field)->find();
        if($info) {
            return intval($info['used_num']);
        } else {
            return 0;
        }
    }

    /**
     * 清除分销商的配置数据
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $resellerId 分销商ID
     * @param  $date 开始删除的日期
     * @param  $areaId 分区ID
     * @return 
     */
    private function _removeResellerData($setterId, $resellerId, $date, $areaId = false) {
        //删除默认的配置数据
        $res = $this->_removeDefaultData($setterId, $resellerId, $areaId);
        if(!$res) {
            return false;
        }

        //删除具体配置的数据
        $res = $this->_removeRoundData($setterId, $resellerId, $date, $areaId);
        if($res) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 删除默认配置的数据
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $resellerId 分销商ID
     * @param  $areaId 分区ID
     * @return
     */
    private function _removeDefaultData($setterId, $resellerId, $areaId = false) {
        $where = array(
            'reseller_id' => $resellerId,
            'setter_id'   => $setterId
        );

        if($areaId) {
            $where['area_id'] = $areaId;
        }
        $page  = '1,100'; 
        $field = 'total_num,id,area_id';

        $deleteIds = [];
        $mark = true;

        $list = $this->table($this->_defaultStorageTable)->where($where)->field($field)->page($page)->select();

        //如果没有数据的话
        if(!$list) {
            return true;
        }

        foreach($list as $item) {
            if($item['total_num'] > 0) {
                $res = $this->_changeDefaultStorage($setterId, $item['area_id'], $item['total_num']);

                if(!$res) {
                    $mark = false;
                    break;
                }
            }

            $deleteIds[] = $item['id'];
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        //批量删除数据
        $where = [
            'id' => ['in', $deleteIds]
        ];
        $res = $this->table($this->_defaultStorageTable)->where($where)->delete();

        if($res !== false) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 修改分销商保留库存总和 - 默认配置
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $areaId 分区ID
     * @param  $num 减少数量
     * @return
     */
    private function _changeDefaultStorage($setterId, $areaId, $num) {
        $num = intval($num);
        $where = array(
            'area_id'   => $areaId,
            'setter_id' => $setterId
        );

        $data = [
            'reserve_num' => ['exp', "reserve_num-{$num}"],
            'update_time' => time()
        ];

        $res = $this->table($this->_defaultInfoTable)->where($where)->save($data);

        return $res === false ? false : true;
    }

    /**
     * 修改分销商保留库存总和 - 场次配置
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $roundId 场次ID
     * @param  $areaId 分区ID
     * @param  $num 减少数量
     * @return
     */
    private function _changeRoundStorage($setterId, $roundId, $areaId, $num) {
        $num = intval($num);
        $where = array(
            'area_id'   => $areaId,
            'setter_id' => $setterId,
            'round_id'  => $roundId
        );

        $data = [
            'reserve_num' => ['exp', "reserve_num-{$num}"],
            'update_time' => time()
        ];

        $res = $this->table($this->_infoTable)->where($where)->save($data);

        return $res === false ? false : true;
    }

    /**
     * 清除具体场次的数据
     * @author dwer
     * @date   2016-03-23
     *
     * @param  $setterId 供应商ID
     * @param  $resellerId 分销商ID
     * @param  $date 日期
     * @param  $areaId 分区ID
     * @return
     */
    private function _removeRoundData($setterId, $resellerId, $date, $areaId = false) {
        $where = array(
            'reseller_id' => $resellerId,
            'setter_id'   => $setterId,
            'use_date'    => ['egt', $date]
        );

        if($areaId) {
            $where['area_id'] = $areaId;
        }
        $page  = '1,365'; 
        $field = 'total_num,id,area_id,round_id';

        $deleteIds = [];
        $mark = true;

        $list = $this->table($this->_storageTable)->where($where)->field($field)->page($page)->select();

        //如果没有数据的话
        if(!$list) {
            return true;
        }

        foreach($list as $item) {
            if($item['total_num'] > 0) {
                $res = $this->_changeRoundStorage($setterId, $item['round_id'], $item['area_id'], $item['total_num']);

                if(!$res) {
                    $mark = false;
                    break;
                }
            }

            $deleteIds[] = $item['id'];
        }

        if(!$mark) {
            $this->rollback();
            return false;
        }

        //批量删除数据
        $where = [
            'id' => ['in', $deleteIds]
        ];
        $res = $this->table($this->_storageTable)->where($where)->delete();

        if($res !== false) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 消耗库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $orderNum 订单ID
     * @param  $roundId 场次ID
     * @param  $num 消耗的库存量
     * @return
     */
    private function _useStorage($orderNum, $roundId, $num) {
        $this->startTrans();

        //记录写入历史
        $res = $this->_addUsedLog($orderNum, $roundId, $num);
        if(!$res) {
            return false;
        }

        //增加使用量
        $res = $this->_addUsedNum($roundId, $num);
        if($res) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    private function _addUsedLog($orderNum, $roundId, $num) {
        $data = [
            'order_num'   => $orderNum,
            'round_id'    => $roundId,
            'num'         => $num,
            'update_time' => time(),
        ];

        $res = $this->table($this->_logTable)->add($data);

        return $res === false ? false : true;
    }

    /**
     * 增加使用掉的未分配库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @param  $num 消耗的库存量
     */
    private function _addUsedNum($roundId, $num) {
        $info = $this->table($this->_usedTable)->field('id')->where(['round_id' => $roundId])->find();

        if($info) {
            $newData = [
                'update_time' => time(),
                'used_num' => ['exp', "used_num + {$num}"]
            ];

            $res = $this->table($this->_usedTable)->where(['id' => $info['id']])->save($newData);
        } else {

            $newData = [
                'update_time' => time(),
                'used_num'    => $num,
                'round_id'    => $roundId
            ];

            $res = $this->table($this->_usedTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 减少使用掉的未分配库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId 场次ID
     * @param  $num 消耗的库存量
     */
    private function _recoverUsedNum($roundId, $num) {
        $where = ['round_id' => $roundId];

        $data = [
            'update_time' => time(),
            'used_num' => ['exp', "used_num - {$num}"]
        ];

        $res = $this->table($this->_usedTable)->where($where)->save($data);

        return $res === false ? false : true;
    }

    /**
     * 获取剩余的可以使用的未分配的库存
     * @author dwer
     * @date   2016-03-22
     *
     * @param  $roundId
     * @param  $areaId
     * @param  $reserveNum
     * @return
     */
    private function _getLeftStorage($roundId, $areaId, $reserveNum) {
        //获取总库存
        $tmp    = $this->getRoundSeats($roundId, $areaId);
        $total  = intval($tmp['total']);

        $reserveNum = intval($reserveNum);

        //获取已经使用掉的库存
        $used = $this->getUsedStorage($roundId);

        $leftStorage = $total - $reserveNum - $used;
        if($leftStorage >= 0) {
            return intval($leftStorage);
        } else {
            return 0;
        }
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
     * @param  $useDate 使用日期
     * @param  $setterId 供应商ID
     */
    private function _setNum($roundId, $areaId, $resellerId, $storage, $useDate, $setterId) {
        $where = array(
            'round_id'    => $roundId,
            'area_id'     => $areaId,
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
            $newData['setter_id'] = $setterId;
            $newData['use_date'] = $useDate;

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
     * @param  $setterId 供应商ID
     */
    private function _setInfo($roundId, $areaId, $reserveNum, $useDate, $setterId, $status = false){
        $where = array(
            'round_id'    => $roundId,
            'area_id'     => $areaId
        );

        $field = 'id';
        $tmp = $this->table($this->_infoTable)->field($field)->where($where)->find();

        if($tmp) {
            $data = array(
                'reserve_num' => $reserveNum,
                'update_time' => time()
            );

            if(!$status !== false) {
                $data['status'] = $status;
            }

            $res = $this->table($this->_infoTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['reserve_num'] = $reserveNum;
            $newData['update_time'] = time();
            $newData['use_date']    = $useDate;
            $newData['setter_id']   = $setterId; 

            if(!$status !== false) {
                $newData['status'] = $status;
            }

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
     * @param  $setterId 供应商ID
     */
    private function _setDefaultInfo($areaId, $reserveNum, $status, $setterId) {
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
            $newData['reserve_num'] = $reserveNum;
            $newData['status']      = $status;
            $newData['update_time'] = time();
            $newData['setter_id']   = $setterId;

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
     * @param  $setterId 供应商ID
     */
    private function _setDefaultNum($areaId, $resellerId, $storage, $setterId) {
        $where = array(
            'area_id'     => $areaId,
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
            $newData['setter_id'] = $setterId;

            $res = $this->table($this->_defaultStorageTable)->add($newData);
        }

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * 模型日志
     * @author dwer
     * @date   2016-03-09
     *
     * @param  [type] $dataArr 日志数组内容
     * @param  [type] $type 类型 get ： 获取判断日志，set ： 设置日志
     * @return [type]
     */
    private function _log($dataArr, $type = 'get') {
        if(!$dataArr) {
            return false;
        }

        $content = json_encode($dataArr);
        if($type == 'set') {
            $res = pft_log($this->_setLogPath, $content);
        } else {
            $res = pft_log($this->_getLogPath, $content);
        }

        return $res;
    }

}