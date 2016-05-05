<?php
/**
 * User: Fang
 * Time: 15:14 2016/3/10
 */

namespace Model\Member;

use Library\Model;

class MemberRelationship extends Model

{
    private $memberTable = 'pft_member';
    private $memberRealtionTable = 'pft_member_relationship';
    private $memberExtInfoTable = 'pft_member_extinfo';
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
    public function getDispatchDistributor($search = '',$page,$limit)
    {
        $limit = $page ? ($limit ? $limit : 20) : 9999;
        $page = $page ? $page : 1;
        $table = "{$this->memberRealtionTable} AS mr ";
        $join  = [
            "left join {$this->memberTable} AS m ON m.id=mr.son_id ",
            "left join {$this->memberExtInfoTable} AS me ON me.fid=mr.son_id ",
        ];
        $where = array(
            'mr.parent_id'   => $this->memberID,
            'mr.son_id_type' => 0,
            'mr.ship_type'   => 0,
            'mr.status'      => 0,
            'mr.son_id'      => array('not in',[1,$this->memberID]),
            'm.dtype'=>array('in',[0,1,7]),
            'm.status'=>array('in',[0,3]),
            'length(m.account)' => 6,
            'me.com_type' => array('not in',['电商','团购网','淘宝/天猫','电商/团购网']),
        );
        if ( ! empty($search)) {
            if (is_numeric($search)) {
                if(strlen($search)>6){
                    $where['m.mobile'] = array("like", "%{$search}%");
                }else{
                    $where['m.account'] = array("like", "%{$search}%");
                }
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

        $total = $this->table($table)->join($join)->where($where)->count();
        $data = $this->table($table)
            ->join($join)
            ->where($where)
            ->field($field)
            ->order($order)
            ->page($page)
            ->limit($limit)
            ->select();
        $data = is_array($data) ? $data : [];
        return array($total,$data);
    }

    /**
     * 判断是否为分销关系
     * @param $distributorID
     *
     * @return bool
     */
    public function isDistributor($distributorID){
        $table = "$this->memberRealtionTable AS mr";
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