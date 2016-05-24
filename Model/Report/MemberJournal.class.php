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
    public function getMemberList(Array $memberIds=[], $expectFlag = false, $limit=0, $offset=200)
    {
        $map = [
            'dtype' =>['in', '0,1,9'],
            'status'=>0,
        ];
        if (count($memberIds)>0) {
            $flag = 'in';
            if ($expectFlag) $flag = 'not in';
            $map['id']  = [$flag, $memberIds];
        }
        if ($expectFlag) {
            $items = $this->table('pft_member')
                ->where($map)
                ->limit($limit, $offset)
                ->getField('id,account,dname', true);
        }
        else {
            $items = $this->table('pft_member')
                ->where($map)
                ->getField('id,account,dname', true);
        }
        $data = [];
        foreach ($items as $item) {
            $data[$item['id']] = [
                'account'=>$item['account'],
                'dname'=>$item['dname']
            ];
        }
        return $data;
    }
    public function AdminSummary($startDate, $endDate, $memberId=0, $expectMember=array())
    {
        if (!$startDate || !$endDate) return [];
        $where = '1=1 ';
        if (is_numeric($memberId) && $memberId>0) $where .= " AND fid=$memberId";
        elseif (is_array($memberId)) $where .= " AND fid IN(".implode(',', $memberId).")";
        if (!empty($expectMember)) $where .= " AND fid NOT IN(".implode(',', $expectMember) .")";
        $where .= " AND ptype in (0,1,4,5,6)";
        $where .= " AND rectime BETWEEN '$startDate' and '$endDate'";
        $sql = <<<SQL
select fid,daction,dtype,sum(dmoney) as dmoney from {$this->_journalTable}
where $where
group by fid,daction,dtype
SQL;
        //echo $sql;
        $items = $this->query($sql);
        $data  = [];
        foreach ($items as $item) {
            if (isset($data[$item['fid']][$item['daction']][$item['dtype']]))
                $data[$item['fid']][$item['daction']][$item['dtype']] += $item['dmoney'];
            $data[$item['fid']][$item['daction']][$item['dtype']] = $item['dmoney'];
        }
        return $data;
    }

    public function MoneySummary($startDate, $endDate,$memberIdList)
    {
        $data = [];
        $map1 = [
            'fid'       => ['in', $memberIdList],
            'rectime'   => ['elt', $startDate],
            'ptype'     => ['not in','2,3'],
        ];
        $map2 = [
            'fid'       => ['in', $memberIdList],
            'rectime'   => ['elt', $endDate],
            'ptype'     => ['not in','2,3'],
        ];
        $preMaxId = $this->table($this->_journalTable)->where($map1)->group('fid')->getField('max(id)', true);
        $preList  = $this->table($this->_journalTable)
            ->where(['id'=>['in', $preMaxId]])
            ->getField('fid,lmoney', true);
        foreach ($preList as $fid=>$money) {
            $data[$fid]['pre'] =$money;
        }
        $curMaxId = $this->table($this->_journalTable)->where($map2)->group('fid')->getField('max(id)', true);
        $curList  = $this->table($this->_journalTable)
            ->where(['id'=>['in', $curMaxId]])
            ->getField('fid, lmoney', true);
        foreach ($curList as $fid=>$money) {
            $data[$fid]['cur'] =$money;
        }
       return $data;
    }
}