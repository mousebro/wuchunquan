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
     *
     * @return mixed
     */
    public function getDistributor($search = '')
    {
        $table = "{$this->_memberTable} AS m";
        $join  = "LEFT JOIN {$this->_memberRealtionTable} AS mr ON m.id=mr.son_id";
        $where = array(
            'mr.parent_id'   => $this->memberID,
            'mr.son_id_type' => 0,
            'mr.status'      => 0,
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
        );
        $order = array(
            'mr.status ASC',
            'mr.id DESC',
        );

        $result = $this->table($table)
                       ->join($join)
                       ->where($where)
                       ->field($field)
                       ->order($order)
                       ->select();
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