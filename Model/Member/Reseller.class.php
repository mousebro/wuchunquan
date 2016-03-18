<?php
/**
 * 分销商相关模型
 *
 * @author dwer
 * @date 2016-01-20
 * 
 */
namespace Model\Member;
use Library\Model;

class Reseller extends Model{

    private $_relationTable = 'pft_member_relationship';
    private $_memberTable   = 'pft_member';

    /**
     * 获取供应商下面的分销商
     * @author dwer
     * @date   2016-03-15
     *
     * @param  $providerId 供应商ID
     * @return
     */
    public function getResellerList($providerId, $page = 1, $size = 100) {
        if(!$providerId) {
            return array();
        }

        $table = "{$this->_relationTable} as relation";
        $field = 'relation.son_id,  member.dname, member.account';
        $join  = "LEFT JOIN {$this->_memberTable} as member on member.id=relation.son_id";
        $where = "`parent_id` = '{$providerId}' and `son_id_type`=0 and relation.`status`=0 and member.status<3 and member.id>1 and length(member.account)<11";
        $page  = "{$page},{$size}";

        $res = $this->table($table)->field($field)->join($join)->where($where)->page($page)->select();
        //如果分销商里面已经包含了自己，就先将那个数据去除
        foreach($res as $key => $item) {
            if($item['son_id'] == $providerId) { 
                unset($res[$key]);
            }
        }

        //将供应商加入到列表里面去
        $field = "dname, account";
        $where = array('id' => $providerId);
        $memberInfo = $this->table($this->_memberTable)->field($field)->where($where)->find();

        if($memberInfo) {
            $memberInfo['son_id'] = $providerId;
            array_unshift($res, $memberInfo);
        }

        return $res;
    }
}