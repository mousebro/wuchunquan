<?php
/**
 * 分销商库存管理相关的类库 
 *
 * @author dwer
 * @time 2016-01-20 18:45
 */
namespace Model\Product;
use Library\Model;

class SellerStorage extends Model{
    private $_infoTable    = 'pft_resellers_storage_info';
    private $_fixedTable   = 'pft_resellers_storage_fixed';
    private $_dynamicTable = 'pft_resellers_storage_dynamic';
    private $_publicTable  = 'pft_resellers_storage_public';
    private $_logTable     = 'pft_resellers_storage_log';
    private $_usedTable    = 'pft_reseller_storage_used';

    private $_orderTable    = 'uu_ss_order';
    private $_detailTable   = 'uu_order_fx_details';
    private $_ticketTable   = 'uu_jq_ticket';
    private $_productTable  = 'uu_products';

    private $_evoluteTable  = 'pft_p_apply_evolute';
    private $_salesTable    = 'pft_product_sale_list';

    private $_memberTable   = 'pft_member';

    private $_setLogPath = 'product/seller_storage_set';
    private $_getLogPath = 'product/seller_storage_get';

    /**
     * 初始化函数
     * 
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 是否用户库存管理权限
     * @author dwer
     * @date   2016-03-15
     *
     * @param  $dtype 用户的类型
     * @param  $authStr 权限序列
     * @return
     */
    public static function haveStorageAuth($dtype, $authStr) {
        $storageType = 'resellerStorage';
        if($dtype == 0) {
            //供应商本来就有权限
            return true;
        } else if($dtype == 6) {
            //员工账号,判断权限
            $authStr = strval($authStr);
            $authArr = explode(',', $authStr);
            if(in_array($storageType, $authArr)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     *  产品是否开启分销库存功能
     * @author dwer
     * @date   2016-03-06
     *
     * @param  string $pid
     * @param  string $applyDid
     * @return boolean
     *             true：开启，false：未开启
     */
    public function isOpenSellerStorage($pid, $applyDid = false){
        $info = $this->getInfo($pid, $applyDid);

        if(!$info) {
            return false;
        }

        if($info['status'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 分销商是否可以显示配置按钮
     * @author dwer
     * @date   2016-03-06
     *
     * @param  string $pid 产品ID
     * @param  int 登录用户ID
     * @param  string $date 日期 - 2016-10-23
     * @return int 0=不显示，1=显示
     */
    public function isShowSetting($pid, $memberId, $setterId, $date = false, $attr = false) {
        $applyDid = $this->getApplyDid($pid);
        if(!$applyDid || !$memberId) {
            return 0;
        }

        //只有一级分销商才能配置分销库存
        if($applyDid != $setterId) {
            return 0;
        }

        $res = $this->isOpenSellerStorage($pid, $applyDid);
        if(!$res) {
            return 0;
        }

        // if($date !== false) {
        //     $tmp = strtotime($date);
        //     if(!$tmp) {
        //         $date = date('Ymd');
        //     } else {
        //         $date = date('Ymd', $tmp);
        //     }
        // } else {
        //     $date = date('Ymd');
        // }

        //判断分销商所在的层级，如果不是一级分销商的话，就不能设置
        $level = $this->getResellerLevel($pid, $memberId);
        if($level !== 2) {
            return 0;
        }

        //判断上级设置库存的模式
        // $publicInfo = $this->getAvailablePublic($applyDid, $pid, $date, $attr);
        // if($publicInfo && $publicInfo['mode'] == 1) {
        //      return 1;
        // } else {
        //     return 0;
        // }

        return 1;
    }

    /**
     * 获取库存通用配置
     * 
     * @param pid 产品ID
     *
     */
    public function getInfo($pid, $applyDid = false) {
        if(!$pid) {
            return false;
        }

        if(!$applyDid) {
            //获取供应商ID
            $tmp = $this->getApplyDid($pid);

            if(!$tmp) {
                return false;
            }
            $setterUid = $tmp;
        } else {
            $setterUid = $applyDid;
        }

        $pid        = intval($pid);
        $setterUid  = intval($setterUid);
        $res         = $this->table($this->_infoTable)->where(array('pid' => $pid, 'setter_uid' => $setterUid))->find();

        //如果获取不到数据，就进行初始化
        if(!$res) {
            $data = array(
                'pid'         => $pid, 
                'setter_uid'  => $setterUid,
                'update_time' => time()
            );

            $this->table($this->_infoTable)->add($data);

            //重新获取数据
            $res = $this->table($this->_infoTable)->where(array('pid' => $pid, 'setter_uid' => $setterUid))->find();
        }

        //返回配置信息
        return $res;
    }

    /**
     *  移除分销商，顺便移除给该分销商配置分销库存
     * @author dwer
     * @date   2016-03-17
     *
     * @param  $resellerId 分销商ID
     * @param  $pid 产品ID
     * @param  $setterId 供应商ID
     * @return
     */
    public function removeReseller($resellerId, $pid = false, $setterId = false, $attr = false) {
        if(!$resellerId) {
            return false;
        }

        if($attr !== false) {
            $attr = strval($attr);
        }

        if($pid !== false) {
            if(!$pid) {
                return false;
            }
        }

        if($setterId !== false) {
            if(!$setterId) {
                return false;
            }
        }

        //获取已经配置好的数据
        $nowDate = date('Ymd');
        $where   = array(
            'reseller_uid' => $resellerId
        );
        $where['_string'] = "date=0 OR date >= {$nowDate}";

        if($pid) {
            $where['pid'] = $pid;
        }

        if($setterId) {
            $where['setter_uid'] = $setterId;
        }

        //最多就365天的数据
        $page = '1,365';
        $field = 'date,pid,setter_uid';
        $order = "date asc";

        $list = $this->table($this->_fixedTable)->where($where)->field($field)->order($order)->page($page)->select();

        $mark = true;
        $this->startTrans();

        foreach($list as $item) {
            //清除数据
            $res = $this->_removeRsellerSetting($item['pid'], $item['setter_uid'], $resellerId, $item['date'], $attr);

            if($res === false) {
                $mark = false;
            }
        }

        //写日志
        $logData         = ['ac' => 'removeReseller'];
        $logData['data'] = [$resellerId, $pid , $setterId ];
        $logData['rs']   = $mark;
        $this->_log($logData, 'get');

        //返回结果
        if($mark) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 删除供应商设置的库存配置
     * @author dwer
     * @date   2016-03-17
     *
     * @param  $resellerId 分销商ID
     * @param  $pid 产品ID
     * @param  $setterId 供应商ID
     * @return
     */
    public function removeSetter($pid, $setterId, $attr = false) {
        if(!$pid || !$setterId) {
            return false;
        }

        if($attr !== false) {
            $attr = strval($attr);
        }

        $this->startTrans();

        $res = $this->_removeSetterSetting($pid, $setterId, $attr);

        //写日志
        $logData         = ['ac' => 'removeSetter'];
        $logData['data'] = [$pid , $setterId ];
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
     * 获取产品信息
     * @author dwer
     * @date   2016-03-08
     *
     * @param  [type] $pid
     * @return [type]
     */
    public function getProductInfo($pid) {
        $field = "l.title as ltitle, t.title as ttitle, p.id";
        $table = "{$this->_productTable} p";
        $where = "p.id = {$pid}";
        $join  = "left join uu_jq_ticket as t on p.id=t.pid left join uu_land l on p.contact_id=l.id";

        $info = $this->table($table)->field($field)->join($join)->where($where)->find();

        return $info;
    }

    /**
     * 获取产品分销库存的开启状态
     * 
     * @param pid 产品ID
     * @return bool
     */
    public function getInfoStatus($pid, $applyDid = false) {
        if(!$pid) {
            return false;
        }

        if(!$applyDid) {
            //获取供应商ID
            $tmp = $this->getApplyDid($pid);

            if(!$tmp) {
                return false;
            }
            $setterUid = $tmp;
        } else {
            $setterUid = $applyDid;
        }

        $pid        = intval($pid);
        $setterUid  = intval($setterUid);
        $res         = $this->table($this->_infoTable)->where(array('pid' => $pid, 'setter_uid' => $setterUid))->find();

        if($res && $res['status'] == 1) {
            //开启
            return true;
        } else {
            return false;
        }
    }

    /**
     * 配置库存通常配置
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID
     * @param status 是否开启 - 0=关闭，1=开启
     * 
     */
    public function setInfo($pid, $setterUid, $status = 0) {
        $resData = array();

        if(in_array($status, array(0, 1))) {
            $resData['status'] = intval($status);
        } else {
            $resData['status'] = 0;
        }

        if(!$resData) {
            return false;
        }

        $resData['update_time'] = time();

        //更新数据
        $where = array(
            'pid'             => $pid,
            'setter_uid'     => $setterUid
        );

        $res = $this->table($this->_infoTable)->where($where)->save($resData);

        //写日志
        $logData         = ['ac' => 'setInfo'];
        $logData['data'] = array_merge($where, $resData);
        $logData['rs']   = $res;
        $this->_log($logData, 'set');

        return $res === false ? false : true;
    }

    /**
     * 获取默认情况下分销商的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerArr 需要获取的分销商的ID数组
     *           array('1101', '2203', '444322')
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回固定库存数组
     *  
     */
    public function getCommonFixed($pid, $setterUid, $resellerArr, $attr = false) {
         if(!$resellerArr || !is_array($resellerArr)) {
             return array();
         }

         $arr = array();
         foreach($resellerArr as $item) {
             if(intval($item)) {
                 $arr[] = intval($item);
             }
         }

         if(!$arr) {
             return array();
         }

         $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,  
            'reseller_uid' => array('in', $arr),
            'date'         => 0
         );

         if($attr) {
             $where['special_attr'] = $attr;
         }

         $tmp = $this->table($this->_fixedTable)->where($where)->select();

         //处理数据
         $res = array();
         foreach($tmp as $item) {
             $res[$item['reseller_uid']] = $item;
         }
         return $res;
    }

    /**
     * 获取指定日期下分销商的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerArr 需要获取的分销商的ID数组
     *           array('1101', '2203', '444322')
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回固定库存数组
     *  
     */
    public function getSpecialFixed($pid, $setterUid, $resellerArr, $date, $attr = false) {

         if(!$resellerArr || (!is_array($resellerArr) || !$date)) {
             return array();
         }

         $arr = array();
         foreach($resellerArr as $item) {
             if(intval($item)) {
                 $arr[] = intval($item);
             }
         }

         if(!$arr) {
             return array();
         }

         $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,  
            'reseller_uid' => array('in', $arr),
            'date'         => $date
         );

         if($attr) {
             $where['special_attr'] = $attr;
         }

         $tmp = $this->table($this->_fixedTable)->where($where)->select();

         //处理数据
         $res = array();
         foreach($tmp as $item) {
             $res[$item['reseller_uid']] = $item;
         }

         return $res;
    }


    /**
     * 获取默认情况下商品的动态库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回动态库存数据
     *   
     */
    public function getCommonDynamic($pid, $setterUid, $attr = false) {
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,  
            'date'         => 0
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_publicTable)->where($where)->find();
        return $res;
    }

    /**
     * 获取指定日期下商品的动态库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回动态库存数据
     *   
     */
    public function getSpecialDynamic($pid, $setterUid, $date, $attr = false) {
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,  
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_publicTable)->where($where)->find();
        return $res;
    }

    /**
     * 设置默认情况下产品的公共信息
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param mode 设定库存模式:1=固定库存， 2=动态库存
     * @param dayNum 设定的日总库存量
     * @param setNum 给分销商设置的固定库存之和
     * @param level 层级：1=供应商给一级分销商设置，2=一级分销商给二级分销商设置
     * @param useLimit 动态库存限额
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    public function setCommonDynamic($pid, $setterUid, $dayNum, $mode, $setNum, $level, $useLimit = 0, $attr = false) {
        //设置数据
        $data = array(
            'pid'        => $pid,
            'setter_uid' => $setterUid,
            'date'       => 0
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }
 
        //获取数据，如果存在就更新，不存在就新增
        $tmp = $this->table($this->_publicTable)->where($data)->find();

        if($tmp) {
            //更新数据
            $newData = array(
                'total_num'   => intval($dayNum),
                'set_num'     => intval($setNum),
                'update_time' => time(),
                'mode'        => $mode,
                'use_limit'   => $useLimit,
                'level'       => $level
            );

            $res = $this->table($this->_publicTable)->where($data)->save($newData);
            
        } else {
            //新增数据
            $data['total_num']   = intval($dayNum);
            $data['set_num']     = intval($setNum);
            $data['update_time'] = time();
            $data['mode']        = $mode;
            $data['use_limit']   = $useLimit;
            $data['level']       = $level;
            
            $res =  $this->table($this->_publicTable)->add($data);
        }

        return $res === false ? false : true;
    }

    /**
     * 设置指定某天时产品的公共信息
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param date 日期 - 20161023
     * @param dayNum 日总库存量
     * @param setNum 设定的未分配库存或共享库存
     * @param level 层级：1=供应商给一级分销商设置，2=一级分销商给二级分销商设置
     * @param useLimit 动态库存限额
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    public function setSpecialDynamic($pid, $setterUid, $date, $dayNum, $mode, $setNum, $level, $useLimit = 0, $attr = false) {
        //设置数据
        $data = array(
            'pid'        => $pid,
            'setter_uid' => $setterUid,
            'date'       => $date
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }

        //获取数据，如果存在就更新，不存在就新增
        $tmp = $this->table($this->_publicTable)->where($data)->find();

        if($tmp) {
            //更新数据
            $newData = array(
                'total_num'   => intval($dayNum),
                'set_num'     => intval($setNum),
                'update_time' => time(),
                'mode'        => $mode,
                'use_limit'   => $useLimit,
                'level'       => $level
            );

            $res = $this->table($this->_publicTable)->where($data)->save($newData);
            
        } else {
            //新增数据
            $data['total_num']   = intval($dayNum);
            $data['set_num']     = intval($setNum);
            $data['update_time'] = time();
            $data['mode']        = $mode;
            $data['use_limit']   = $useLimit;
            $data['level']       = $level;
            
            $res =  $this->table($this->_publicTable)->add($data);
        }

        return $res === false ? false : true;
    }

    /**
     * 插入库存公共信息
     * 
     * @author dwer
     * @DateTime 2016-02-22T13:53:25+0800
     * 
     * @param   $pid       产品ID
     * @param   $setterUid 供应商ID或是上级分销商ID
     * @param   $date      日期 - 20161023
     * @param   $dayNum    日总库存量
     * @param   $mode      库存类型 
     * @param   $setNum    动态日库存量
     * @param   $useLimit  分销商动态库存库存限额
     * @param   $attr      产品属性，这边可能是场次
     *
     */
    public function insertSpecialDynamic($pid, $setterUid, $date, $dayNum, $mode, $useLimit = 0, $setNum = 0, $attr = false) {
        $data = array(
            'pid'         => $pid,
            'setter_uid'  => $setterUid,
            'date'        => $date,
            'mode'        => $mode,
            'day_num'     => $dayNum,
            'set_num'     => $setNum,
            'use_limit'   => $useLimit,
            'update_time' => time(),
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }

        $res =  $this->table($this->_publicTable)->add($data); 

        return $res === false ? false : true;
    }

    /**
     * 获取已经给分销商设置的固定库存的数量
     * 
     * @author dwer
     * @DateTime 2016-02-23T17:25:30+0800
     * 
     * @param $setterUid 上级供应商ID
     * @param $pid 产品ID
     * @param $date 日期 20161023 或是 0
     * @param $excludeResellerArr 需要排除掉的分销商的ID数组
     * 
     * @return int
     */
    public function getSetFixedNums($setterUid, $pid, $date, $excludeResellerArr = array(), $attr = false) {
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterUid,
            'date'       => $date,
        );

        $field = 'sum(fixed_num) as nums';

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $arr = array();
        if($excludeResellerArr) {
            foreach($excludeResellerArr as $item) {
                if(intval($item)) {
                     $arr[] = intval($item);
                 }
            }
        }

        if($arr) {
            $where['reseller_uid'] = array('not in', $arr);
        }

        $res = $this->table($this->_fixedTable)->field($field)->where($where)->find();
        if($res) {
            return $res['nums'];
        } else {
            return 0;
        }
    }


    /**
     * 设置固定库存模式的分销库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param dayNum 日库存量
     * @param setNum 给分销商设置的固定库存之和
     * @param level 层级：1=供应商给一级分销商设置，2=一级分销商给二级分销商设置
     * @param setData 分销商固定库存数组
     *        [{'分销商ID' : 444}, {'分销商ID' : 444}]
     * @param date 设置日期 20160123 或是 0
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    public function setFixedStorage($pid, $setterUid, $dayNum, $setNum, $level , $setData, $date = 0, $attr = false) {
        //开启事务
        $this->startTrans();
        $mark = true;

        //根据层级设置日库存，level=1时为日总库存量设定值，level=2时该值不起作用
        if($level == 2) {
            $dayNum = 0;
        }

        if($date === 0) {
            //默认设置
                
            //设置公共信息
            $res = $this->setCommonDynamic($pid, $setterUid, $dayNum, 1, $setNum, $level, 0, $attr);

            if($res) {
                //设置固定库存的值
                foreach($setData as $key => $val) {
                    $resellerUid = $key;
                    $fixNum      = $val;

                    $tmp = $this->setCommonFixed($pid, $setterUid, $resellerUid, $fixNum, $attr);

                    if(!$tmp) {
                        break;
                        $mark = false;
                    }
                }
            } else {
                //保存失败
                $mark = false;
            }
        } else {
            //具体日期设置
            
            //设置公共信息
            $res = $this->setSpecialDynamic($pid, $setterUid, $date, $dayNum, 1, $setNum, $level, 0, $attr);

            if($res) {
                //设置固定库存的值
                foreach($setData as $key => $val) {
                    $resellerUid = $key;
                    $fixNum      = $val;

                    $tmp = $this->setSpecialFixed($pid, $setterUid, $resellerUid, $date, $fixNum, $attr);

                    if(!$tmp) {
                        break;
                        $mark = false;
                    }
                }
            } else {
                //保存失败
                $mark = false;
            }
        }

        //写日志
        $logData         = ['ac' => 'setStorage'];
        $logData['data'] = [$pid, $setterUid, $dayNum, $setNum, $level , $setData, $date];
        $logData['rs']   = $mark;
        $this->_log($logData, 'set');

        if($mark) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 设置动态库存模式的分销库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param dayNum 日库存量
     * @param level 层级：1=供应商给一级分销商设置，2=一级分销商给二级分销商设置
     * @param useLimit 动态库存限额
     * @param setData 分销商固定库存数组
     *        [{'分销商ID' : 444}, {'分销商ID' : 444}]'
     * @param dynamicNum 动态库存
     * @param date 设置日期 20160123 或是 0
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回动态库存数据
     *   
     */
    public function setDynamicStorage($pid, $setterUid, $dayNum, $level, $useLimit, $setData, $dynamicNum, $date = 0, $attr = false) {
        //开启事务
        $this->startTrans();
        $mark = true;

        //根据层级设置日库存，level=1时为日总库存量设定值，level=2时该值不起作用
        if($level == 2) {
            $dayNum = 0;
        }

        if($date === 0) {
            //默认设置

            //设置公共信息
            $res = $this->setCommonDynamic($pid, $setterUid, $dayNum, 2, $dynamicNum, $level, $useLimit, $attr);

            if($res) {
                //设置固定库存的值
                foreach($setData as $key => $val) {
                    $resellerUid = $key;
                    $fixNum      = $val;

                    $tmp = $this->setCommonFixed($pid, $setterUid, $resellerUid, $fixNum, $attr);

                    if(!$tmp) {
                        break;
                        $mark = false;
                    }
                }
            } else {
                //保存失败
                $mark = false;
            }
        } else {
            //具体日期设置

            //设置公共信息 
            $res = $this->setSpecialDynamic($pid, $setterUid, $date, $dayNum, 2, $dynamicNum, $level, $useLimit, $attr);

            if($res) {
                //设置固定库存的值
                foreach($setData as $key => $val) {
                    $resellerUid = $key;
                    $fixNum      = $val;

                    $tmp = $this->setSpecialFixed($pid, $setterUid, $resellerUid, $date, $fixNum, $attr);

                    if(!$tmp) {
                        break;
                        $mark = false;
                    }
                }
            } else {
                //保存失败
                $mark = false;
            }
        }

        //写日志
        $logData         = ['ac' => 'setStorage'];
        $logData['data'] = [$pid, $setterUid, $dayNum, $level, $useLimit, $setData, $dynamicNum, $date, $attr];
        $logData['rs']   = $mark;
        $this->_log($logData, 'set');

        if($mark) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }


    /**
     * 设置默认情况下产品的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 下级分销商ID
     * @param setNum 设定的日库存量
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回动态库存数据
     *   
     */
    public function setCommonFixed($pid, $setterUid, $resellerUid, $setNum, $attr = false) {
        $data = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => 0
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }
 
        //获取数据，如果存在就更新，不存在就新增
        $tmp = $this->table($this->_fixedTable)->where($data)->find();

        if($tmp) {
            //更新数据
            $setNum  = intval($setNum);
            $newData = array(
                'fixed_num'   => $setNum,
                'update_time' => time()
            );

            $res = $this->table($this->_fixedTable)->where($data)->save($newData);
        } else {
            //新建数据
            $data['fixed_num']   = $setNum;
            $data['update_time'] = time();

            $res = $this->table($this->_fixedTable)->add($data);
        }

        return $res === false ? false : true;
    }

    /**
     * 
     * 设置指定某天情况下产品的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param setNum 设定的日库存量
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回动态库存数据
     *   
     */
    public function setSpecialFixed($pid, $setterUid, $resellerUid, $date, $setNum, $attr = false) {
        $data = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }
 
        //获取数据，如果存在就更新，不存在就新增
        $tmp = $this->table($this->_fixedTable)->where($data)->find();

        if($tmp) {
            //更新数据
            $setNum = intval($setNum);
            $newData = array(
                'fixed_num'   => $setNum,
                'update_time' => time()
            );

            $res = $this->table($this->_fixedTable)->where($data)->save($newData);
        } else {
            //新建数据
            $setNum = intval($setNum);
            $res = $this->insertSpecialFixed($pid, $setterUid, $resellerUid, $date, $setNum, $attr);
        }

        return $res === false ? false : true;
    }

    /**
     * 
     * 插入指定某天情况下产品的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param setNum 设定的日库存量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    public function insertSpecialFixed($pid, $setterUid, $resellerUid, $date, $setNum, $attr = false) {
        $data = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date,
            'fixed_num'    => intval($setNum),
            'update_time'  => time()
        );

        if($attr) {
            $data['special_attr'] = $attr;
        }

        $res = $this->table($this->_fixedTable)->add($data);
        return $res === false ? false : true;
    }

    /**
     * 获取分销商或是供应商的最大可卖库存
     *     -- 如果是自供自销的话，只能使用未分配库存或是动态库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回最大可卖库存数组
     *  ['max_fixed' => 100, 'max_dynamic' => 20]
     */
    public function getMaxStorage($pid, $setterUid, $resellerUid, $date, $attr = false) {
        //获取共用配置
        $publicInfo = $this->getAvailablePublic($setterUid, $pid, $date, $attr);
        if(!$publicInfo) {
            return array('max_fixed' => 0, 'max_dynamic' => 0);
        }

        if($setterUid == $resellerUid) {
            //自供自销，或是有设置分销库存的一级分销商
            if ($publicInfo['level'] == 1) {
                //如果是自供自销的话
                $availableDynamic = 0;
                $availableFixed   = 0;
                if($publicInfo['mode'] == 2) {
                    //动态库存模式，只能使用动态库存
                    $tmpDynamic       = intval($publicInfo['total_num']) - intval($publicInfo['set_num']);
                    $usedNum          = $this->getUsedDynamic($pid, $resellerUid, $date, $attr);
                    $availableDynamic = intval($tmpDynamic - $usedNum);
                    $availableDynamic = $availableDynamic > 0 ? $availableDynamic : 0;

                } else {
                    //固定库存模式，只能使用未分配库存
                    $availableFixed = intval($publicInfo['total_num']) - intval($publicInfo['set_num']);
                }

                return array('max_fixed' => $availableFixed, 'max_dynamic' => $availableDynamic);
            } else {
                //有设置分销库存的一级分销商
                $totalNum = $this->getSettedDayNum($pid, $resellerUid, $date, $attr);

                $availableDynamic = 0;
                $availableFixed   = 0;
                if($publicInfo['mode'] == 2) {
                    //动态库存模式，只能使用动态库存
                    $tmpDynamic       = intval($totalNum) - intval($publicInfo['set_num']);
                    $usedNum          = $this->getUsedDynamic($pid, $resellerUid, $date, $attr);
                    $availableDynamic = intval($tmpDynamic - $usedNum);
                    $availableDynamic = $availableDynamic > 0 ? $availableDynamic : 0;
                } else {
                    //固定库存模式，只能使用未分配库存
                    $availableFixed = intval($totalNum) - intval($publicInfo['set_num']);
                }

                return array('max_fixed' => $availableFixed, 'max_dynamic' => $availableDynamic);
            }
        } else {
            if($publicInfo['mode'] == 2) {
                //动态库存模式
                $res = $this->getFixedInfo($pid, $setterUid, $resellerUid, $publicInfo['date'], $attr);

                $fixedNum = 0;
                if($res) {
                    $fixedNum = intval($res['fixed_num']);
                }

                if($publicInfo['level'] == 1) {
                    $dayNum = intval($publicInfo['total_num']);
                } else {
                    $dayNum = $this->getSettedDayNum($pid, $setterUid, $date);
                }

                $setNum   = $dayNum - intval($publicInfo['set_num']);
                $useLimit = intval($publicInfo['use_limit']);
                $usedNum  = $this->getUsedDynamic($pid, $setterUid, $date, $attr);

                //动态库存的余量
                $leftDynamic = $setNum - $usedNum;

                //获取用户已经使用的库存，计算用户的动态库存限额余量
                $usedLog = $this->getUsedStorage($pid, $setterUid, $resellerUid, $date, $attr);
                $leftLimit = $useLimit - intval($usedLog['dynamic']);

                //返回
                $resDynamic = min($leftDynamic, $leftLimit);
                return array('max_fixed' => $fixedNum, 'max_dynamic' => $resDynamic);
            } else {
                //固定库存模式
                $res = $this->getFixedInfo($pid, $setterUid, $resellerUid, $publicInfo['date'], $attr);

                if(!$publicInfo) {
                    return array('max_fixed' => 0, 'max_dynamic' => 0);
                } else {
                    //直接返回固定库存
                    $fixedNum = intval($res['fixed_num']);

                    return array('max_fixed' => $fixedNum, 'max_dynamic' => 0);
                }
            }
        }
    }

    /**
     * 获取分销商已经使用的库存
     *
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     *
     * @return 库存使用数组
     *      ['fixed' => 20, 'dynamic' => 2]
     */
    public function getUsedStorage($pid, $setterUid, $resellerUid, $date, $attr) {
        //获取固定库存
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        $tmp = $this->table($this->_usedTable)->where($where)->find();

        $res = array('fixed' => 0, 'dynamic' => 0);
        if($tmp) {
            $res['fixed']   = intval($tmp['fixed_num_used']);
            $res['dynamic'] = intval($tmp['dynamic_num_used']);
        }

        return $res;
    }

    /**
     * 获取指定日期下分销商的库存使用量数组
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerArr 需要获取的分销商的ID数组
     *           array('1101', '2203', '444322')
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     *
     * @return 返回固定库存数组
     *  
     */
    public function getUsedStorageArr($pid, $setterUid, $resellerArr, $date, $attr = false) {

         if(!$resellerArr || (!is_array($resellerArr) || !$date)) {
             return array();
         }

         $arr = array();
         foreach($resellerArr as $item) {
             if(intval($item)) {
                 $arr[] = intval($item);
             }
         }

         if(!$arr) {
             return array(); 
         }

         $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,  
            'reseller_uid' => array('in', $arr),
            'date'         => $date
         );
         $field = 'reseller_uid, fixed_num_used, dynamic_num_used';

         if($attr) {
             $where['special_attr'] = $attr;
         }

         $tmp = $this->table($this->_usedTable)->where($where)->field($field)->select();

         //处理数据
         $res = array();
         foreach($tmp as $item) {
            $res[$item['reseller_uid']] = $item;
         }

         return $res;
    }

