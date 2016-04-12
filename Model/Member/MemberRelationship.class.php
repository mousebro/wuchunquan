<?php
/**
 * User: Fang
 * Time: 15:14 2016/3/10
 */

namespace Model\Member;

use Library\Model;

class MemberRelationship extends Model

{
    private $_memberTable = 'pft_member';
    private $_memberRealtionTable = 'pft_member_relationship';
    public $memberID;

    public function __construct($memberID)
    {
        parent::__construct();
        $this->memberID = $memberID;
    }

    /**
     * 根据名称或账号获取分销商
     * @param string $search
     * @param        $page
     * @param        $limit
     * @param bool   $count
     *
     * @return mixed
     */
    public function getDistributor($search = '',$page,$limit,$count=false)
    {
        $limit = $page ? ($limit ? $limit : 20) : 9999;
        $page = $page ? $page : 1;
        $table = "{$this->_memberRealtionTable} AS mr ";
        $join  = "left join {$this->_memberTable} AS m ON m.id=mr.son_id ";
        $where = array(
            'mr.parent_id'   => $this->memberID,
            'mr.son_id_type' => 0,
            'mr.ship_type'   => 0,
            'mr.status'      => 0,
            'mr.son_id'      => array('not in',[1,$this->memberID]),
            'm.dtype'=>array('in',[0,1,7]),
            'm.status'=>array('in',[0,3]),
            'length(m.account)' => 6,
        );
        if ( ! empty($search)) {
            if (intval($search)) {
//                $where['m.account'] = intval($search);
                $where['m.account'] = array("like", "%{$search}%");
            } else {
                $where['m.dname'] = array("like", "%{$search}%");
            }
        }
        $field = array(
            'm.id',
            'm.dname',
            'm.account',
            'm.cname as concact',
            'm.mobile',
        );
        $order = array(
            'mr.status ASC',
            'mr.id DESC',
        );
        if($count){
            $field = "count(*) as total";
            $result = $this->table($table)->join($join)->where($where)->field($field)->find();
            $result = $result['total'];
        }else{
            $result = $this->table($table)
                           ->join($join)
                           ->where($where)
                           ->field($field)
                           ->order($order)
                           ->page($page)
                           ->limit($limit)
                           ->select();
        }
//        $this->test();
        return $result;
    }

    /**
     * 判断是否为分销关系
     * @param $distributorID
     *
     * @return bool
     */
    public function isDistributor($distributorID){
        $table = "$this->_memberRealtionTable AS mr";
        $where = array(
            'mr.parent_id'   => $this->memberID,
            'mr.son_id'      => $distributorID,
            'mr.son_id_type' => 0,
        );
        $field = array(
            'mr.id',
        );
        $result = $this->table($table)
                       ->where($where)
                       ->field($field)
                       ->find();
        $result = $result ? true : false;
//        $this->test();
//        var_dump($result);
//        exit;
        return $result;
    }
    private function test(){
        $str = $this -> getLastSql();
        print_r($str);
    }

}