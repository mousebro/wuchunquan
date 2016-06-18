<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 5/23-023
 * Time: 9:16
 */

namespace Model\Report;


use Library\Model;

class ApplyerReport extends Model
{
    private $dbConf;
    const COUNT_TABLE = 'pft_applyer_order';
    public function __construct()
    {
        $this->dbConf = C('db');
        $this->db(1, $this->dbConf['summary'], true);
        parent::__construct('summary');
    }
    /**
     * 景区销量统计
     *
     * @param int $date 日期（Ymd）
     * @return bool
     */
    public function OrderSummaryByLid($date, $lid=0, $getData=false)
    {
        $this->db(0, $this->dbConf['slave'], true);

        $startTime = $date . '000000';
        $endTime   = $date . '235959';
        $where = '';
        if (is_numeric($lid) && $lid>0) $where = " lid=$lid AND ";

        $sql = <<<SQL
SELECT tid,COUNT(*) AS cnt,SUM(tnum) AS tnum, SUM(totalmoney) AS totalmoney
FROM uu_ss_order
WHERE $where ordertime BETWEEN '$startTime' AND '$endTime'
GROUP BY tid
SQL;
        //echo $sql;exit;
        $orders = $this->db(0)->query($sql);
        if (!$orders) return false;
        if ($getData===true) return $orders;
        $output = [];
        foreach ($orders as $order) {
            $output[$order['tid']] = $order;
        }
        $infos = $this->db(0)->table('uu_jq_ticket t')
            ->join('left join uu_land l ON l.id=t.landid')
            ->join('LEFT JOIN pft_member m ON m.id=l.apply_did')
            ->where(['t.id'=>['in', array_keys($output)]])
            ->field('l.id as lid,t.id as tid, m.id as mid,l.title as ltitle,t.title as ttitle, m.dname')
            ->select();
        //echo $this->getLastSql();
        $save = array();
        //print_r($infos);
        //$sday = date('Ymd', strtotime($endTime));
        foreach ($infos as $info)
        {
            //echo $info['tid'];
            $save[] = [
                'sday'      => $date,
                'lid'       => $info['lid'],
                'tid'       => $info['tid'],
                'apply_did' => $info['mid'],
                'ltitle'     => $info['ltitle'],
                'ttitle'     => $info['ttitle'],
                'dname'     => $info['dname'],
                'onum'      =>$output[$info['tid']]['cnt'],
                'tnum'      => $output[$info['tid']]['tnum'],
                'total_money'=>$output[$info['tid']]['totalmoney'],
            ];
        }
        //var_export($save);exit;
        $res = $this->db(1)->table(self::COUNT_TABLE)->addAll($save);
        if ($res===false) {
            echo $this->getDbError();
        }
    }

    /**
     * @param int $day1
     * @param int $day2
     * @param int $lid
     * @param int $apply_did
     * @param int $group
     * @return bool| array
     */
    public function GetOrderSummaryById($day1=0, $day2=0, $lid=0, $apply_did=0, $group=0)
    {
        $where = [];
        if ($lid==0 && $apply_did==0) return false;
        if ($lid>0) $where['lid']       = $lid;
        if ($apply_did>0) $where['apply_did'] = $apply_did;
        if ($day1>0) $where['sday'][]   = ['egt', $day1];
        if ($day2>0) $where['sday'][]   = ['elt', $day2];
        $groupby = 'sday';

        $field = 'SUM(tnum) as tnum,SUM(onum) as onum,SUM(total_money) AS total_money';
        if ($group==1) {
            $groupby = 'mon';
            $field .= ",CONCAT(substr(sday, 1, 6),'01') AS mon";
        }
        else $field .= ",sday";
        $data = $this->table(self::COUNT_TABLE)
        ->field($field)
        ->where($where)
        ->group($groupby)
        ->order("sday ASC")
        ->select();
        //echo $this->getLastSql();
        return $data;
    }

    /**
     * 月销量统计
     *
     * @param int $top 取前几名的数据
     * @param int $group 分组依据，1：供应商，2：景点
     * @param int $order 排序依据，1:票数，2:订单数，3:订单总额
     * @return bool|array
     */
    public function MonthCount($top=30, $group=1, $order=1)
    {
        $day1 = date('Ymd', strtotime('-30 days'));
        $day2 = date('Ymd');
        $where = [
            'sday'=>[
                ['egt',$day1],
                ['elt',$day2],
            ]
        ];
        if ($group==1) {
            $group  ='apply_did';
            $field = "apply_did as gid,concat(dname,'(',apply_did,')') as title,";
        }
        elseif ($group==2){
            $group='lid';
            $field = "lid as gid, concat(ltitle,'(',lid,')') as title,";
        }
        else {
            return false;
        }
        $orderBy = 'tnum';
        if ($order==2)      $orderBy = 'onum';
        elseif ($order==3)  $orderBy = 'total_money';

        $field .= "SUM(onum) as onum,SUM(tnum) as tnum, SUM(total_money) as total_money,`$group`";
        //$field .= "SUM($orderBy) as cnt,`$group`";
        $data = $this->table(self::COUNT_TABLE)
            ->field($field)
            ->where($where)
            ->group($group)
            ->order("$orderBy DESC")
            ->limit($top)
            ->select();
        //echo $this->getLastSql();
        return $data;
    }

    public function GetTitleList($type, $day1=0, $day2=0)
    {
        $day1 = $day1==0 ? date('Ymd', strtotime('-30 days')) : $day1;
        $day2 = $day2==0 ? date('Ymd') : $day2;
        $output = array();
        $query = $this->db(1)->table(self::COUNT_TABLE)
            ->where(
                [
                    'sday'=>[
                        ['egt',$day1],
                        ['elt',$day2],
                    ]
                ]
            );
        if ($type==1) $query->field('dname as name,apply_did as id')->group('apply_did');
        elseif ($type==2) $query->field('ltitle as name,lid as id')->group('lid');
        $data =  $query->select();
        //echo $this->getLastSql();
        foreach ($data as $item) {
            $output[$item['id']] = $item['name'];
        }
        return $output;
    }
}