    /**
     * 获取可用的分销库存 
     * @author dwer
     * @date   2016-03-08
     *
     * @param   $pid 产品ID
     * @param   $setterId 供应商ID
     * @param   $resellerId 分销商ID
     * @param   $date 查询日期
     * @return 
     */
    public function getLeftStorageNum($pid, $setterId, $resellerId, $date, $attr = false) {
        //参数判断
        if(!$pid || !$setterId || !$resellerId || !$date) {
            return -1;
        }

        //日期处理
        $tmp = strtotime($date);
        if(!$tmp) {
            return -1;
        }
        $date = date('Ymd', $tmp);

        //判断当前分销库存是否开启
        $status = $this->getInfoStatus($pid);
        if($status === false) {
            //没有开启分销库存
            return false;
        }

        //获取需要扣除库存的分销商
        $res = $this->_getLastResellers($resellerId, $pid, $setterId, $date, $attr);

        //如果供应链上都没有设置库存
        if(!$res) {
            //没有开启分销库存
            return false;
        }

        //获取当前可用使用的最大库存
        $needArr = $res[0];

        $resSeller = $needArr['first'];
        $resSetter = $needArr['second'];
        
        $leftNums = $this->_getLeftNums($pid, $resSetter, $resSeller, $date);

        return $leftNums;
    }


