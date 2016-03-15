<?php
/**
 * 分销商相关模型
 *
 * @author dwer
 * @time 2016-01-20 18:45
 */
namespace Model\Product;
use Library\Model;

class Seller extends Model{


    /**
     * 获取供应商下面的分销商
     * @author dwer
     * @date   2016-03-15
     *
     * @param  $providerId 供应商ID
     * @return
     */
    public function getResullerList($providerId) {
        if(!$providerId) {
            return array();
        }

        $getSql = "SELECT  relation.son_id,  member.dname, member.account  FROM `{$this->_relationTable}` relation left join `{$this->_memberTable}` as member on relation.son_id=member.id  WHERE `parent_id` = ? and `son_id_type`=? and relation.`status`=? and member.status<3 and member.id>1 and length(member.account)<11 limit 0,100 ";
        $data   = array($providerId, 0, 0);

        $stmt = $this->_db->prepare($getSql);
        $stmt->execute($data);
        $res  = $stmt->fetchAll(PDO::FETCH_ASSOC);

        //如果分销商里面已经包含了自己，就先将那个数据去除
        foreach($res as $key => $item) {
            if($item['son_id'] == $providerId) { 
                unset($res[$key]);
            }
        }

        //将供应商加入到列表里面去
        $memberSql  = "SELECT  `dname`, `account`  FROM `{$this->_memberTable}` WHERE `id` = ?;";
        $data       = array($providerId);
        $stmt       = $this->_db->prepare($memberSql);
        $stmt->execute($data);
        $memberInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if($memberInfo) {
            $memberInfo['son_id'] = $providerId;
            array_unshift($res, $memberInfo);
        }

        return $res;
    }
}