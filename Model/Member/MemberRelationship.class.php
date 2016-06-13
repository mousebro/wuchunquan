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

    /**
     * 获取相关商家列表
     *
     * @param   string      $keywords      查询关键字
     * @param   integer     $relation      会员关系     0-分销商 1-供应商 2-供应商和分销商
     * @param   array       $field         返回字段
     * @return  mixed
     */
    public function getRelevantMerchants($keywords, array $field = ['m.id'], $relation = 0, $limit = 20){

        //输入少于4个字符的英文字符串: 不查询
        if (preg_match("/^[a-zA-Z\s]+$/", $keywords) && strlen($keywords) < 4) {
            return false;
        }
        
        //初始化
        $table = "{$this->memberRealtionTable} AS mr";
        $where = [
            'mr.son_id_type'     => 0,
            'mr.ship_type'       => 0,
            'mr.son_id'          => array('not in',['1',$this->memberID]),
            'm.dtype'            => array('in',[0,1,7]),
            'm.status'           => array('in',[0,3]),
            'length(m.account)'  => 6,
        ];
        
        //处理查询关键字
        $param['m.dname'] = ['like', ':dname'];
        $bind[':dname'] = '%' . $keywords . '%';

        //关键字全为数字时查询对应会员ID
        if (is_numeric($keywords)) {
            $param['m.id'] = ':id';
            $bind[':id'] = $keywords;
            //关键字为6位数字时查询对应会员账号
            if (strlen($keywords) == 6) {
                $param['m.account'] = ':account';
                $bind[':account'] = $keywords;
            }
        }
       
        if (count($bind) > 1) {
            $param['_logic'] = 'or';
        }
        
        $where['_complex'][] = $param;
        
        $select_distributor = [
            'mr.parent_id'       => $this->memberID,
            'mr.son_id'          => array('not in',['1',$this->memberID]),
        ];
        
        $select_supplier = [
            'mr.parent_id'      => array('not in',['1',$this->memberID]),
            'mr.son_id'         => $this->memberID,
        ];
        
        if($relation==0){
            $where = array_merge($select_distributor);
            $join_on = "mr.son_id = m.id";
        }elseif($relation==1){
            $where = array_merge($select_supplier);
            $join_on = "mr.parent_id = m.id";
        }else{
            $where['_complex'][] = [$select_distributor,$select_supplier,'_logic'=>'or'];
            $join_on = " ( mr.son_id = m.id OR mr.parent_id = m.id) ";
        }
       
        $join = "INNER JOIN {$this->memberTable} AS m ON " . $join_on;
        
        $result = $this->table($table)
            ->join($join)
            ->where($where)
            ->bind($bind)
            ->field($field)
            ->limit($limit)
            ->select();
        $this->logSql();
        return $result;
    }

}