    /**
     * 判断库存是否充足
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 2016-10-23
     * @param buyNum 需要购买的数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool / int
     *         -1    : 参数错误
     *         true  ：库存充足
     *         false : 库存不足
     */
    public function isStorageAvailable($pid, $setterUid, $resellerUid, $date, $buyNum, $attr = false) {
        //参数判断
        $buyNum = intval($buyNum);
        if(!$pid || !$setterUid || !$resellerUid || !$date || ($buyNum <=0)) {
            return -1;
        }

        //日期处理
        $tmp = strtotime($date);
        if(!$tmp) {
            return -1;
        }
        $date = date('Ymd', $tmp);

        //写日志
        $logData         = ['ac' => 'storage_available_init'];
        $logData['data'] = [$pid, $setterUid, $resellerUid, $date, $buyNum, $attr];
        $logData['rs']   = 1;
        $this->_log($logData, 'get');

        //判断当前分销库存是否开启
        $status = $this->getInfoStatus($pid);
        if($status === false) {
            //分销库存没有开启，那分销库存就是充足的
            return true;
        }

        //获取需要扣除库存的分销商
        $res = $this->_getLastResellers($resellerUid, $pid, $setterUid, $date, $attr);

        //如果供应链上都没有设置库存
        if(!$res) {
            return true;
        }

        //这边只要判断最末端的分销商库存就可以了
        $needArr = $res[0];

        $resSeller = $needArr['first'];
        $resSetter = $needArr['second'];

        $tmp = $this->_isStorageEnough($pid, $resSetter, $resSeller, $date, $buyNum, $attr);

        //写日志
        $logData         = ['ac' => 'storage_available_return'];
        $logData['data'] = [$pid, $setterUid, $resellerUid, $date, $buyNum, $resSeller, $resSetter, $attr];
        $logData['rs']   = $tmp;
        $this->_log($logData, 'get');

        if($tmp) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 下单使用分销商的可用库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 2016-10-23
     * @param buyNum 需要购买的数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool / int
     *         -1    : 参数错误
     *         true  ：库存充足
     *         false : 库存不足
     *   
     */
    public function useStorage($orderId, $pid, $setterUid, $resellerUid, $date, $buyNum, $attr = false) {
        //参数判断
        $buyNum = intval($buyNum);
        if(!$orderId || !$pid || !$setterUid || !$resellerUid || !$date || ($buyNum <=0)) {
            return -1;
        }

        //日期处理
        $tmp = strtotime($date);
        if(!$tmp) {
            return -1;
        }
        $date = date('Ymd', $tmp);

        //判断当前分销库存是否开启
        $status = $this->getInfoStatus($pid);
        if($status === false) {
            //分销库存没有开启，那分销库存就是充足的
            return true;
        }

        //获取需要扣除库存的分销商
        $res = $this->_getLastResellers($resellerUid, $pid,$setterUid, $date, $attr);

        //该供应链没有设置分销商库存
        if(!$res) {
            return true;
        }

        //这边开启事务
        $mark = true;
        $this->startTrans();

        //按供应链扣除相应的库存
        foreach($res as $item) {
            $resReseller = $item['first'];
            $resSetter   = $item['second'];
            $selfUse     = isset($item['self_use']) ? true : false;

            if($selfUse) {
                //一级分销商还没有给下级分销配置的时候，也要记录自己使用量
                $re = $this->_useupSelfStorage($orderId, $pid, $resSetter, $resReseller, $date, $buyNum, $attr);
            } else {
                //供应链上的分销商的都要扣除
                $re = $this->_useupStorage($orderId, $pid, $resSetter, $resReseller, $date, $buyNum, $attr);
            }

            if(!$re) {
                $mark = false;
                break;
            }
        }

        //写日志
        $logData         = ['ac' => 'useStorage'];
        $logData['data'] = [$orderId, $pid, $setterUid, $resellerUid, $date, $buyNum, $attr];
        $logData['rs']   = $mark;
        $this->_log($logData, 'get');

        if($mark) {
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
     * @date   2016-03-14
     *
     * @param  $orderId 订单ID
     * @param  $cancedNum 需要退掉的数量
     * @return
     */
    public function changeStorage($orderId, $cancedNum) {
        $cancedNum = intval($cancedNum);
        $orderId   = (string)$orderId;
        if(!$orderId || $cancedNum <= 0) {
            return false;
        }

        //获取基础信息
        $baseData = $this->_getBaseDate($orderId);
        if(!$baseData) {
            return false;
        }

        $pid         = $baseData['pid'];
        $setterUid   = $baseData['setterUid'];
        $resellerUid = $baseData['resellerUid'];
        $date        = $baseData['date'];
        $attr        = $baseData['attr'];

        //开启事务
        $this->startTrans();

        //直接从历史库存中获取消耗的数量
        $logList = $this->table($this->_logTable)->where(array('order_num' => $orderId))->select();

        $recoverNum = 0;
        foreach($logList as $item) {
            //判断是不是已经恢复过了
            if($item['recover_time'] > 0) {
                //已经恢复过，就不能进行修改了
                continue;
            }

            $resReseller = $item['reseller_id'];
            $resSetter   = $item['setter_id'];

            $fixedNum        = intval($item['fixed_num']);
            $dynamicNum      = intval($item['dynamic_num']);

            $logFixedNum   = 0;
            $logDynamicNum = 0;

            //计算从哪里恢复库存
            if($dynamicNum >= $cancedNum) {
                //动态库存就够了
                $logDynamicNum = $cancedNum;
            } else {
                $logDynamicNum = $dynamicNum;
                $logFixedNum   = $cancedNum - $dynamicNum;
                $logFixedNum   = $logFixedNum > $fixedNum ? $fixedNum : $logFixedNum;
            }

            //计算剩余的的动态库存是固定库存
            $leftDynamicNum = $dynamicNum - $logDynamicNum;
            $leftFixedNum   = $fixedNum - $logFixedNum;

            //恢复固定库存
            if($logFixedNum > 0) {
                $tmpFixed = $this->_recoverFixed($pid, $resSetter, $resReseller, $date, $logFixedNum, $attr);
                
                if(!$tmpFixed) {
                    $this->rollback();
                    return false;
                }
            }

            //恢复动态库存
            if($logDynamicNum > 0) {
                $tmpDynamic = $this->_recoverDynamic($pid, $resSetter, $resReseller, $date, $logDynamicNum, $attr);

                if(!$tmpDynamic) {
                    $this->rollback();
                    return false;
                }
            }

            //修改这条记录剩余的数据
            $res = $this->_changeLog($item['id'], $leftFixedNum, $leftDynamicNum);
            if(!$res) {
                $this->rollback();
                return false;
            }

            $recoverNum += 1;
        }

        if($recoverNum >=1) {
            //有恢复数据的时候，写日志
            $logData         = ['ac' => 'changeStorage'];
            $logData['data'] = [$orderId, $cancedNum, $recoverNum];
            $logData['rs']   = $recoverNum;
            $this->_log($logData, 'get');
        }

           //提交事务
        $this->commit();

        //返回
        return true;
    }

    /**
     * 退单恢复分销商的可用库存
     * 
     * @param orderId 订单ID
     *
     * @return bool
     *   
     */
    public function recoverStorage($orderId) {
        if(!$orderId) {
            return false;
        }

        //获取基础信息
        $baseData = $this->_getBaseDate($orderId);
        if(!$baseData) {
            return false;
        }

        $pid         = $baseData['pid'];
        $setterUid   = $baseData['setterUid'];
        $resellerUid = $baseData['resellerUid'];
        $date        = $baseData['date'];
        $attr        = $baseData['attr'];

        //开启事务
        $this->startTrans();

        //直接从历史库存中获取消耗的数量
        $logList = $this->table($this->_logTable)->where(array('order_num' => $orderId))->select();

        $recoverNum = 0;
        foreach($logList as $item) {
            //判断是不是已经恢复过了
            if($item['recover_time'] > 0) {
                //已经恢复过，就不再进行恢复了
                continue;
            }        

            $resReseller = $item['reseller_id'];
            $resSetter   = $item['setter_id'];

            $logFixedNum     = $item['fixed_num'];
            $logDynamicNum     = $item['dynamic_num'];

            //恢复固定库存
            if($logFixedNum > 0) {
                $tmpFixed = $this->_recoverFixed($pid, $resSetter, $resReseller, $date, $logFixedNum, $attr);
                
                if(!$tmpFixed) {
                    $this->rollback();
                    return false;
                }
            }

            //恢复动态库存
            if($logDynamicNum > 0) {
                $tmpDynamic = $this->_recoverDynamic($pid, $resSetter, $resReseller, $date, $logDynamicNum, $attr);

                if(!$tmpDynamic) {
                    $this->rollback();
                    return false;
                }
            }

            //将这条记录记录为已经恢复过
            $recoverTmp = $this->table($this->_logTable)->where(array('id' => $item['id']))->save(array('recover_time' => time()));

            if($recoverTmp === false) {
                $this->rollback();
                return false;
            }

            $recoverNum += 1;
        }

        if($recoverNum >=1) {
            //有恢复数据的时候，写日志
            $logData         = ['ac' => 'recoverStorage'];
            $logData['data'] = [$orderId, $recoverNum];
            $logData['rs']   = $recoverNum;
            $this->_log($logData, 'get');
        }

           //提交事务
        $this->commit();

        //返回
        return true;
    }

    /**
     * 获取产品的分销商
     * @author dwer
     *
     * @param  $pid 产品ID
     * @param  $setterUid 供应商ID,或是上级分销商
     * @param  $date 日期 20160123 或是 0 = 默认配置
     * @param  $getDefault 是否获取默认设置
     * @param  $page 页码
     * @param  $limit 条数
     * @param  $search 搜索词
     * @param  $attr 特殊属性
     *
     * @return array
     *  {id : '1000123', dname : '先行', account : '33222', set_num : 10}
     * 
     */
    public function getResellers($pid, $setterUid, $date, $getDefault = false, $page = 1, $size = 20, $search = false, $attr = false) {
        //获取供应商 
        $applyDid = $this->getApplyDid($pid);
        $applyDid = $applyDid ? $applyDid : '';

        //搜索词处理
        if($search !== false) {
            $search = strval($search);
        } else {
            $search = '';
        }

        //总的记录数
        $totalNum = 0;

        //判断是不是产品的直接供应商
        if($setterUid == $applyDid) {
            //从直销表获取数据
            $field = "member.dname, member.id, member.account, member.mobile";
            $table = "{$this->_salesTable} sales";
            $where = "sales.`status`=0 AND `aid`={$setterUid} AND (`pids`='A' OR FIND_IN_SET('{$pid}', `pids`))";
            $join  = "left join {$this->_memberTable} as member on member.id = sales.fid";
            $page  = "{$page},{$size}"; 

            if($search !== '') {
                $where .= " AND (member.dname like('%{$search}%') OR member.account like('%{$search}%') OR member.mobile like('%{$search}%'))";
            }

            $totalNum = $this->table($table)->join($join)->where($where)->count();

            $memberList = $this->table($table)->field($field)->join($join)->where($where)->page($page)->select();
        } else {
            //如果是分销产品的话，从产品分销表获取下级分销商
            $field = "member.dname, member.id, member.account";
            $table = "{$this->_evoluteTable} evolute";
            $join  = "left join {$this->_memberTable} as member on member.id = evolute.fid";
            $page  = "{$page},{$size}";

            $where = array(
                'sid'            => $setterUid,
                'pid'            => $pid,
                'evolute.status' => 0
            );

            if($search !== '') {
                $where['_string'] = " member.dname like ('%{$search}%') OR member.account like ('%{$search}%') OR member.mobile like ('%{$search}%')";
            }

            $totalNum = $this->table($table)->join($join)->where($where)->count();

            //分页获取相应的分销商
            $memberList = $this->table($table)->field($field)->where($where)->page($page)->join($join)->select();

        }

        //获取分销商数组
        $resellerArr = array();
        $memberList = is_array($memberList) ? $memberList : array();
        foreach($memberList as $item) {
            $resellerArr[] = $item['id'];
        }

        $specialData = array();
        if($getDefault) {
            $specialData = $this->getCommonFixed($pid, $setterUid, $resellerArr, $attr);
        } else {
            //获取当天设置的值
            $specialData =  $this->getSpecialFixed($pid, $setterUid, $resellerArr, $date, $attr);
        }

        //获取分销库存使用量
        $usedStorageArr = $this->getUsedStorageArr($pid, $setterUid, $resellerArr, $date, $attr);

        //获取库存设置值
        foreach($memberList as &$item) {
            //获取当天的设置
            $tmpResllerId = $item['id'];
            
            //如果设置了当天的库存，就获取当天的，如果没有就获取默认设置的，如果再没有就为0
            if(isset($specialData[$tmpResllerId])) {
                $item['set_num'] = $specialData[$tmpResllerId]['fixed_num'];
            }else {
                $item['set_num'] = 0;
            }

            if(isset($usedStorageArr[$tmpResllerId])) {
                $item['selled_num'] = $usedStorageArr[$tmpResllerId]['fixed_num_used'] + $usedStorageArr[$tmpResllerId]['dynamic_num_used'];
            }else {
                $item['selled_num'] = 0;
            }
        }

        //返回数据
        return array('list' => $memberList, 'total' => $totalNum);
    }

    /**
     * 该上级供应商是否可以设置分销库存
     * @author dwer
     * @DateTime 2016-02-25T17:42:20+0800
     * 
     * @param    $pid 产品ID
     * @param    $setterId 供应商ID或是上级分销商
     * @return   mixed false ：不能设置，1：直接直接供应商，可以设置，2：分销商，可以设置
     */
    public function isCanSet($pid, $setterId, $date, $attr = false) {
        $applyDid = $this->getApplyDid($pid);
        if(!$applyDid) {
            return false;
        }

        if($applyDid == $setterId) {
            //供应商进行设置
            $info = $this->getInfo($pid, $applyDid);
            if(!$info || $info['status'] == 0) {
                return false;
            } else {
                return 1;
            }
        } else {
            //分销商，判断上级有没有给他设置

            //二手分销产品判断
            $where = array(
                'fid'      => $setterId,
                'pid'      => $pid,
                'aid'      => $applyDid
            );

            $res = $this->table($this->_salesTable)->field('aid')->where($where)->find();
            if(!$res) {
                return false;
            }
            
            //获取上一级给这一级的设置
            $publicInfo = $this->getAvailablePublic($applyDid, $pid, $date, $attr);
            if(!$publicInfo) {
                //如果上级没有设置分销库存,一级分销商也不能设置
                return false;
            }

            if($publicInfo['mode'] == 2) {
                //如果供应商设置的动态库存的模式，那么一级供应商就不能设置
                return false;
            }

            //获取是否有设置固定库存
            $fixInfo = $this->getFixedInfo($pid, $applyDid, $setterId, $publicInfo['date'], $attr);
            
            if(!$fixInfo || ($fixInfo['fixed_num'] <= 0)) {
                return false;
            }

            return 2;
        }
    }

    /**
     * 商品供应商是否设置了默认分销库存 
     * @author dwer
     * @date   2016-03-02
     *
     * @param  [type] $pid 产品ID
     * @return bool
     */
    public function isCanSetDefault($pid) {
        $applyDid = $this->getApplyDid($pid);
        if(!$applyDid) {
            return false;
        }

        $publicInfo = $this->getCommonDynamic($pid, $applyDid);
        if(!$publicInfo || $publicInfo['mode'] == 2) {
            //未设置或是设置的是动态库存
            return false;
        }

        return true;
    }

    /**
     * 复制分销库存
     * @author dwer
     * @DateTime 2016-02-25T17:56:34+0800
     * 
     * @param  $pid        产品ID
     * @param  $setterId   上级供应商ID
     * @param  $sourceDate 源日期
     * @param  $targetDate 目标日期 或是0=默认库存配置
     * @return             
     */
    public function copyStorage($pid, $setterId, $sourceDate, $targetDate, $attr = false ) {
        $this->startTrans();
        $mark = true;

        $res = $this->_deleteAllData($pid, $setterId, $targetDate, $attr);
        if($res) {
            $rs = $this->_copeAllData($pid, $setterId, $sourceDate, $targetDate, $attr);
            if($res) {
                $mark = true;
            } else {
                $mark  = false;
            }
        } else {
            $mark = false;
        }

        //写日志
        $logData         = ['ac' => 'copyStorage'];
        $logData['data'] = [$pid, $setterId, $sourceDate, $targetDate, $attr];
        $logData['rs']   = $mark;
        $this->_log($logData, 'set');

        if($mark) {
            $this->commit();
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     *  将默认配置的固定库存配置复制到具体某天中去
     * @author dwer
     * @date   2016-02-28
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $setterId 上级供应商
     * @param  [type] $date 日期 20161023
     * @param  [type] $excludeResellerArr 需要排除的分销商
     * @param  [type] $attr 特殊属性
     * @return [type]
     */
    public function copyDefaultFixed($pid, $setterId, $date, $excludeResellerArr, $attr = false) {
        //将之前的配置删除
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_publicTable)->where($where)->delete();
        if($res === false) {
            return false;
        }

        $arr = array();
        if($excludeResellerArr) {
            foreach($excludeResellerArr as $item) {
                if(intval($item)) {
                     $arr[] = intval($item);
                 }
            }
        }

        if($arr) {
            $where['reseller_uid'] = array('not in', $arr);
        }

        //将默认配置查询出来
        $where['date'] = 0;
        $field         = 'reseller_uid,fixed_num';
        $fixedList     = $this->table($this->_fixedTable)->where($where)->field($field)->select();
        
        $setData = array();
        $curTime = time();
        $attr    = $attr ? $attr : '';

        foreach($fixedList as $item) {
            $setData[] = array(
                'pid'          => $pid,
                'setter_uid'   => $setterId,
                'reseller_uid' => $item['reseller_uid'],
                'date'         => $date,
                'fixed_num'    => $item['fixed_num'],
                'special_attr' => $attr,
                'update_time'  => $curTime
            );
        }

        //将数据写入
        if($setData) {
            $res = $this->table($this->_fixedTable)->addAll($setData);

            if($res === false) {
                return false;
            }
        }

        return true;
    }    

    /**
     * 获取一个月中每天的配置情况
     * @author dwer
     * @DateTime 2016-02-26T11:08:41+0800
     * 
     * @param   $pid      
     * @param   $setterId 
     * @param   $dayArr
     * @return            
     */
    public function getCalendarSetting($pid, $setterId, $dayArr, $attr = false) {
        //获取当前是处理哪一级
        $applyDid = $this->getApplyDid($pid);
        $level    = $setterId == $applyDid ? 1 : 2;

        $resArr = [];
        foreach($dayArr as $item) {
            //默认没有配置
            $resArr[$item] = ['status' => 1, 'available' => 1];
        }

        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => array('in', $dayArr)
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_publicTable)->where($where)->field('id,date')->select();
        $setArr = [];
        foreach($res as $item) {
            //有进行具体日期的配置
            $resArr[$item['date']]['status'] = 2;

            $setArr[$item['date']] = $item;
        }
        unset($res);

        //获取默认配置
        $where['date'] = 0;
        $defaultInfo = $this->table($this->_publicTable)->where($where)->field('id')->find();

        foreach($resArr as $key =>$item) {
            if($resArr[$key]['status'] != 2) {
                if($defaultInfo) {
                    $resArr[$key]['status'] = 3;
                }
            }

            //判断是不是有效
            if($level == 2) {
                $publicInfo = $this->getAvailablePublic($applyDid, $pid, $key, $attr);

                //判断上级是不是动态库存模式
                if(!$publicInfo || $publicInfo['mode'] == 2) {
                    $resArr[$key]['available'] = 0;
                } else {
                    $info = [];
                    if($resArr[$key]['status'] == 2) {
                        $info = $setArr[$key];
                    } else if($resArr[$key]['status'] == 3) {
                        $info = $defaultInfo;
                    }

                    if($info) {
                        //判断固定库存是不是足够
                        $dayNum = $this->getSettedDayNum($pid, $setterId, $key, $attr);

                        if($dayNum < $info['set_num']) {
                            $resArr[$key]['available'] = 0;
                        }
                    }
                }
            }
        }


        //返回数据
        return $resArr;
    }


    /**
     * 获取固定库存列表
     * @author dwer
     * @date   2016-02-26
     *
     * @param  int $pid 产品ID
     * @param  int $setterId 上级供应商ID
     * @param  date $date 日期
     * @param  int $page 第几页
     * @param  int $limit 条数
     * 
     * @return array
     */ 
    public function getFixedList($pid, $setterId, $date, $page = 1, $limit = 20){
        
        $field = "member.dname, member.account, fixed.fixed_num";
        $table = "{$this->_fixedTable} fixed";
        $join  = "left join {$this->_memberTable} as member on member.id = fixed.reseller_uid";
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $date
        );

        $page    = intval($page);
        $limit   = intval($limit);
        $pageArr = "{$page},{$limit}";
        $res     = $this->table($table)->field($field)->join($join)->where($where)->page($page)->select();

        return $res;
    }

    /**
     *  获取固定库存具体配置
     * @author dwer
     * @date   2016-02-28
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $setterId 上级供应商ID
     * @param  [type] $resellerId 分销商
     * @param  [type] $date 日期
     * @param  boolean $attr
     * @return [type]
     */
    public function getFixedInfo($pid, $setterId, $resellerId, $date, $attr = false){
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterId,
            'reseller_uid' => $resellerId,
            'date'         => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_fixedTable)->where($where)->find();

        return $res;
    }

    /**
     *  获取已经使用掉的动态库存
     * @author dwer
     * @date   2016-02-28
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $setterId 上级供应商ID
     * @param  [type] $date 日期
     * @param  boolean $attr
     * @return [type]
     */
    public function getUsedDynamic($pid, $setterId, $date, $attr = false){
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_dynamicTable)->where($where)->find();
        if($res) {
            return intval($res['used_num']);
        } else {
            return 0;
        }
    }

