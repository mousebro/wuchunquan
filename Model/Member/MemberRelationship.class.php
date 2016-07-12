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
    private $memberCreditTable = 'pft_member_credit';
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
        return $result;
    }

    /**
     * 获取相关商家列表
     *
     * @param   string      $keywords      查询关键字
     * @param   integer     $relation      会员关系     0-分销商 1-供应商 2-供应商和分销商
     * @param   array       $field         返回字段
     * @return  mixed
     */
    public function getRelevantMerchants($keywords, array $field = ['m.id'], $limit = 20, $relation = 2){

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

            if (strlen($keywords) == 11) {
                $param['m.mobile'] = ':mobile';
                $bind[':mobile'] = $keywords;
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
        return $result;
    }

    /**
     * 获取要导出合作分销商的数据
     * @param  [type] $condition 查询分销商条件
     * @param  string $sql       是否是拼装好的sql语句
     * @return [type]            [description]
     */
    public function getReportDisData($condition, $sql = 'false') {
        if ($sql) {
            $distributors = $this->query($condition);
        }

        if (!$distributors) return [];

        $members = [];
        foreach ($distributors as $key => $dis) {
            $dis['group'] = '未分组';
            $members[$dis['id']] = $dis;
        }

        if (!class_exists('\PFT\PriceGroup')) {
            include BASE_WWW_DIR . '/class/PriceGroup.class.php';
        }

        //分组信息
        $groups = \PFT\PriceGroup::getGroupsBySid($this->memberID);

        //分销商的授信信息
        $credits = $this->getCreditForMulti($this->memberID, array_keys($members));

        $return = [];
        foreach ($groups as $group) {

            if (!$group['dids']) continue;

            $did_arr = explode(',', $group['dids']);
            foreach ($did_arr as $did) {
                if (isset($members[$did])) {
                    $return[] = array_merge($members[$did], ['group' => $group['name']]);
                    unset($members[$did]);
                }
            }
        }

        $return = array_merge($return, $members);

        foreach ($return as $key => $item) {

            if (isset($credits[$item['id']])) {
                $return[$key]['credit'] = $credits[$item['id']]['credit'];
            } else {
                $return[$key]['credit'] = 0;
            }
        }

       return $return;
    }

    /**
     * 批量获取多个分销商的供应商授信余额
     * @param  int $sid          
     * @param  array $memberid_arr 分销商id
     * @return [type]               [description]
     */
    public function getCreditForMulti($sid, $memberid_arr) {

        $where = [
            'aid'   => $sid,
            'fid'   => ['in', implode(',', $memberid_arr)]
        ];

        $field = 'fid,kmoney,basecredit,(kmoney + basecredit) as credit,basetime,baseauthority';

        $credits = $this->table($this->memberCreditTable)->where($where)->field($field)->select();

        if (!$credits) return [];

        $return = [];
        foreach ($credits as $item) {
            $item['credit'] = sprintf("%.2f", $item['credit'] / 100);
            $item['kmoney'] = sprintf("%.2f", $item['kmoney'] / 100);
            $item['basecredit'] = sprintf("%.2f", $item['basecredit'] / 100);
            $return[$item['fid']] = $item;
        }

        return $return;
    }


}