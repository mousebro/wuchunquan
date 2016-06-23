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
    //TODO::SalerID绝对不能>=这个数字。否则整个系统会崩溃@2015年7月27日17:48:01
    const MAX_SALER_ID = 987654;
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

    /**
     * 保存产品基础数据
     *
     * @param array $params
     * @return array
     */
    public function AddProduct(Array $params)
    {
        $params['terminal']      = self::getTerminalId();
        $params['terminal_type'] = 1;
        $memParams = array(
            'dname'     => $params['title'],
            'dtype'     => 2,//直接供应方
            'creattime' => date('Y-m-d H:i:s'),
            'password'  => md5(md5('pft@'.mt_rand(10000,999999)))//随机密码
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
                return array('code'=>0,'msg'=>'添加失败，商户ID超出长度！');
            }
            //建立直接供应商与供应商关系,2014年12月11日16:12:46更新，之前的数据都是错误的，son_id存成了account！！！
            $rel_res = $mem->createRelationship($params['apply_did'], $res_account[0],1,1);
            //TODO:建立直接供应方与直接供应方平级关系
            if($reg_res['status']!='ok'){
                return ['code'=>0, 'msg'=>$reg_res['msg']];
            }
        }
        $res = $this->table('uu_land')->data($params)->add();
        if (is_numeric($res) && $res>0) return ['code'=>200, 'data'=>['lastid'=>$res]];
        return ['code'=>0, 'msg'=>'添加失败，服务器发生错误'];
    }

    /**
     * 更新景区信息
     * @param  int $apply_did 供应商id
     * @param  int $lid       景区id
     * @param  array $params  更新数据
     * @return [type]         [description]
     */
    public function updateProduct($apply_did, $lid, $params) {
        $where = [
            'id'        => $lid,
            'apply_did' => $apply_did
        ];

        $result = $this->table('uu_land')->where($where)->save($params);

        return $result !== false ? true : false;
    }

    public function UpdateAttrbites(Array $where, Array $attrs)
    {
        return $this->table('uu_land')->where($where)->save($attrs);
    }
}