    /**
     *  获取已经使用掉的未分配库存
     * @author dwer
     * @date   2016-02-28
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $setterId 上级供应商ID
     * @param  [type] $date 日期
     * @param  boolean $attr
     * @return [type]
     */
    public function getUsedFixed($pid, $setterId, $date, $attr = false) {
        $where = array(
            'pid'          => $pid,
            'reseller_uid' => $setterId,
            'date'         => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_usedTable)->where($where)->sum('fixed_num_used');
        if($res) {
            return intval($res);
        } else {
            return 0;
        }
    }

    /**
     * 获取分销商可用的公共配置
     * 如果指定日期有配置存，就使用这个库存，否则使用默认的配置
     * 
     * @author dwer
     * @DateTime 2016-02-23T14:55:37+0800
     * 
     * @param   $setterUid   上级供应商
     * @param   $pid         商品ID
     * @param   $date        具体日期 - 20160212
     * @param   $attr        特殊属性
     * @return               
     */
    public function getAvailablePublic($setterUid, $pid, $date, $attr = false) {
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'date'         => array('in', array($date, 0))  //设定值和默认值一起返回
        );
        $order = 'date desc';
        $limit = '0,1';

        $tmp = $this->table($this->_publicTable)->where($where)->order($order)->limit($limit)->select();

