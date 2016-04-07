<?php
/**
 * 价格配置模型
 */

namespace Model\Product;
use Library\Model;

class Price extends Model {

    const __SALE_LIST_TABLE__   = 'pft_product_sale_list';    //一手供应商产品表
    const __EVOLUTE_TABLE__     = 'pft_p_apply_evolute';    //转分销产品表

    const __PRICESET_TABLE__    = 'uu_priceset';   //产品价格表

    protected $soap_cli = null; //soap接口实例

    
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
            if (!$result) {
                $this->rollback();
                return false;
            }
        }
        $this->commit();
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
        $self_price = $this->getSettlePrice($saccount, $pid, $aid);
        if ($self_price == false) {
            // var_dump($pid);die;
            return true;   //无分销权限
        }

        //带配置的会员id数组
        $did_arr = array_keys($priceset);   

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
            if ($did == $sid || $did == $aid || $price === '' || $price >= 10000 || $price < 0) {
                continue;
            }
            //要设置的差价
            $diff_price = ($price - $self_price) * 100;
            if ($diff_price < 0) {
                unset($did_arr[array_search($did, $did_arr)]);
                unset($priceset[$did]);
                continue;
            }

            //价格记录，存在则更新，不存在则插入
            if (isset($tmp_priceset[$did])) {
                if ((string)$tmp_priceset[$did] != (string)$diff_price) {
                    $todo_update[(string)$diff_price][] = $did;
                }
            } else {
                $todo_insert[$did] = $diff_price; 
            }
        }
        // var_dump($todo_insert, $todo_update);die;
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

        $result = \PFTCoreAPI::pStorage($this->soap_cli, $saccount, $pid, $aid, $date, 0);
        return $result['js']['p'] == -1 ? false : $result['js']['p'];

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
                    'tid' => $pid,
                    'pid' => $did,
                    'aid' => $real_aid,
                    'dprice' => $diff_price,
                    'status' => 0 
                );
            }
        }
        // var_dump($todo_update, $insert_data);
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

            if (!$affect_rows) return false;
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
                if ($pid_arr[0] == '' || $pid_arr[0] == 'A') {
                    continue;
                }
                if (!in_array($pid, $pid_arr)) continue;
                $new_pid_arr = array_diff($pid_arr, array($pid));
                //循环关闭名下的各级分销权限
                if (!$this->deletePermission($pid, $did, "$sid,$did", $sid)) {
                    return false;
                }
                // continue;
            } else {
                if ($pid_arr[0] == 'A' || in_array($pid, $pid_arr)) {
                    continue;
                }
                $new_pid_arr = array_merge($pid_arr, array($pid));
            }

            $new_pid_arr = array_unique($new_pid_arr);
            $affect_rows = $this->table(self::__SALE_LIST_TABLE__)
                                ->save(array('id' => $item['id'], 'pids' => implode(',', $new_pid_arr)));
            $this->recordLog($sid, $affect_rows . $this->_sql());
            if (!$affect_rows) return false;

            //变更转分销状态
            
            $open_id = $this->table(self::__EVOLUTE_TABLE__)
                            ->where(array('sid' => $sid, 'fid' => $did, 'pid' => $pid, 'active' => 1))->getField('id');
            if ($open_id) $todo_open[] = $open_id;
        }

        if ($todo_open) {
            $affect_rows = $this->table(self::__EVOLUTE_TABLE__)
                                ->where(array('id' => array('in', implode($todo_open))))
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
            //存在则更新，不存在则插入
            if (isset($tmp_chain_info[$did])) {
                //价格为空，去除分销权限
                if ($priceset[$did] == '' && $priceset[$did] !== 0) {
                    if ($tmp_chain_info[$did]['status'] != 1 || $tmp_chain_info[$did]['active'] != 0) {
                        $todo_delete[] = $tmp_chain_info[$did]['id'];
                    }
                    $this->deletePermission($pid, $did, $tmp_chain_info[$did]['aids'].','.$did, $sid);
                    continue;
                }

                if ($tmp_chain_info[$did]['status'] == 0) continue;

                $todo_update[] = $tmp_chain_info[$did]['id'];
            } else {
                if ($priceset[$did] == '' && $priceset[$did] !== 0) {
                    continue;
                }
                $todo_insert[] = $did;
            }
        }
        // var_dump($todo_insert, $todo_update, $todo_delete);die;

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
     * 删除分销权限
     * @param  [type] $pid  [description]
     * @param  [type] $did  [description]
     * @param  [type] $aids [description]
     * @param  [type] $sid  [description]
     * @return [type]       [description]
     */
    public function deletePermission($pid, $did, $aids, $sid) {
        $tree = $this->table(self::__EVOLUTE_TABLE__)
                    ->where(array('pid' => $pid, 'status' => 0, 'sid' => array('exp', '=substring(aids, -length(sid))'), 'aids' => array('like', $aids . '%')))
                    ->field('id')
                    ->select();
        // var_dump($this->_sql());
        $todo_update = array();
        foreach ($tree as  $item) {
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

    public function setSoapClient(\SoapClient $soap) {
        $this->soap_cli = $soap;
    }


    public function recordLog($sid, $txt,$file=''){
        // echo $txt.'<br/>';
        $dir = BASE_LOG_DIR . '/price/priceset/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        if($file=='') $file = $dir . $sid . date('_Y-m-d'). '.txt';
        $fp = fopen($file,"a");
        flock($fp, LOCK_EX);
        fwrite($fp,date("Y-m-d H:i:s").":".$txt."\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    
}
