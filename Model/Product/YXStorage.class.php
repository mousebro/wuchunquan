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
    private $_storageTable  = 'pft_yx_storage';
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

        $getSql = "SELECT `total_num` FROM `{$this->_storageTable}` WHERE `reseller_id`=? and `round_id`=? and `area_id`=?;";
        $data = array($resellerId, $roundId, $area);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($res && isset($res[0])) {
            return intval($res[0]['total_num']);
        } else {
            return 0;
        }
    }

    /**
     * 获取某个场次、分区的情况下所有已经设置的分销商库存
     *
     * @param $roundId 场次 - 199944
     * @param $area 分区 - 33
     *
     * @return 返回 bool/number 如果参数错误返回false，否则返回数量
     * 
     */
    public function allSetNumber( $roundId, $area) {
        //参数判断
        if(!$roundId || !$area) {
            return false;
        }

        $getSql = "SELECT sum(`total_num`) as num  FROM `{$this->_storageTable}` WHERE `round_id`=? and `area_id`=?;";
        $data = array($roundId, $area);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetch(PDO::FETCH_ASSOC);

        if($res && isset($res['num'])) {
            return $res['num'];
        } else {
            return 0;
        }
    }

    /**
     * 给分销商设置数量
     * 
     */
    public function setNum($resellerId, $roundId, $area, $num) {
        //获取数据
        $getSql = "SELECT `id` FROM `{$this->_storageTable}` WHERE `reseller_id`=? and `round_id`=? and `area_id`=?;";
        $data = array($resellerId, $roundId, $area);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if($res) {
            //更新数据
            $updateSql = "UPDATE `{$this->_storageTable}` SET `total_num`=?, `update_time`=? WHERE  `reseller_id`=? and `round_id`=? and `area_id`=?;";
            $data = array($num,  time(), $resellerId, $roundId, $area);

            $stmt = $this->_db->prepare($updateSql);
            $back  = $stmt->execute($data);
        } else {
            //新增
            $sql = "INSERT INTO `{$this->_storageTable}` (`reseller_id`, `round_id`, `area_id`, `total_num`, `update_time`) VALUES (?, ?, ?, ?, ?);";
            $data = array($resellerId, $roundId, $area, $num, time());

            $stmt = $this->_db->prepare($sql);
            $back  = $stmt->execute($data);
        }

        if($back) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取场次
     */
    public function getRoundList($venusId, $date) {
        $getSql = "SELECT `id`,`round_name` FROM `{$this->_roundTable}` WHERE `venus_id`=? and `use_date`=? limit 0,100";
        $data = array($venusId, $date);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
     * 获取总结信息
     */
    public function getSummary($venusId, $roundId, $area) {
        $seatSql   = "SELECT id,seat_status from `{$this->_seatsTable}` where venue_id=? AND `zone_id`=?";
        $stmt_seat = $this->_db->prepare($seatSql);
        $data      = array($venusId, $area);
        $stmt_seat->execute($data);

        //统计数据
        $seatData = array();
        $totalNum = 0;
        $repeatNum = 0;

        while($tmp=$stmt_seat->fetch(PDO::FETCH_ASSOC)) {
            $seatData[$tmp['id']] = $tmp;

            if ($tmp['seat_status']==0) {
                $totalNum += 1;
            }
        }

        $dynSql = "SELECT seat_id, zone_id, status FROM `{$this->_dynTable}` WHERE round_id=? AND `zone_id`=?";
        $data      = array($roundId, $area);
        $stmt_seat = $this->_db->prepare($dynSql);
        $stmt_seat->execute($data);
        while($s = $stmt_seat->fetch(\PDO::FETCH_ASSOC)) {
            if ($s['status']==4 ) {
                if ($seatData[$s['seat_id']]['seat_status']!=0) {
                    //若场馆座位不可售，场次座位设置成可售，那么总库存增加
                    $totalNum += 1;
                }
            } elseif ($s['status']==5) {
                //场次座位不可售，场馆座位可售，那么总库存应该减少
                if ($seatData[$s['seat_id']]['seat_status']!=5) {
                    $totalNum -= 1;
                }
            } elseif ($s['status']!=4) {
                if($seatData[$s['seat_id']]['seat_status']!=0) {
                    $totalNum += 1;
                }
            }


            if($s['status'] == 1) {
                //如果场次预留的座位和场馆不可售的座位重合了，记录这个数据
                if($seatData[$s['seat_id']]['seat_status'] == 5) {
                    //重复的座位
                    $repeatNum += 1;
                }   
            }
        }

        //获取已经占用的数量
        $getSql = "SELECT COUNT(id) AS saled FROM `{$this->_dynTable}` WHERE `round_id`=? AND `status` in (2,3) AND `zone_id`=?";
        $data = array($roundId, $area);
        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(isset($res[0])) {
            $saled = $res[0]['saled'];
        } else {
            $saled = 0;
        }

        //获取预留的座位
        $getSql = "SELECT COUNT(id) AS reserve FROM `{$this->_dynTable}` WHERE `round_id`=? AND `status`=? AND `zone_id`=?";
        $data = array($roundId, 1, $area);
        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(isset($res[0])) {
            $reserve = $res[0]['reserve'];
        } else {
            $reserve = 0;
        }

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

        $getSql = "SELECT count(id) as sales FROM `{$this->_dynTable}` WHERE `round_id`=? and `zone_id`=? and `opid`=? and status in (2, 3)";
        $data = array($roundId, $areaId, $resellerId);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(isset($res[0])) {
            return $res[0]['sales']; 
        } else {
            return 0;
        }
    }

}