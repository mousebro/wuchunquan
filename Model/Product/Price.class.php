<?php
/**
 * 价格配置模型
 */

namespace Model\Product;
use Library\Model;
use Model\Product\Ticket;
use Library\Resque\Queue as Queue;

class Price extends Model {

    const __SALE_LIST_TABLE__       = 'pft_product_sale_list';    //一手供应商产品表
    const __EVOLUTE_TABLE__         = 'pft_p_apply_evolute';    //转分销产品表

    const __PRICESET_TABLE__        = 'uu_priceset';   //产品价格表
    const __PRICE_CHG_NOTIFY_TABLE__= 'pft_price_change_notify';
    const __PRICE_GROUP__TABLE__    = 'pft_price_group';

    const __MEMBER_RELATIONSHIP__   = 'pft_member_relationship';

    const __MAX_PRICCE__            = 20000;

    protected $diff_mode            = false;  //当前传的价格是否是差价模式

    protected $soap_cli             = null;     //soap接口实例

    protected $notify_data          = array();  //权限变更待通知数据

    protected $commit_flag          = false;    //本次配置是否成功标识

    protected $price_diff           = array();  //本次价格变动集合


    
    /**
     * @param int    供应商ID
     * @param string 供应商账号
     * @param int    productID
     * @param array  价格配置, array('分销商ID' => 180, '分销商ID' => 150)
     * @param int    产品的上级供应商ID,自供应为0
     */
    public function setOneProductPrice($sid, $saccount, $pid, array $priceset, $aid = 0) {
        $this->startTrans();    //开启事务
        $result = $this->setPriceForOneProduct($sid, $saccount, $pid, $priceset, $aid);
        if ($result) {
            $this->commit();
            $this->commit_flag = true;
            return true;
        } else {
            $this->rollback();
            return false;
        }
    }

    /**
     * 同时为多个分销商配置多个自供应产品的价格
     * @param int    供应商ID
     * @param string 供应商账号
     * @param array  价格配置, array('productID' => 150, 'productID' => 100)
     * @param array  分销商ID, array(81, 83, 3385, 6970)
     */
    public function setPriceForSelfByMulti($sid, $saccount, array $priceset, array $did_arr) {
        if (count($priceset) == 0 || count($did_arr) == 0) {
            return true;
        }

        $pid_arr = array_keys($priceset);

        $this->startTrans();    //开启事务
        foreach ($pid_arr as $pid) {
            $todo_priceset = array();
            foreach ($did_arr as $did) {
                $todo_priceset[$did] = $priceset[$pid];
            }
            $result = self::setPriceForOneProduct($sid, $saccount, $pid, $todo_priceset, 0);
            $this->recordLog($sid, $result);
            if (!$result) {
                $this->rollback();
                return false;
            }
        }
        $this->commit();
        $this->commit_flag = true;
        return true;
    }

    /**
     * 同时为多个分销商配置多个转分销产品的价格
     * @param int    供应商ID
     * @param string 供应商账号
     * @param array  价格配置, array('productID_上级供应商ID' => 150, '14624_3385' => 100)
     * @param array  分销商ID, array(81, 83, 3385, 6970)
     */
    public function setPriceForDisByMulti($sid, $saccount, array $priceset, array $did_arr) {
        if (count($priceset) == 0 || count($did_arr) == 0) {
            return true;
        }

        $this->startTrans();    //开启事务
        foreach ($priceset as $map => $price) {
            $todo_priceset = array();
            list($pid, $aid) = explode('_', $map);
            foreach ($did_arr as $did) {
                $todo_priceset[$did] = $price;
            }
            $result = self::setPriceForOneProduct($sid, $saccount, $pid, $todo_priceset, $aid);
            if (!$result) {
                $this->rollback();
                return false;
            }
        }
        $this->commit();
        $this->commit_flag = true;
        return true;
    }

