<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 4/5-005
 * Time: 17:37
 */
namespace Model\Report;
use Library\Model;

class HomeSimpleReport extends Model
{
    private function change_db()
    {
        $dbConf = C('db');
        $this->db(1, $dbConf['slave'], true);
    }

    /**
     * 店铺提醒
     *
     * @param $aid
     * @param $start_date
     * @param $end_date
     * @param bool|false $today
     * @return mixed
     */
    public function StoreNotice($aid, $start_date, $end_date, $today=false)
    {

        if ($today) {
            $where = [
                'aid'   =>$aid,
                'status'=>1,
                'dtime'=> array(array('egt',$start_date),array('elt',$end_date))
            ];
            $data = $this->table('uu_ss_order')
                ->where($where)
                ->field('COUNT(*) AS cnt,SUM(tnum) AS tnum,SUM(totalmoney) as totalmoney')
                ->find();
        }
        else {
            $this->change_db();
            $where = [
                'aid'       => $aid,
                'mid'       => ['neq',$aid],
                'ddate'     => [['egt',$start_date],['elt',$end_date]],
            ];
            $data = $this->table('pft_order_statistics')
                ->where($where)
                ->field('sum(torder) as cnt,sum(tnum) as tnum,sum(money) as totalmoney')
                ->find();
        }
        //echo $this->getLastSql();
        return $data;
    }

    /**
     * 7日销售排行
     *
     * @param $date
     * @param int $aid
     * @return mixed
     */
    public function WeekSale($date, $aid=0)
    {
        $this->change_db();
        $where = "ddate>'$date'";
        if ($aid>0 && $aid<>1) $where .= " AND aid=$aid";
        $sql = <<<SQL
select tid,sum(torder) as torder,sum(tnum) as tnum,sum(money) as money,sum(pmode0) as pmode0,
sum(pmode1) as pmode1,sum(pmode2) as pmode2,sum(pmode3) as pmode3
from pft_order_statistics where $where group by tid
SQL;
        $result = $this->db(1)->query($sql);
        return $result;
    }

    public function ticket_info(Array $tid)
    {

        $land = [];
        //$map['"uu_jq_ticket.id']  = array('not in','1,5,8');
        $data = $this->table('uu_jq_ticket')
            ->field('uu_land.id as lid,uu_jq_ticket.id as tid,areacode,uu_land.title,jtype,p_type')
            ->where(["uu_jq_ticket.id"=>["in",$tid]])
            ->join('left join uu_land ON uu_land.id=uu_jq_ticket.landid')
            ->select();
        //echo $this->getLastSql();
        //echo $this->getDbError();
        foreach ($data as $row) {
            $land[$row['tid']][$row['lid']] = $row;
        }
        return $land;
    }
}