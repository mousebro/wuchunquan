<?php
/**
 * Created by PhpStorm.
 * User: cgp
 * Date: 16/4/25
 * Time: 21:08
 * 交易记录统计
 */

namespace Model\Report;


use Library\Model;

class MemberJournal extends Model
{
    private $_journalTable = 'pft_member_journal';
    private $_where = '';
    public function __construct()
    {
        parent::__construct('slave', 'pft');
    }

    public function AdminSummary($startDate, $endDate, $memberId=0, $expectMember=array())
    {
        $where = '1=1 ';
        if (is_numeric($memberId) && $memberId>0) $where .= " AND fid=$memberId";
        if (!empty($expectMember)) $where .= " AND fid NOT IN(".implode(',', $expectMember) .")";
        $where .= " AND ptype<>2 AND ptype<>3";
        $where .= " AND rectime=>'$startDate' and rectime<='$endDate'";
        $sql = <<<SQL
select fid,daction,dtype,ptype,sum(dmoney) as dmoney from {$this->_journalTable}
where $where
group by fid,daction,dtype,ptype
SQL;
        //echo $sql;exit;
        $items = $this->query($sql);
        $data  = [];
        foreach ($items as $item) {
            if (isset($data[$item['fid']][$item['daction']][$item['dtype']]))
                $data[$item['fid']][$item['daction']][$item['dtype']] += $item['dmoney'];
            $data[$item['fid']][$item['daction']][$item['dtype']] = $item['dmoney'];
        }
        return $data;
    }
    private function MoneySummary()
    {
        $where = $this->_where;
        $where .= "";
        $sql = <<<SQL
SELECT lmoney,fid,MAX(rectime)  AS rectime FROM {$this->_journalTable} WHERE $this->_where
GROUP BY fid ORDER BY rectime DESC
SQL;
;
    }
}