    /**
     * @param int    供应商ID
     * @param string 供应商账号
     * @param int    productID
     * @param array  价格配置, array('分销商ID' => 180, '分销商ID' => 150)
     * @param int    产品的上级供应商ID,自供应为0
     */
    protected function setPriceForOneProduct($sid, $saccount, $pid, array $priceset, $aid = 0) {
        if (count($priceset) == 0) {
            return true;
        }

        //获取当前登录用户的结算价
        if (!$this->diff_mode) {
            $self_price = $this->getSettlePrice($saccount, $pid, $aid);
            if ($self_price === false) {
                return true;   //无分销权限
            }

            $this->recordLog($sid, 'price:' . $self_price);
        }
        

        //待配置的会员id数组
        $did_arr = array_keys($priceset);   
        $did_arr = $this->memberFilter($sid, $did_arr);

        //获取当前的差价
        $cur_priceset = $this->table(self::__PRICESET_TABLE__)
            ->where(array(
                'aid' => $aid ? $sid : 0,
                'tid' => $pid, 
                'pid' => array('in', implode(',', $did_arr))))
            ->field('dprice,pid')
            ->select();

        $tmp_priceset = array();
        foreach ($cur_priceset as $item) {
            $tmp_priceset[$item['pid']] = $item['dprice'];
        }

        $todo_insert = $todo_update = array();
        foreach ($priceset as $did => $price) {
            if ($did == $sid || $did == $aid || $price === '' || $price > self::__MAX_PRICCE__ || $price < 0) {
                continue;
            }
            //要设置的差价
            if ($this->diff_mode) {
                $diff_price = $price * 100;
            } else {
                $diff_price = ($price - $self_price) * 100;
            }
            if ($diff_price < 0) {
                unset($did_arr[array_search($did, $did_arr)]);
                unset($priceset[$did]);
                continue;
            }

            //价格记录，存在则更新，不存在则插入
            if (isset($tmp_priceset[$did])) {
                if ((string)$tmp_priceset[$did] != (string)$diff_price) {
                    //本次价格变动的差价
                    $this->price_diff[$did.'_'.$pid] = $diff_price - $tmp_priceset[$did];

                    $todo_update[(string)$diff_price][] = $did;
                }
            } else {
                $todo_insert[$did] = $diff_price; 
            }
        }

        // $this->startTrans();    //开启事务

        //差价保存
        $set_price_result = $this->setPriceAction($sid, $pid, $aid, $todo_insert, $todo_update);

        if (!$set_price_result) {
            // $this->rollback();
            return false;
        }
        //分销权限设置
        $permission_action = $aid ? 'setPermissionForEvolute' : 'setPermissionForSupply';
        $permission_res = $this->$permission_action($sid, $pid, $did_arr, $aid, $priceset);

        return (bool)$permission_res;
    }

    /**
     * 获取结算价
     * @param  string 供应商账号
     * @param  int    productID
     * @param  int    上级供应商ID
     * @param  string 日期, '2016-03-36'
     * @return [type]
     */
    protected function getSettlePrice($saccount, $pid, $aid = 0, $date = null) {
        if (!class_exists('PFTCoreAPI')) {
            include '/var/www/html/new/d/class/abc/PFTCoreAPI.class.php';
        }

        $date = $date ? $date : date('Y-m-d');
        $result = \PFTCoreAPI::pStorage($this->soap_cli, $saccount, $pid, $aid, $date, 0);
        return $result['js']['p'] == -1 ? false : $result['js']['p'];

    }

    /**
     * 分销商过滤
     * @param  [type] $sid     [description]
     * @param  [type] $did_arr [description]
     * @return [type]          [description]
     */
    protected function memberFilter($sid, $did_arr) {
        static $son_id_arr = [];

        if (!$son_id_arr) {
            $son_info = $this->table(self::__MEMBER_RELATIONSHIP__)
                ->where(['parent_id' => $sid, 'status' => 0])
                ->field('son_id')
                ->select();
            $son_id_arr = [];
            foreach ($son_info as $son) {
                $son_id_arr[] = $son['son_id'];
            }
        } 
        
        return array_intersect($did_arr, $son_id_arr);
    } 

