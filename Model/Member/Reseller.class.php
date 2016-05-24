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
    private $_evoluteTable  = 'pft_p_apply_evolute';
    private $_ticketTable   = 'uu_jq_ticket';

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

    /**
     * 获取购买商品的一级分销商
     * @author dwer
     * @date   2016-05-17
     *
     * @param  $memberId 购买用户ID
     * @param  $aid 上级供应商ID
     * @param  $pid 产品ID
     * @return
     */
    public function getTopResellerId($memberId, $aid, $pid) {
        if(!$memberId || !$aid || !$pid) {
            return false;
        }

        if($memberId == $aid) {
            return $memberId;
        }

        //获取产品直接供应商
        $tmp = $this->table($this->_ticketTable)->where(array('pid' => $pid))->field('apply_did')->find();
        if(!$tmp) {
            return false;
        }

        //判断购买用户是不是一级分销商
        $applyDid = $tmp['apply_did'];

        if($applyDid == $aid) {
            return $memberId;
        }

        //判断是不是转分销
        $where = array(
            'fid'    => $memberId,
            'sid'    => $aid,
            'pid'    => $pid,
            'status' => 0
        );

        $tmp = $this->table($this->_evoluteTable)->field('aids')->where($where)->find();
        if($tmp) {
            //获取转分销中的一级分销商
            $aids     = $tmp['aids'];
            $arr_aids = explode(',',$aids);

            $oneLevelSeller = isset($arr_aids[1]) ? $arr_aids[1] : $memberId;
            return $oneLevelSeller;
        } else {
            return $memberId;
        }
    }
}