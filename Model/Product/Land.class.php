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
use Library\Tools\Helpers;
use pft\Member;

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
        $memParams = array(
            'dname'=>$params['title'],
            'dtype'=>2,//直接供应方
            'creattime'=> date('Y-m-d H:i:s'),
            'password' => md5(md5('pft_'.mt_rand(10000,999999)))//md5(md5('uu654321'))
        );
        $db = Helpers::getPrevDb();
        Helpers::loadPrevClass('MemberAccount');
        $mem = new Member\MemberAccount($db);
        //生成直接供应商
        $reg_res = $mem->register($memParams, array());

        if($reg_res['status']=='ok') {
            $res_account = explode('|', $reg_res['body']);
            $params['salerid'] = $res_account[1];
            if(strlen($params['salerid'])>6 || $params['salerid']>=self::MAX_SALER_ID) {
                return array('errcode'=>1001,'msg'=>'添加失败，商户ID超出长度！');
            }
            //建立直接供应商与供应商关系,2014年12月11日16:12:46更新，之前的数据都是错误的，son_id存成了account！！！
            $rel_res = $mem->createRelationship($params['apply_did'],
                $res_account[0],1,1);
            //TODO:建立直接供应方与直接供应方平级关系
            if($parent_id>0) {
                $res_res_p = $mem->createRelationship($parent_id,
                    $res_account[0],1,2);
            }
            if($reg_res['status']!='ok'){
                return $rel_res;
            }
        }

        return $this->table('uu_land')->data($params)->add();
    }

    public function UpdateAttrbites(Array $where, Array $attrs)
    {
        return $this->table('uu_land')->where($where)->save($attrs);
    }
}