    /**
     * 真正执行价格配置的动作
     * @param [type] $sid         [description]
     * @param [type] $pid         [description]
     * @param [type] $aid         [description]
     * @param array  $todo_insert [description]
     * @param array  $todo_update [description]
     */
    protected function setPriceAction($sid, $pid, $aid, $todo_insert = array(), $todo_update = array()) {
        $real_aid = $aid ? $sid : 0;
        $insert_data = array();
        if (count($todo_insert)) {
            foreach ($todo_insert as $did => $diff_price) {
                $insert_data[] = array(
                    'tid'       => $pid,
                    'pid'       => $did,
                    'aid'       => $real_aid,
                    'dprice'    => $diff_price,
                    'status'    => 0 
                );
            }
        }
        // var_dump($todo_update, $insert_data);die;
        if ($insert_data) {
            $last_insert_id = $this->table(self::__PRICESET_TABLE__)->addAll($insert_data);
            $this->recordLog($sid, $last_insert_id . $this->_sql());
            if (!$last_insert_id) return false;
        }

        foreach ($todo_update as $diff_price => $update_did_arr) {
            $affect_rows = $this->table(self::__PRICESET_TABLE__)
                ->where(array(
                    'pid' => array('in', implode(',', $update_did_arr)),
                    'tid' => $pid,
                    'aid' => $real_aid))
                ->save(array('dprice' => $diff_price));
            $this->recordLog($sid, $affect_rows . $this->_sql());

            if ($affect_rows === false) return false;

            //价格变动通知
            foreach ($update_did_arr as $did) {
                $price_diff = isset($this->price_diff[$did.'_'.$pid]) ? $this->price_diff[$did.'_'.$pid] : 0;
                $this->_priceChangeNotify($sid, $did, $pid, $price_diff);    
            }
        }

        return true;
    }

    /**
     * 自供应产品权限设置
     * @param [type] $sid      [description]
     * @param [type] $pid      [description]
     * @param [type] $did_arr  [description]
     * @param [type] $aid      [description]
     * @param [type] $priceset [description]
     */
    protected function setPermissionForSupply($sid, $pid, $did_arr, $aid, $priceset) {
        $sale_list = $this->table(self::__SALE_LIST_TABLE__)
            ->where(array(
                'aid' => $sid,
                'fid' => array('in', implode(',', $did_arr))))
            ->select();

        $tmp_sale_list = array();
        if ($sale_list) {
            foreach ($sale_list as $item) {
                $tmp_sale_list[$item['fid']] = $item;
            }
        }
        $todo_open = array();
        foreach ($tmp_sale_list as $did => $item) {
            $pid_arr = explode(',', $item['pids']);
            //价格为空，则去除分销权限
            if (($priceset[$did] == '' && $priceset[$did] !== 0)) {
                if ((count($pid_arr) == 1 && $pid_arr[0] == '') || $pid_arr[0] == 'A') {
                    if ($pid_arr[0] == 'A') {
                        $this->deletePriceset($sid, $did, $pid);
                    }
                    continue;
                }
                if (!in_array($pid, $pid_arr)) continue;
                $new_pid_arr = array_diff($pid_arr, array($pid));
                //循环关闭名下的各级分销权限
                if (!$this->deletePermission($pid, $did, "$sid,$did", $sid)) {
                    return false;
                }
                $this->_permissionChangeNotify($sid, $did, $pid, 'delete');
                // continue;
            } else {
                if ($pid_arr[0] == 'A' || in_array($pid, $pid_arr)) {
                    continue;
                }
                $new_pid_arr = array_merge($pid_arr, array($pid));

                $this->_permissionChangeNotify($sid, $did, $pid, 'add');
            }

            $new_pid_arr = array_unique($new_pid_arr);
            $affect_rows = $this->table(self::__SALE_LIST_TABLE__)
                ->save(array('id' => $item['id'], 'pids' => implode(',', $new_pid_arr)));
            $this->recordLog($sid, $affect_rows . $this->_sql());
            if ($affect_rows === false) return false;

            //变更转分销状态
            
            $open_id = $this->table(self::__EVOLUTE_TABLE__)
                ->where(array('sid' => $sid, 'fid' => $did, 'pid' => $pid, 'active' => 1))
                ->getField('id');
            if ($open_id) $todo_open[] = $open_id;
        }

        if ($todo_open) {
            $affect_rows = $this->table(self::__EVOLUTE_TABLE__)
                ->where(array('id' => array('in', implode(',', $todo_open))))
                ->save(array('active' => 0));
            $this->recordLog($sid, $affect_rows . $this->_sql());
            if (!$affect_rows) return false;
        }

        return true;
    }