        if(!$tmp) {
            return false;
        } else {
            //第一条数据就是需要的数据
            return $tmp[0];
        }
    }

    /**
     * 获取供应商给分销商设置的固定日库存
     * 
     * @author dwer
     * @date   2016-03-01
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $resellerId
     * @param  [type] $date
     * @return int
     */
    public function getSettedDayNum($pid, $resellerId, $date, $attr = false) {
        $where = array(
            'pid'          => $pid,
            'reseller_uid' => $resellerId,
            'date'         => array('in', array($date, 0))  //设定值和默认值一起返回
        );
        $order = 'date desc';
        $limit = '0,1';
        $field = 'fixed_num';

        $tmp = $this->table($this->_fixedTable)->where($where)->field($field)->order($order)->limit($limit)->select();

        if(!$tmp) {
            return 0;
        } else {
            //第一条数据就是需要的数据
            $dayNum = intval($tmp[0]['fixed_num']);
            return $dayNum;
        }
    }

    /**
     * 获取产品的直接供应商 
     * @author dwer
     * @date   2016-03-02
     *
     * @param  [type] $pid
     * @return [type]
     */
    public function getApplyDid($pid) {
        if(!$pid) {
            return false;
        }

        //获取供应商ID
        $tmp = $this->table($this->_productTable)->where(array('id' => $pid))->field('apply_did')->find();
        if($tmp && $tmp['apply_did']) {
            return $tmp['apply_did'];
        } else {
            return false;
        }
    }

    /**
     * 判断分销商所处的层级
     * @author dwer
     * @date   2016-03-02
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $resellerId 上级供应商
     * @return false = 不在供应链上，1 = 供应商设置，2=一级分销商设置
     */
    public function getResellerLevel($pid, $resellerId) {
        $applyDid = $this->getApplyDid($pid);

        if(!$applyDid) {
            return false;
        }

        if($applyDid == $resellerId) {
            return 1;
        } else {
            //查找在供应链的哪一级
            $where = array(
                'aid' => $applyDid,
                'fid' => $resellerId
            );
            $res = $this->table($this->_salesTable)->field('pids')->where($where)->find();

            if($res) {
                $pids = $res['pids'];
                $tmp = explode(',', $pids);
                if(in_array($pid, $tmp) || $pids == 'A') {
                    return 2;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    /**
     * 删除某个日期下面的分销商库存配置
     * @author dwer
     * @date   2016-03-17
     *
     * @param  $pid 产品ID
     * @param  $setterId 上级供应商
     * @param  $resellerId 分销商ID
     * @param  $date 具体日期 20151023 或是 0 = 默认配置
     * @return
     */
    private function _removeRsellerSetting($pid, $setterId, $resellerId, $date, $attr = false) {
        //获取这天的固定库存配置
        $where = array(
            'pid'          => $pid, 
            'setter_uid'   => $setterId, 
            'reseller_uid' => $resellerId, 
            'date'         => $date, 
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_fixedTable)->field('fixed_num')->where($where)->find();
        if(!$res) {
            return true;
        }

        $fixedNum = intval($res['fixed_num']);

        if($fixedNum > 0) {
            //将public表中的set_num扣除
            $publicWhere = $where;
            unset($publicWhere['reseller_uid']);

            $data = array(
                'update_time' => time(),
                'set_num'     => array('exp', "set_num-{$fixedNum}")
            );

            $res = $this->table($this->_publicTable)->where($publicWhere)->save($data);

            if($res === false) {
                return false;
            }
        }

        //将这条固定库存的记录删除
        $res = $this->table($this->_fixedTable)->where($where)->delete();

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 删除供应商设置的库存配置
     * @author dwer
     * @date   2016-03-17
     *
     * @param  $pid 产品ID
     * @param  $setterId 上级供应商
     * @return
     */
    private function _removeSetterSetting($pid, $setterId, $attr = false) {
        //获取这天的固定库存配置
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterId,
        );

        $nowDate = date('Ymd');
        $where['_string'] = "date=0 OR date >= {$nowDate}";

        if($attr) {
            $where['special_attr'] = $attr;
        }

        //删除公共配置
        $res = $this->table($this->_publicTable)->where($where)->delete();

        if($res === false) {
            return false;
        }

        //删除固定库存配置
        $res = $this->table($this->_fixedTable)->where($where)->delete();

        if($res === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 删除待复制日期的数据
     * @author dwer
     * @DateTime 2016-02-25T17:56:34+0800
     * 
     * @param  $pid        产品ID
     * @param  $setterId   上级供应商ID
     * @param  $sourceDate 源日期
     * @param  $targetDate 目标日期 或是0=默认配置
     * @return bool        
     */
    private function _deleteAllData($pid, $setterId, $date, $attr = false) {
        //查看需要复制的日期是否已经配置
        if($date == 0) {
            $publicInfo = $this->getCommonDynamic($pid, $setterId);
        } else {
            $publicInfo = $this->getSpecialDynamic($pid, $setterId, $date);
        }

        if(!$publicInfo) {
            //如果没有配置过，就直接返回配置成功
            return true;
        }

        //删除公共配置
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $res = $this->table($this->_publicTable)->where($where)->delete();
        if($res === false) {
            return false;
        }

        //删除固定库存配置
        $res = $this->table($this->_fixedTable)->where($where)->delete();
        if($res === false) {
            return false;
        }

        return true;
    }

    /**
     * 复制源日期的数据
     * @author dwer
     * @DateTime 2016-02-25T17:56:34+0800
     * 
     * @param  $pid        产品ID
     * @param  $setterId   上级供应商ID
     * @param  $sourceDate 源日期
     * @param  $targetDate 目标日期 或是0=默认配置
     * @return bool        
     */
    private function _copeAllData($pid, $setterId, $sourceDate, $targetDate, $attr = false) {
        $publiInfo = $this->getSpecialDynamic($pid, $setterId, $sourceDate, $attr);
        if(!$publiInfo) {
            return false;
        }

        $newData                = $publiInfo;
        $newData['update_time'] = time();
        $newData['date']        = $targetDate;
        unset($newData['id']);

        $res = $this->table($this->_publicTable)->add($newData);
        if(!$res) {
            return false;
        }

        $field  = 'reseller_uid,fixed_num';
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $sourceDate
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $fixedList = $this->table($this->_fixedTable)->where($where)->field($field)->select();

        $setData = array();
        $curTime = time();
        $attr = $attr ? $attr : '';

        foreach($fixedList as $item) {
            $setData[] = array(
                'pid'          => $pid,
                'setter_uid'   => $setterId,
                'reseller_uid' => $item['reseller_uid'],
                'date'         => $targetDate,
                'fixed_num'    => $item['fixed_num'],
                'special_attr' => $attr,
                'update_time'  => $curTime
            );
        }

        if($setData) {
            $res = $this->table($this->_fixedTable)->addAll($setData);

            if($res === false) {
                return false;
            }
        }

        //成功copy数据
        return true;
    }

    /**
     * 根据订单ID获取产品ID、购买用户ID、分销商ID、使用日期、特别属性
     *
     * @param  $orderId 订单ID
     *         
     */
    private function _getBaseDate($orderId) {
        if(!$orderId) {
            return false;
        }

        //获取分销商、供应商或是上级分销商、商品ID等信息
        $orderInfo = $this->table($this->_orderTable)->field('begintime,aid,tid,tnum,member')->where(array('ordernum' => $orderId))->find();
        if(!$orderInfo) {
            return false;
        }

        $buyNum      = $orderInfo['tnum'];
        $tid         = $orderInfo['tid'];
        $resellerUid = $orderInfo['member'];
        $setterUid   = $orderInfo['aid'];
     
        $ticketInfo = $this->table($this->_ticketTable)->field('pid, apply_did')->where(array('id' => $tid))->find();
        if(!$ticketInfo) {
            return false;
        }
 
        $pid  = $ticketInfo['pid'];                       //产品ID
        $tmp = explode('-', $orderInfo['begintime']);

        //消耗的是哪一天的库存
        $year  = $tmp[0];
        $month = intval($tmp[1]);
        $day   = intval($tmp[2]);
        
        $month = $month >= 10 ? $month : '0' . $month;
        $day   = $day >= 10 ? $day : '0' . $day;
        $date  = $year . $month . $day;

        //获取上级分销商
        // $attr = false;
        // $detailInfo = $this->table($this->_detailTable)->field('aids,series')->where(array('orderid' => $orderId))->find();
        // if($detailInfo && $detailInfo['series']) {
        //     $series = @unserialize($detailInfo['series']);

        //     //如果数据中存在场次信息
        //     if($series && is_array($series) && isset($series[1]) && $series[1]) {
        //         $attr = $series[1];
        //     }
        // }

        //返回基础信息
        $res = array(
            'pid'         => $pid,
            'setterUid'   => $setterUid,
            'resellerUid' => $resellerUid,
            'date'        => $date,
            'buyNum'      => $buyNum,
            'attr'        => $attr ? $attr : false
        );

        return $res;
    }


    /**
     * 判断分销商的库存是否足够
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param buyNum 需要购买的数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    private function _isStorageEnough($pid, $setterUid, $resellerUid, $date, $buyNum, $attr = false) {
        $leftNums = $this->_getLeftNums($pid, $setterUid, $resellerUid, $date);

        if($buyNum > $leftNums) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取最大分销库存量
     * @author dwer
     * @date   2016-03-08
     *
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param  bool $attr
     * @return
     */
    private function _getLeftNums($pid, $setterUid, $resellerUid, $date, $attr = false) {

        $tmp = $this->getMaxStorage($pid, $setterUid, $resellerUid, $date, $attr);
        $maxStorage = $tmp['max_fixed'] + $tmp['max_dynamic'];

        //获取用户已经使用的库存
        $tmp         = $this->getUsedStorage($pid, $setterUid, $resellerUid, $date, $attr);
        $usedFixed   = $tmp['fixed'];

        //现在可售卖数量
        $leftNums = intval($maxStorage - $usedFixed);

        return $leftNums;
    }

    /**
     * 消耗分销商的可用库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param buyNum 需要购买的数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    private function _useupStorage($orderId, $pid, $setterUid, $resellerUid, $date, $buyNum, $attr = false) {
        //判断库存是否充足
        $tmp = $this->getMaxStorage($pid, $setterUid, $resellerUid, $date, $attr);
        $maxFixed     = $tmp['max_fixed'];
        $maxDynamic   = $tmp['max_dynamic'];

        //获取用户已经使用的库存
        $tmp         = $this->getUsedStorage($pid, $setterUid, $resellerUid, $date, $attr);
        $usedFixed   = $tmp['fixed'];

        //库存量如果不足，就使用掉足的量
        $leftNum = ($maxFixed + $maxDynamic) - $usedFixed;
        if( $leftNum < $buyNum ) {
            $buyNum = $leftNum;
        }

        //记录使用来源表
        $logFixedNum   = 0;
        $logDynamicNum = 0;

        //自己的固定库存是否足够
        if(($maxFixed - $usedFixed) >= $buyNum) {
            //足够的情况
            $res = $this->_useFixed($pid, $setterUid, $resellerUid, $date, $buyNum, $attr);

            //记录使用数量
            $logFixedNum = $buyNum;

            if(!$res) {
                return false;
            }
        } else {
            //不够的情况
            $needNum = $buyNum - ($maxFixed - $usedFixed);

            //需要使用的固定库存
            $useupFixedNum = $maxFixed - $usedFixed;

            if($useupFixedNum > 0) {
                $fixdRes = $this->_useFixed($pid, $setterUid, $resellerUid, $date, $useupFixedNum, $attr);

                if(!$fixdRes) {
                    return false;
                }
            }

            if($needNum > 0) {
                //需要使用的动态库存
                $dynamicRes = $this->_useDynamic($pid, $setterUid, $resellerUid, $date, $needNum, $attr);

                if(!$dynamicRes) {
                    return false;
                }
            }

            //记录使用数量
            $logFixedNum   = $useupFixedNum;
            $logDynamicNum = $needNum;
        }
        
        //记录库存的使用记录
        $logRes = $this->_useLog($orderId, $setterUid, $resellerUid, $logFixedNum, $logDynamicNum);
        if(!$logRes) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 消耗分销商的可用库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param buyNum 需要购买的数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     *   
     */
    private function _useupSelfStorage($orderId, $pid, $setterUid, $resellerUid, $date, $buyNum, $attr = false) {
        //记录使用来源表
        $logFixedNum   = $buyNum;
        $logDynamicNum = 0;

        //足够的情况
        $res = $this->_useFixed($pid, $setterUid, $resellerUid, $date, $buyNum, $attr);
        if(!$res) {
            return false;
        }

        //记录库存的使用记录
        $logRes = $this->_useLog($orderId, $setterUid, $resellerUid, $logFixedNum, $logDynamicNum);
        if(!$logRes) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 根据购买用户、商品、分销商ID获取有设置分销商库存的分销商
     *
     * @param  memberId 购买用户ID
     * @param  pid 商品ID
     * @param  setterId 上级供应商
     * @param  date 购买日期
     * @param  attr 特殊属性 - 比如场次
     * 
     */
    private function _getLastResellers($memberId, $pid, $setterId, $date, $attr = false) {
        //如果是自销的就直接返回
        if($memberId == $setterId) {
            //判断有没有设置分销库存
            $publicInfo = $this->getAvailablePublic($setterId, $pid, $date);
            if(!$publicInfo) {
                return false;
            } else {
                //直接返回
                return array(array('first' => $memberId, 'second' => $setterId));
            }
        }

        //判断是不是直接找供应商购买
        $where = array(
            'fid' => $memberId,
            'aid' => $setterId
        );
        $res = $this->table($this->_salesTable)->field('pids')->where($where)->find();

        if($res) {
            $pids = $res['pids'];
            $tmp = explode(',', $pids);
            if(in_array($pid, $tmp) || $pids == 'A') {
                if($this->_isSetStorage($memberId, $setterId, $pid, $date, $attr)) {

                    //如果一级分销商有设置分销库存，所以自己就只能使用未分配库存或是动态库存
                    $publicInfo = $this->getAvailablePublic($memberId, $pid, $date, $attr);
                    if($publicInfo) {
                        return array(
                            array('first' => $memberId, 'second' => $memberId), //使用未分配库存或是动态库存
                            array('first' => $memberId, 'second' => $setterId), //使用供应商的库存
                        );
                    } else {
                        return array(
                            array('first' => $memberId, 'second' => $setterId),
                            array('first' => $memberId, 'second' => $memberId, 'self_use' => true) //一级分销商还没有给下级分销配置的时候，也要记录自己使用量
                        );
                    }

                } else {
                    return false;
                }
            }
        }

        //二手分销产品判断
        $where = array(
            'fid' => $memberId,
            'sid' => $setterId,
            'pid' => $pid
        );

        $res = $this->table($this->_evoluteTable)->where($where)->find();
        if($res) {
            //购买用户是这个商品的分销商
            $aids = $res['aids'];

            $tmp = explode(',', $aids);
            $sellersList = array_reverse($tmp);
            array_unshift($sellersList, $memberId);

            $resArr = array();

            for($i = 0; $i < (count($sellersList) - 1); $i++) {
                $first = $sellersList[$i];
                $second = $sellersList[$i + 1];

                if($this->_isSetStorage($first, $second, $pid, $date, $attr)) {
                    $resArr[] = array('first' => $first, 'second' => $second);
                }
            }

            //返回数据
            if(count($resArr) == 0) {
                return false;
            } else {
                return $resArr;
            }
                        
        } else {
            return false;
        }
    }

    /**
     * 判断分销商和上级供应商是否有设置库存
     * 
     */
    private function _isSetStorage($resellerUid, $setterUid, $pid, $date, $attr = false) {
        //获取上级供应商的公共配置
        $publicTmp = $this->getSpecialDynamic($pid, $setterUid, $date, $attr);

        if($publicTmp) {
            //如果是一级分销商对二级分销商的配置
            if($publicTmp['level'] == 2) {
                $setNum = intval($publicTmp['set_num']);

                //判断该配置是否生效
                $tmp = $this->isSettingAvailable($pid, $setterUid, $date, $setNum);
                if(!$tmp) {
                    return false;
                }
            }

            //对具体日期有配置库存
            if($publicTmp['mode'] == 2) {
                //如果是动态库存模式
                return true;
            } else {
                //如果是固定库存模式，还要查看是否有设置固定库存
                $fixedInfo = $this->getFixedInfo($pid, $setterUid, $resellerUid, $date, $attr);

                if($fixedInfo) {
                    //有设置固定库存
                    return true;
                } else {
                    //没有设置固定库存
                    return false;
                }
            }
        } else {
            //查找是否有默认配置
            $publicTmp = $this->getCommonDynamic($pid, $setterUid, $attr);

            if(!$publicTmp) {
                //都没有配置，直接返回
                return false;
            }

            //如果是一级分销商对二级分销商的配置
            if($publicTmp['level'] == 2) {
                $setNum = intval($publicTmp['set_num']);

                //判断该配置是否生效
                $tmp = $this->isSettingAvailable($pid, $setterUid, $date, $setNum);

                if(!$tmp) {
                    return false;
                }
            }

            if($publicTmp['mode'] == 2) {
                //如果是动态库存模式
                return true;
            } else {
                //如果是固定库存模式，还要查看是否有设置固定库存
                $fixedInfo = $this->getFixedInfo($pid, $setterUid, $resellerUid, 0, $attr);

                if($fixedInfo) {
                    //有设置固定库存
                    return true;
                } else {
                    //没有设置固定库存
                    return false;
                }
            }
        }
    }

    /**
     *  分销商库存配置是否生效
     *  条件：供应商设置了动态库存模式，一级分销商设置的配置无法生效
     *        如果供应商给一级分销商设置的固定库存不足的话，一级分销商设置的配置无法生效
     *        
     * @author dwer
     * @date   2016-03-02
     *
     * @param  [type] $pid 产品ID
     * @param  [type] $setterId 分销商ID
     * @param  [type] $date 日期
     * @param  $setNum 给下级分销商设置的固定库存总和
     * @return bool
     */
    public function isSettingAvailable($pid, $setterId, $date, $setNum, $attr = false) {
        if(!$pid || !$setterId || !$date) {
            return false;
        }

        //获取直接供应商
        $applyDid = $this->getApplyDid($pid);

        $publicInfo = $this->getAvailablePublic($applyDid, $pid, $date, $attr);
        if(!$publicInfo) {
            return false;
        }

        if($publicInfo['mode'] == 2) {
            return false;
        }

        //获取给分销商设置的日库存是否充足
        $tmpDate = $publicInfo['date'];
        $fixedInfo = $this->getFixedInfo($pid, $applyDid, $setterId, $tmpDate);

        if(!$fixedInfo) {
            return false;
        }

        $fixedNum = $fixedInfo['fixed_num'];

        if($fixedNum >= $setNum) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 恢复分销商的固定库存
     * 
     * 
     */
    private function _recoverFixed($pid, $setterUid, $resellerUid, $date, $fixedNum, $attr) {
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        //恢复固定库存
        $data = array(
            'fixed_num_used'     => array('exp', 'fixed_num_used-' . $fixedNum),
            'update_time'         => time()
        );
        $res = $this->table($this->_usedTable)->where($where)->save($data);

        return $res === false ? false : true;
    }

    /**
     * 恢复分销商的动态库存
     *
     * 
     */
    private function _recoverDynamic($pid, $setterUid, $resellerUid, $date, $dynamicNum, $attr) {
        //恢复分销商的库存
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        //恢复固定库存
        $data = array(
            'dynamic_num_used'         => array('exp', 'dynamic_num_used-' . $dynamicNum),
            'update_time'         => time()
        );
        $res = $this->table($this->_usedTable)->where($where)->save($data);

        if($res === false) {
            return false;
        }

        //恢复动态库存  
        $dynamicWhere = $where;
        unset($dynamicWhere['reseller_uid']);
        $data = array(
            'used_num'         => array('exp', 'used_num - ' . $dynamicNum),
            'update_time'     => time()
        );
        $res = $this->table($this->_dynamicTable)->where($dynamicWhere)->save($data);

        return $res === false ? false : true;
    }

    /**
     * 使用分销商的固定库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param usedNum 使用的固定库存数量
     * @param attr 产品属性，这边可能是场次
     *
     * @return bool
     * 
     */
    private function _useFixed($pid, $setterUid, $resellerUid, $date, $usedNum, $attr = false) {
        //获取使用记录
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        $usedNum = intval($usedNum) > 0 ? intval($usedNum) : 0;

        //获取记录
        $tmp = $this->table($this->_usedTable)->where($where)->find();
        if(!$tmp) {
            //初始化记录
            $data = $where;
            $data['fixed_num_used'] = $usedNum;
            $data['update_time']    = time();

            $res = $this->table($this->_usedTable)->add($data);
        } else {
            //更新记录
            $data = array(
                'fixed_num_used' => array('exp', 'fixed_num_used + ' . $usedNum),
                'update_time'    => time()
            );

            $res = $this->table($this->_usedTable)->where($where)->save($data);
        } 

        return $res === false ? false : true;
    }    


    /**
     * 使用分销商的动态库存
     * 
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param resellerUid 分销商ID
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     * @param usedNum 使用的动态库存数量
     *
     * @return bool
     * 
     */
    private function _useDynamic($pid, $setterUid, $resellerUid, $date, $usedNum, $attr = false) {
        //获取使用记录
        $where = array(
            'pid'          => $pid,
            'setter_uid'   => $setterUid,
            'reseller_uid' => $resellerUid,
            'date'         => $date
        );

        if($attr) {
             $where['special_attr'] = $attr;
        }

        $usedNum = intval($usedNum) > 0 ? intval($usedNum) : 0;

        //获取记录
        $tmp = $this->table($this->_usedTable)->where($where)->find();
        if(!$tmp) {
            //初始化记录
            $data = $where;
            $data['dynamic_num_used'] = $usedNum;
            $data['update_time']      = time();

            $res = $this->table($this->_usedTable)->add($data);
        } else {
            //更新记录
            $data = array(
                'dynamic_num_used' => array('exp', "dynamic_num_used+{$usedNum}") ,
                'update_time'      => time()
            );

            $res = $this->table($this->_usedTable)->where($where)->save($data);
        }

        if($res === false) { 
            return false;
        } else {
            //扣除共享库存
            $dynamicRes = $this->_takeofDynamic($pid, $setterUid, $date, $attr, $usedNum);
            return $dynamicRes;
        }
    }

    /**
     *  扣除共享库存
     * @author dwer
     * @date   2016-02-29
     *
     * @param pid 产品ID
     * @param setterUid 供应商ID或是上级分销商ID
     * @param date 日期 - 20161023
     * @param attr 产品属性，这边可能是场次
     * @param usedNum 使用的动态库存数量
     */
    private function _takeofDynamic($pid, $setterId, $date, $attr, $usedNum) {
        $where = array(
            'pid'        => $pid,
            'setter_uid' => $setterId,
            'date'       => $date
        );

        if($attr) {
            $where['special_attr'] = $attr;
        }

        $tmp = $this->table($this->_dynamicTable)->where($where)->find();
        if($tmp) {
            //更新
            $data = array(
                'used_num'    => $tmp['used_num'] + $usedNum,
                'update_time' => time()
            );
            $dynamicRes = $this->table($this->_dynamicTable)->where($where)->save($data);
        } else {
            $newData = $where;
            $newData['used_num'] = $usedNum;
            $newData['update_time'] = time();

            $dynamicRes = $this->table($this->_dynamicTable)->add($newData);
        }

        return $dynamicRes === false ? false : true;
    }



    /**
     * 记录库存使用量
     *
     * @param orderId 订单ID
     * @param fixedNum 固定库存使用量
     * @param dynamicNum 动态库存使用量
     */
    private function _useLog($orderId, $setterId, $resellerId, $fixedNum, $dynamicNum) {
        $fixedNum = intval($fixedNum);
        $dynamicNum = intval($dynamicNum);

        if($fixedNum < 1 && $dynamicNum < 1) {
            return true;
        }

        $data = array(
            'order_num'   => $orderId,
            'fixed_num'   => $fixedNum,
            'dynamic_num' => $dynamicNum,
            'setter_id'   => $setterId,
            'reseller_id' => $resellerId,
            'update_time' => time()
        );

        $res = $this->table($this->_logTable)->add($data);
        
        return $res === false ? false : true;
    }

    /**
     * 修改库存使用量
     *
     * @param id 记录ID
     * @param fixedNum 固定库存使用量
     * @param dynamicNum 动态库存使用量
     */
    private function _changeLog($id, $fixedNum, $dynamicNum) {
        $fixedNum = intval($fixedNum);
        $dynamicNum = intval($dynamicNum);

        if($fixedNum < 1 && $dynamicNum < 1) {
            return true;
        }

        $where = array('id' => $id);
        $data  = array(
            'fixed_num'   => $fixedNum,
            'dynamic_num' => $dynamicNum,
            'update_time' => time()
        );

        $res = $this->table($this->_logTable)->where($where)->save($data);

        return $res === false ? false : true;
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