    /**
     * 转分销产品 权限设置
     * @param [type] $sid      [description]
     * @param [type] $pid      [description]
     * @param [type] $did_arr  [description]
     * @param [type] $aid      [description]
     * @param [type] $priceset [description]
     */
    protected function setPermissionForEvolute($sid, $pid, $did_arr, $aid, $priceset) {
        //获取当前的分销链信息
        $evolute = $this->table(self::__EVOLUTE_TABLE__)->where(array('sid' => $aid, 'fid' => $sid, 'pid' => $pid, 'active' => 1))->find();
        if (!$evolute) return false;

        $chain_cur = explode(',', $evolute['aids']);
        $chain_todo = $evolute['aids'] . ',' . $sid;
        $lvl_todo = count($chain_cur) + 1;

        //获取链上已存在的信息
        $chains_info = $this->table(self::__EVOLUTE_TABLE__)
            ->where(array('sid' => $sid, 'fid' => array('in', implode(',', $did_arr)), 'pid' => $pid))
            ->select();

        $tmp_chain_info = array();
        foreach ($chains_info as $chain) {
            $tmp_chain_info[$chain['fid']] = $chain;
        }

        $todo_update = $todo_insert = $todo_delete = array();
        foreach ($did_arr as $did) {
            if ($did == $sid || in_array($did, $chain_cur)) {
                continue;
            }
            $add_permission = false;    //是否新增分销权限
            //存在则更新，不存在则插入
            if (isset($tmp_chain_info[$did])) {
                //价格为空，去除分销权限
                if ($priceset[$did] == '' && $priceset[$did] !== 0) {
                    if ($tmp_chain_info[$did]['status'] != 1 || $tmp_chain_info[$did]['active'] != 0) {
                        $todo_delete[] = $tmp_chain_info[$did]['id'];
                        $this->_permissionChangeNotify($sid, $did, $pid, 'delete');
                    }
                    $this->deletePermission($pid, $did, $tmp_chain_info[$did]['aids'].','.$did, $sid);
                    continue;
                }

                if ($tmp_chain_info[$did]['status'] == 0) continue;

                $add_permission = true;
                $todo_update[] = $tmp_chain_info[$did]['id'];
            } else {
                if ($priceset[$did] == '' && $priceset[$did] !== 0) {
                    continue;
                }
                $add_permission = true;
                $todo_insert[] = $did;
            }

            if ($add_permission) {
                $this->_permissionChangeNotify($sid, $did, $pid, 'add');
            }
        }

        if ($todo_update) {
            $affect_rows = $this->table(self::__EVOLUTE_TABLE__)
                ->where(array('id' => array('in', implode(',', $todo_update))))
                ->save(array(
                    'aids' => $chain_todo,
                    'lvl' => $lvl_todo,
                    'status' => 0));
            $this->recordLog($sid, $affect_rows . $this->_sql());
            if (!$affect_rows) return false;
        }
        
        if ($todo_insert) {
            $insert_data = array();
            foreach ($todo_insert as $item) {
                $insert_data[] = array(
                    'sid'       => $sid,
                    'fid'       => $item,
                    'sourceid'  => $evolute['sourceid'],
                    'pid'       => $pid, 
                    'lvl'       => $lvl_todo,
                    'aids'      => $chain_todo,
                    'rectime'   => date('Y-m-d H:i:s'),
                    'status'    => 0,
                    'active'    => 0
                );
            }

            if ($insert_data) {
                $last_insert_id = $this->table(self::__EVOLUTE_TABLE__)->addAll($insert_data);
                $this->recordLog($sid, $last_insert_id . $this->_sql());
                if (!$last_insert_id) return false;
            }
        }

        if ($todo_delete) {
            $delete_id = $this->table(self::__EVOLUTE_TABLE__)
                ->where(array('id' => array('in', implode(',', $todo_delete))))
                ->save(array('status' => 1, 'active' => 0));
            $this->recordLog($sid, $delete_id . $this->_sql());
            if (!$delete_id) return false;
        }

        return true;
    }

    /**
     * 删除价格表的记录
     * @param  [type] $sid [description]
     * @param  [type] $aid [description]
     * @param  [type] $did [description]
     * @param  [type] $pid [description]
     * @return [type]      [description]
     */
    public function deletePriceset($sid, $did, $pid) {
        $real_aid = $aid ? $sid : 0;
        $where = [
            'tid' => $pid,
            'pid' => $did,
            'aid' => 0
        ];
        $result = $this->table(self::__PRICESET_TABLE__)->where($where)->delete();
        $this->recordLog($sid,  $result . $this->_sql());
    }

    /**
     * 删除分销权限
     * @param  [type] $pid  [description]
     * @param  [type] $did  [description]
     * @param  [type] $aids [description]
     * @param  [type] $sid  [description]
     * @return [type]       [description]
     */
    public function deletePermission($pid, $did, $aids, $sid) {

        //取消分销权限通知
        $this->_permissionDeleteNotify($did, $pid, $sid);
        // var_dump($did);

        $tree = $this->table(self::__EVOLUTE_TABLE__)
            ->where(array(
                '_string'   => "aids='{$aids}' or aids like '{$aids},%'",
                'pid'       => $pid, 
                'status'    => 0))
                // '_string'   => 'sid=substring(aids, -length(sid))', 
                // 'aids'      => array('like', $aids . '%')))
            ->field('id,fid,sid')
            ->select();

        $todo_update = array();
        foreach ($tree as  $item) {
            $this->_permissionChangeNotify($item['sid'], $item['fid'], $pid, 'delete');
            $todo_update[] = $item['id'];
        }
        if ($todo_update) {
            $affect_rows = $this->table(self::__EVOLUTE_TABLE__)
                ->where(array('id' => array('in', implode(',', $todo_update))))
                ->save(array('status' => 1, 'active' => 0));
            $this->recordLog($sid, $affect_rows . $this->_sql());
            if (!$affect_rows) return false;
        }
        return true;
    }

    /**
     * 获取某个用户的产品上下架通知
     * @param  [type] $memberid [description]
     * @return [type]           [description]
     */
    public function getPermissionChange($memberid, $type = 'in', $options = array()) {
        $options = $this->_combineNotifyOptions($options);
        extract($options);

        $notify_type = $type == 'in' ? 2 : 1;
        $where = array(
            'mid'           => $memberid,
            'notify_type'   => $notify_type,
            'create_time'   => array('between', array($begin_time, $end_time)),
        );
        $result = $this->table(self::__PRICE_CHG_NOTIFY_TABLE__)
            ->where($where)
            ->field('aid,mid,pid,create_time')
            ->limit($limit)
            ->select();

        $return = array();
        foreach ($result as $key => $item) {
            $return[$item['pid']] = $item;
        }
        return $return;
    }

    /**
     * 获取价格变动的记录
     * @param  [type] $memberid [description]
     * @param  array  $options  [description]
     * @return [type]           [description]
     */
    public function getPriceChange($memberid, $options = array()) {
        $options = $this->_combineNotifyOptions($options);
        extract($options);

        $sale_list = $this->table(self::__SALE_LIST_TABLE__)->where(['fid' => $memberid, 'status' => 0])->select();

        $sale_pid_arr = array();
        foreach ($sale_list as $item) {
            $sale_pid_arr = array_merge($sale_pid_arr, explode(',', $item['pids']));
        }

        $TicketModel = new Ticket();
        $products = $TicketModel->getSaleDisProducts(
            $memberid, 
            array('active' => array('in', '0,1')), 
            array('field' => 'p.id')
        );
        $dis_pid_arr = array();
        foreach ($products as $product) {
            $dis_pid_arr[] = $product['id'];
        }
        //监听变化的产品集合
        $pid_arr = array_unique(array_merge($dis_pid_arr, $sale_pid_arr));

        $where = array(
            'pid' => array('in', implode(',', $pid_arr)),
            'notify_type' => 0,
            'create_time' => array('between', array($begin_time, $end_time)),
        );

        //可能发生改变的产品结合
        $possible_change = $this->table(self::__PRICE_CHG_NOTIFY_TABLE__)
            ->where($where)
            ->field('mid,aid,pid,create_time,price_diff')
            ->select(); 

        $result = $next_find = array();
        if ($possible_change) {
            foreach ($possible_change as $item) {
                if ($item['mid'] == $memberid) {
                    //因为上级供应商配置价格直接引起的变化
                    $result[] = $item;
                } else {
                    $next_find[] = $item;
                }
            }
        }

        if ($next_find) {
            $goto = array();
            foreach ($next_find as $item) {
                $where = array(
                    'fid'       => $memberid,
                    'pid'       => $item['pid'],
                    '_string'   => 'find_in_set('.$item['mid'].', aids)',
                    'status'    => 0
                );
                //因为上游(不是直接上下级)的供应商更改价格引起的价格变化
                $find = $this->table(self::__EVOLUTE_TABLE__)->where($where)->find();
                if ($find && !in_array($find['sid'] . $item['create_time'], $goto)) {
                    $item['aid'] = $find['sid'];
                    $item['mid'] = $find['fid'];
                    $result[]    = $item;
                    $goto[]      = $find['sid'] . $item['create_time'];
                }
            }
        }

        $iterator = new \ArrayIterator($result);
        $limits = new \LimitIterator($iterator, ($page - 1) * $page_size, $page_size);

        $result = array();
        foreach ($limits as $item) {
            $result[$item['pid']] = $item;
        }

        return $result;
    }

    private function _combineNotifyOptions($options) {
        $begin_time = isset($options['begin_time']) ? $options['begin_time'] : strtotime(date('Y-m-d'));
        return array(
            'begin_time' => $begin_time,
            'end_time'   => isset($options['end_time']) ? $options['end_time'] : $begin_time + 3600 * 24,
            'page'       => isset($options['page']) ? $options['page'] : 1,
            'page_size'  => isset($options['page_size']) ? $options['page_size'] : 20
        );
    }

    /**
     * 取消分销权限通知(一级)
     * @param  int $did 分销商id
     * @param  int $pid 产品id
     * @param  int $sid 供应商id
     * @return [type]      [description]
     */
    private function _permissionDeleteNotify($did, $pid, $sid) {
        $storageModel = new \Model\Product\YXStorage();
        $storageModel->removeReseller($sid, $did, $pid);

        //加载分销库存模型
        $storageModel = new \Model\Product\SellerStorage();
        $storageModel->removeReseller($did, $pid, $sid);
        return true;
        // $params = array(
        //     'did' => $did,
        //     'pid' => $pid,
        //     'sid' => $sid
        // );
        // $queue = new Queue();
        // $aa = $queue->push('default', 'SalePermission_Job', $params);
    }

    /**
     * 去除/新增分销权限通知(多级)
     * @param  [type] $sid [description]
     * @param  [type] $did [description]
     * @param  [type] $pid [description]
     * @return [type]      [description]
     */
    private function _permissionChangeNotify($sid, $did, $pid, $type = 'add') {
        $notify_type = $type == 'add' ? 2 : 1;
        $notify = array(
            'aid'           => $sid,
            'mid'           => $did,
            'pid'           => $pid,
            'price_diff'    => 0,
            'notify_type'   => $notify_type,
            'create_time'   => time()
        );
        $this->notify_data[] = $notify;
    }

    /**
     * 价格变动通知
     * @param  [type] $sid [description]
     * @param  [type] $did [description]
     * @param  [type] $pid [description]
     * @return [type]      [description]
     */
    private function _priceChangeNotify($sid, $did, $pid, $price_diff) {
        $notify = array(
            'aid'           => $sid,
            'mid'           => $did,
            'pid'           => $pid,
            'price_diff'    => $price_diff,
            'notify_type'   => 0,
            'create_time'   => time()
        );
        $this->notify_data[] = $notify;
    }

    public function setSoapClient(\SoapClient $soap) {
        $this->soap_cli = $soap;
    }

    public function setPriceMode($mode) {
        $this->diff_mode = $mode;
    }


    public function recordLog($sid, $txt,$file='') {
        $dir = BASE_LOG_DIR . '/price/priceset/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        if($file=='') $file = $dir . $sid . date('_Y-m-d'). '.txt';
        $fp = fopen($file,"a");
        flock($fp, LOCK_EX);
        fwrite($fp,date("Y-m-d H:i:s").":".$txt."\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function __destruct() {
        if ($this->commit_flag && $this->notify_data) {
            $this->table(self::__PRICE_CHG_NOTIFY_TABLE__)->addAll($this->notify_data);
        }
    }

    
}
